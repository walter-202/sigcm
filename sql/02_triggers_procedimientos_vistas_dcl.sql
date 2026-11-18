-- ============================================================================
-- OBJETOS DE BASE DE DATOS II (PostgreSQL 17):
-- ÍNDICES, TRIGGERS, PROCEDIMIENTOS TRANSACCIONALES, CURSOR, VISTAS Y DCL
-- ============================================================================

-- 1. ÍNDICES DE RENDIMIENTO
CREATE INDEX IF NOT EXISTS idx_doctors_specialty
    ON doctors (specialty);

CREATE INDEX IF NOT EXISTS idx_appointments_status
    ON appointments (status);

CREATE INDEX IF NOT EXISTS idx_appointments_doctor_interval
    ON appointments (doctor_id, starts_at, ends_at);

CREATE INDEX IF NOT EXISTS idx_consultations_status
    ON medical_consultations (status);

CREATE INDEX IF NOT EXISTS idx_audit_events_occurred
    ON audit_events (occurred_at DESC);

CREATE INDEX IF NOT EXISTS idx_allergies_patient_active
    ON patient_allergies (patient_id, clinical_status);

CREATE INDEX IF NOT EXISTS idx_prescriptions_consultation
    ON prescriptions (consultation_id, status);

CREATE INDEX IF NOT EXISTS idx_diagnostic_reports_status
    ON diagnostic_reports (status, clinical_order_id);

-- 2. TRIGGERS DE INTEGRIDAD CLÍNICA, CONCORDANCIA, AGENDA Y AUDITORÍA (PL/pgSQL)

-- 2.1 Inmutabilidad de consultas médicas firmadas
CREATE OR REPLACE FUNCTION fn_validar_inmutabilidad_consulta()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        IF OLD.status = 'signed' THEN
            RAISE EXCEPTION 'Error de integridad clínica: No se puede eliminar la consulta ID % porque ya está firmada.', OLD.id;
        END IF;
        RETURN OLD;
    END IF;

    IF TG_OP = 'UPDATE' THEN
        IF OLD.status = 'signed' THEN
            RAISE EXCEPTION 'Error de integridad clínica: La consulta ID % ya está firmada y es inmutable. Registre una enmienda en consultation_amendments.', OLD.id;
        END IF;

        IF NEW.status = 'signed' AND COALESCE(btrim(NEW.chief_complaint), '') = '' THEN
            RAISE EXCEPTION 'Error de integridad clínica: El motivo de consulta (chief_complaint) es obligatorio para firmar.';
        END IF;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_validar_inmutabilidad_consulta ON medical_consultations;

CREATE TRIGGER trg_validar_inmutabilidad_consulta
BEFORE UPDATE OR DELETE ON medical_consultations
FOR EACH ROW
EXECUTE FUNCTION fn_validar_inmutabilidad_consulta();

-- 2.2 Protección append-only para consultation_amendments y audit_events
CREATE OR REPLACE FUNCTION fn_bloquear_modificacion_append_only()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'Error de integridad documental: Los registros de % son estrictamente inmutables (append-only).', TG_TABLE_NAME;
END;
$$;

DROP TRIGGER IF EXISTS trg_inmutabilidad_enmiendas ON consultation_amendments;

CREATE TRIGGER trg_inmutabilidad_enmiendas
BEFORE UPDATE OR DELETE ON consultation_amendments
FOR EACH ROW
EXECUTE FUNCTION fn_bloquear_modificacion_append_only();

DROP TRIGGER IF EXISTS trg_inmutabilidad_audit_events ON audit_events;

CREATE TRIGGER trg_inmutabilidad_audit_events
BEFORE UPDATE OR DELETE ON audit_events
FOR EACH ROW
EXECUTE FUNCTION fn_bloquear_modificacion_append_only();

-- 2.3 Protección de tablas hijas clínicas cuando la consulta ya fue firmada
CREATE OR REPLACE FUNCTION fn_validar_hijos_consulta_no_firmada()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_consultation_id BIGINT;
    v_status VARCHAR(15);
BEGIN
    v_consultation_id := COALESCE(NEW.consultation_id, OLD.consultation_id);

    SELECT status INTO v_status
    FROM medical_consultations
    WHERE id = v_consultation_id;

    IF v_status = 'signed' THEN
        RAISE EXCEPTION 'Error de integridad clínica: No se pueden alterar registros de % porque la consulta ID % ya está firmada.',
            TG_TABLE_NAME, v_consultation_id;
    END IF;

    RETURN COALESCE(NEW, OLD);
END;
$$;

DROP TRIGGER IF EXISTS trg_validar_diagnosticos_consulta_abierta ON consultation_diagnoses;

CREATE TRIGGER trg_validar_diagnosticos_consulta_abierta
BEFORE INSERT OR UPDATE OR DELETE ON consultation_diagnoses
FOR EACH ROW
EXECUTE FUNCTION fn_validar_hijos_consulta_no_firmada();

DROP TRIGGER IF EXISTS trg_validar_observaciones_consulta_abierta ON clinical_observations;

CREATE TRIGGER trg_validar_observaciones_consulta_abierta
BEFORE INSERT OR UPDATE OR DELETE ON clinical_observations
FOR EACH ROW
EXECUTE FUNCTION fn_validar_hijos_consulta_no_firmada();

-- 2.4 Inmutabilidad de recetas emitidas/sustituidas/anuladas y sus ítems
CREATE OR REPLACE FUNCTION fn_validar_inmutabilidad_receta()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        IF OLD.status <> 'draft' THEN
            RAISE EXCEPTION 'Error de integridad clínica: No se puede eliminar la receta ID % en estado %.', OLD.id, OLD.status;
        END IF;
        RETURN OLD;
    END IF;

    IF TG_OP = 'UPDATE' THEN
        IF OLD.status IN ('replaced', 'void') THEN
            RAISE EXCEPTION 'Error de integridad clínica: La receta ID % en estado % es inmutable.', OLD.id, OLD.status;
        END IF;

        IF OLD.status = 'issued' THEN
            IF NEW.status NOT IN ('replaced', 'void')
               OR NEW.consultation_id IS DISTINCT FROM OLD.consultation_id
               OR NEW.supersedes_prescription_id IS DISTINCT FROM OLD.supersedes_prescription_id
               OR NEW.issued_at IS DISTINCT FROM OLD.issued_at THEN
                RAISE EXCEPTION 'Error de integridad clínica: Una receta emitida solo puede pasar a replaced o void sin alterar sus datos originales.';
            END IF;
        END IF;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_validar_inmutabilidad_receta ON prescriptions;

CREATE TRIGGER trg_validar_inmutabilidad_receta
BEFORE UPDATE OR DELETE ON prescriptions
FOR EACH ROW
EXECUTE FUNCTION fn_validar_inmutabilidad_receta();

CREATE OR REPLACE FUNCTION fn_validar_items_receta_borrador()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_prescription_id BIGINT;
    v_status VARCHAR(20);
BEGIN
    v_prescription_id := COALESCE(NEW.prescription_id, OLD.prescription_id);

    SELECT status INTO v_status
    FROM prescriptions
    WHERE id = v_prescription_id;

    IF v_status IS NOT NULL AND v_status <> 'draft' THEN
        RAISE EXCEPTION 'Error de integridad clínica: No se pueden modificar ítems de la receta ID % porque está en estado %.',
            v_prescription_id, v_status;
    END IF;

    RETURN COALESCE(NEW, OLD);
END;
$$;

DROP TRIGGER IF EXISTS trg_validar_items_receta_borrador ON prescription_items;

CREATE TRIGGER trg_validar_items_receta_borrador
BEFORE INSERT OR UPDATE OR DELETE ON prescription_items
FOR EACH ROW
EXECUTE FUNCTION fn_validar_items_receta_borrador();

-- 2.5 Concordancia de paciente entre historia clínica, episodios, consultas y condiciones
CREATE OR REPLACE FUNCTION fn_validar_consistencia_episodio()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_record_patient_id BIGINT;
    v_consultation_patient_id BIGINT;
    v_consultation_status VARCHAR(15);
BEGIN
    SELECT patient_id INTO v_record_patient_id
    FROM clinical_records
    WHERE id = NEW.clinical_record_id;

    SELECT patient_id, status INTO v_consultation_patient_id, v_consultation_status
    FROM medical_consultations
    WHERE id = NEW.opening_consultation_id;

    IF v_record_patient_id IS DISTINCT FROM v_consultation_patient_id THEN
        RAISE EXCEPTION 'Error de integridad clínica: La consulta de apertura ID % no pertenece al mismo paciente que la historia clínica ID %.',
            NEW.opening_consultation_id, NEW.clinical_record_id;
    END IF;

    IF v_consultation_status <> 'signed' THEN
        RAISE EXCEPTION 'Error de integridad clínica: Solo una consulta firmada puede abrir un episodio de atención.';
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_validar_consistencia_episodio ON care_episodes;

CREATE TRIGGER trg_validar_consistencia_episodio
BEFORE INSERT OR UPDATE ON care_episodes
FOR EACH ROW
EXECUTE FUNCTION fn_validar_consistencia_episodio();

CREATE OR REPLACE FUNCTION fn_validar_consistencia_episodio_consulta()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_record_patient_id BIGINT;
    v_consultation_patient_id BIGINT;
BEGIN
    SELECT cr.patient_id INTO v_record_patient_id
    FROM care_episodes ce
    INNER JOIN clinical_records cr ON cr.id = ce.clinical_record_id
    WHERE ce.id = NEW.episode_id;

    SELECT patient_id INTO v_consultation_patient_id
    FROM medical_consultations
    WHERE id = NEW.consultation_id;

    IF v_record_patient_id IS DISTINCT FROM v_consultation_patient_id THEN
        RAISE EXCEPTION 'Error de integridad clínica: La consulta ID % no corresponde al paciente del episodio ID %.',
            NEW.consultation_id, NEW.episode_id;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_validar_consistencia_episodio_consulta ON episode_consultations;

CREATE TRIGGER trg_validar_consistencia_episodio_consulta
BEFORE INSERT OR UPDATE ON episode_consultations
FOR EACH ROW
EXECUTE FUNCTION fn_validar_consistencia_episodio_consulta();

CREATE OR REPLACE FUNCTION fn_validar_consistencia_episodio_condicion()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_record_patient_id BIGINT;
    v_condition_patient_id BIGINT;
BEGIN
    SELECT cr.patient_id INTO v_record_patient_id
    FROM care_episodes ce
    INNER JOIN clinical_records cr ON cr.id = ce.clinical_record_id
    WHERE ce.id = NEW.episode_id;

    SELECT patient_id INTO v_condition_patient_id
    FROM patient_conditions
    WHERE id = NEW.condition_id;

    IF v_record_patient_id IS DISTINCT FROM v_condition_patient_id THEN
        RAISE EXCEPTION 'Error de integridad clínica: La condición ID % no pertenece al paciente del episodio ID %.',
            NEW.condition_id, NEW.episode_id;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_validar_consistencia_episodio_condicion ON episode_conditions;

CREATE TRIGGER trg_validar_consistencia_episodio_condicion
BEFORE INSERT OR UPDATE ON episode_conditions
FOR EACH ROW
EXECUTE FUNCTION fn_validar_consistencia_episodio_condicion();

-- 2.6 Validación de solapamiento de citas activas por médico (complementaria al EXCLUDE USING gist)
CREATE OR REPLACE FUNCTION fn_validar_solapamiento_cita()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.status IN ('pending', 'confirmed', 'checked_in') THEN
        IF EXISTS (
            SELECT 1
            FROM appointments a
            WHERE a.doctor_id = NEW.doctor_id
              AND a.id <> COALESCE(NEW.id, -1)
              AND a.status IN ('pending', 'confirmed', 'checked_in')
              AND tstzrange(a.starts_at, a.ends_at, '[)') && tstzrange(NEW.starts_at, NEW.ends_at, '[)')
        ) THEN
            RAISE EXCEPTION 'Error de integridad en agenda: El médico ID % ya posee una cita activa en ese horario.', NEW.doctor_id;
        END IF;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_validar_solapamiento_cita ON appointments;

CREATE TRIGGER trg_validar_solapamiento_cita
BEFORE INSERT OR UPDATE ON appointments
FOR EACH ROW
EXECUTE FUNCTION fn_validar_solapamiento_cita();

-- 2.7 Triggers de auditoría automática hacia audit_events (datos minimizados)
CREATE OR REPLACE FUNCTION fn_auditoria_consultas()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_actor_user_id BIGINT;
    v_operation VARCHAR(12);
BEGIN
    v_actor_user_id := NULLIF(current_setting('app.current_user_id', true), '')::BIGINT;

    IF v_actor_user_id IS NULL THEN
        SELECT d.user_id
        INTO v_actor_user_id
        FROM doctors d
        WHERE d.id = NEW.doctor_id;
    END IF;

    IF TG_OP = 'INSERT' THEN
        v_operation := CASE WHEN NEW.status = 'signed' THEN 'SIGN' ELSE 'INSERT' END;
        INSERT INTO audit_events (actor_user_id, entity_name, entity_id, operation, changed_fields, occurred_at)
        VALUES (
            v_actor_user_id,
            'medical_consultations',
            NEW.id,
            v_operation,
            jsonb_build_object(
                'new_status', NEW.status,
                'patient_id', NEW.patient_id,
                'doctor_id', NEW.doctor_id,
                'db_user', CURRENT_USER
            ),
            CURRENT_TIMESTAMP
        );
    ELSIF TG_OP = 'UPDATE' THEN
        IF OLD.status IS DISTINCT FROM NEW.status
           OR OLD.signed_at IS DISTINCT FROM NEW.signed_at
           OR OLD.chief_complaint IS DISTINCT FROM NEW.chief_complaint THEN
            v_operation := CASE
                WHEN OLD.status <> 'signed' AND NEW.status = 'signed' THEN 'SIGN'
                ELSE 'UPDATE'
            END;

            INSERT INTO audit_events (actor_user_id, entity_name, entity_id, operation, changed_fields, occurred_at)
            VALUES (
                v_actor_user_id,
                'medical_consultations',
                NEW.id,
                v_operation,
                jsonb_build_object(
                    'previous_status', OLD.status,
                    'new_status', NEW.status,
                    'signed_at', NEW.signed_at,
                    'db_user', CURRENT_USER
                ),
                CURRENT_TIMESTAMP
            );
        END IF;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_auditoria_consultas ON medical_consultations;

CREATE TRIGGER trg_auditoria_consultas
AFTER INSERT OR UPDATE ON medical_consultations
FOR EACH ROW
EXECUTE FUNCTION fn_auditoria_consultas();

CREATE OR REPLACE FUNCTION fn_auditoria_citas()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_actor_user_id BIGINT;
BEGIN
    v_actor_user_id := COALESCE(
        NULLIF(current_setting('app.current_user_id', true), '')::BIGINT,
        NEW.created_by_user_id
    );

    IF TG_OP = 'INSERT' THEN
        INSERT INTO audit_events (actor_user_id, entity_name, entity_id, operation, changed_fields, occurred_at)
        VALUES (
            v_actor_user_id,
            'appointments',
            NEW.id,
            'INSERT',
            jsonb_build_object(
                'status', NEW.status,
                'starts_at', NEW.starts_at,
                'ends_at', NEW.ends_at,
                'doctor_id', NEW.doctor_id,
                'db_user', CURRENT_USER
            ),
            CURRENT_TIMESTAMP
        );
    ELSIF TG_OP = 'UPDATE' THEN
        IF OLD.status IS DISTINCT FROM NEW.status
           OR OLD.starts_at IS DISTINCT FROM NEW.starts_at
           OR OLD.ends_at IS DISTINCT FROM NEW.ends_at THEN
            INSERT INTO audit_events (actor_user_id, entity_name, entity_id, operation, changed_fields, occurred_at)
            VALUES (
                v_actor_user_id,
                'appointments',
                NEW.id,
                'UPDATE',
                jsonb_build_object(
                    'previous_status', OLD.status,
                    'new_status', NEW.status,
                    'previous_start', OLD.starts_at,
                    'new_start', NEW.starts_at,
                    'previous_end', OLD.ends_at,
                    'new_end', NEW.ends_at,
                    'db_user', CURRENT_USER
                ),
                CURRENT_TIMESTAMP
            );
        END IF;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_auditoria_citas ON appointments;

CREATE TRIGGER trg_auditoria_citas
AFTER INSERT OR UPDATE ON appointments
FOR EACH ROW
EXECUTE FUNCTION fn_auditoria_citas();

CREATE OR REPLACE FUNCTION fn_auditoria_enmiendas()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    INSERT INTO audit_events (actor_user_id, entity_name, entity_id, operation, changed_fields, occurred_at)
    VALUES (
        NEW.author_user_id,
        'consultation_amendments',
        NEW.id,
        'AMEND',
        jsonb_build_object(
            'consultation_id', NEW.consultation_id,
            'reason', NEW.reason,
            'db_user', CURRENT_USER
        ),
        CURRENT_TIMESTAMP
    );

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_auditoria_enmiendas ON consultation_amendments;

CREATE TRIGGER trg_auditoria_enmiendas
AFTER INSERT ON consultation_amendments
FOR EACH ROW
EXECUTE FUNCTION fn_auditoria_enmiendas();

CREATE OR REPLACE FUNCTION fn_auditoria_expedientes()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_actor_user_id BIGINT;
BEGIN
    v_actor_user_id := NULLIF(current_setting('app.current_user_id', true), '')::BIGINT;

    INSERT INTO audit_events (actor_user_id, entity_name, entity_id, operation, changed_fields, occurred_at)
    VALUES (
        v_actor_user_id,
        'clinical_records',
        NEW.id,
        TG_OP,
        jsonb_build_object(
            'patient_id', NEW.patient_id,
            'opened_from_consultation_id', NEW.opened_from_consultation_id,
            'db_user', CURRENT_USER
        ),
        CURRENT_TIMESTAMP
    );

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_auditoria_expedientes ON clinical_records;

CREATE TRIGGER trg_auditoria_expedientes
AFTER INSERT OR UPDATE ON clinical_records
FOR EACH ROW
EXECUTE FUNCTION fn_auditoria_expedientes();

CREATE OR REPLACE FUNCTION fn_auditoria_recetas()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_actor_user_id BIGINT;
BEGIN
    v_actor_user_id := NULLIF(current_setting('app.current_user_id', true), '')::BIGINT;

    IF TG_OP = 'INSERT' OR (TG_OP = 'UPDATE' AND OLD.status IS DISTINCT FROM NEW.status) THEN
        INSERT INTO audit_events (actor_user_id, entity_name, entity_id, operation, changed_fields, occurred_at)
        VALUES (
            v_actor_user_id,
            'prescriptions',
            NEW.id,
            TG_OP,
            jsonb_build_object(
                'consultation_id', NEW.consultation_id,
                'previous_status', CASE WHEN TG_OP = 'UPDATE' THEN OLD.status ELSE NULL END,
                'new_status', NEW.status,
                'supersedes_prescription_id', NEW.supersedes_prescription_id,
                'db_user', CURRENT_USER
            ),
            CURRENT_TIMESTAMP
        );
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_auditoria_recetas ON prescriptions;

CREATE TRIGGER trg_auditoria_recetas
AFTER INSERT OR UPDATE ON prescriptions
FOR EACH ROW
EXECUTE FUNCTION fn_auditoria_recetas();

CREATE OR REPLACE FUNCTION fn_auditoria_informes()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_actor_user_id BIGINT;
BEGIN
    v_actor_user_id := COALESCE(
        NEW.reviewed_by_user_id,
        NULLIF(current_setting('app.current_user_id', true), '')::BIGINT
    );

    IF TG_OP = 'INSERT' OR (TG_OP = 'UPDATE' AND OLD.status IS DISTINCT FROM NEW.status) THEN
        INSERT INTO audit_events (actor_user_id, entity_name, entity_id, operation, changed_fields, occurred_at)
        VALUES (
            v_actor_user_id,
            'diagnostic_reports',
            NEW.id,
            TG_OP,
            jsonb_build_object(
                'clinical_order_id', NEW.clinical_order_id,
                'previous_status', CASE WHEN TG_OP = 'UPDATE' THEN OLD.status ELSE NULL END,
                'new_status', NEW.status,
                'reviewed_at', NEW.reviewed_at,
                'db_user', CURRENT_USER
            ),
            CURRENT_TIMESTAMP
        );
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_auditoria_informes ON diagnostic_reports;

CREATE TRIGGER trg_auditoria_informes
AFTER INSERT OR UPDATE ON diagnostic_reports
FOR EACH ROW
EXECUTE FUNCTION fn_auditoria_informes();

CREATE OR REPLACE FUNCTION fn_auditoria_alergias()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_actor_user_id BIGINT;
BEGIN
    v_actor_user_id := NULLIF(current_setting('app.current_user_id', true), '')::BIGINT;

    INSERT INTO audit_events (actor_user_id, entity_name, entity_id, operation, changed_fields, occurred_at)
    VALUES (
        v_actor_user_id,
        'patient_allergies',
        NEW.id,
        TG_OP,
        jsonb_build_object(
            'patient_id', NEW.patient_id,
            'severity', NEW.severity,
            'clinical_status', NEW.clinical_status,
            'verification_status', NEW.verification_status,
            'db_user', CURRENT_USER
        ),
        CURRENT_TIMESTAMP
    );

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_auditoria_alergias ON patient_allergies;

CREATE TRIGGER trg_auditoria_alergias
AFTER INSERT OR UPDATE ON patient_allergies
FOR EACH ROW
EXECUTE FUNCTION fn_auditoria_alergias();

CREATE OR REPLACE FUNCTION fn_auditoria_pacientes()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_actor_user_id BIGINT;
BEGIN
    v_actor_user_id := NULLIF(current_setting('app.current_user_id', true), '')::BIGINT;

    IF TG_OP = 'INSERT'
        OR OLD.document_type IS DISTINCT FROM NEW.document_type
        OR OLD.document_number IS DISTINCT FROM NEW.document_number
        OR OLD.birth_date IS DISTINCT FROM NEW.birth_date
        OR OLD.is_active IS DISTINCT FROM NEW.is_active
    THEN
        INSERT INTO audit_events (actor_user_id, entity_name, entity_id, operation, changed_fields, occurred_at)
        VALUES (
            v_actor_user_id,
            'patients',
            NEW.id,
            TG_OP,
            jsonb_build_object(
                'document_type', NEW.document_type,
                'document_number', NEW.document_number,
                'is_active', NEW.is_active,
                'db_user', CURRENT_USER
            ),
            CURRENT_TIMESTAMP
        );
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_auditoria_pacientes ON patients;

CREATE TRIGGER trg_auditoria_pacientes
AFTER INSERT OR UPDATE ON patients
FOR EACH ROW
EXECUTE FUNCTION fn_auditoria_pacientes();

CREATE OR REPLACE FUNCTION fn_auditoria_episodios()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_actor_user_id BIGINT;
BEGIN
    v_actor_user_id := NULLIF(current_setting('app.current_user_id', true), '')::BIGINT;

    IF TG_OP = 'INSERT' OR OLD.status IS DISTINCT FROM NEW.status THEN
        INSERT INTO audit_events (actor_user_id, entity_name, entity_id, operation, changed_fields, occurred_at)
        VALUES (
            v_actor_user_id,
            'care_episodes',
            NEW.id,
            TG_OP,
            jsonb_build_object(
                'clinical_record_id', NEW.clinical_record_id,
                'previous_status', CASE WHEN TG_OP = 'UPDATE' THEN OLD.status ELSE NULL END,
                'new_status', NEW.status,
                'closed_at', NEW.closed_at,
                'db_user', CURRENT_USER
            ),
            CURRENT_TIMESTAMP
        );
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_auditoria_episodios ON care_episodes;

CREATE TRIGGER trg_auditoria_episodios
AFTER INSERT OR UPDATE ON care_episodes
FOR EACH ROW
EXECUTE FUNCTION fn_auditoria_episodios();

CREATE OR REPLACE FUNCTION fn_auditoria_documentos()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_actor_user_id BIGINT;
    v_row clinical_documents%ROWTYPE;
BEGIN
    v_row := CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
    v_actor_user_id := COALESCE(
        NULLIF(current_setting('app.current_user_id', true), '')::BIGINT,
        v_row.uploaded_by_user_id
    );

    INSERT INTO audit_events (actor_user_id, entity_name, entity_id, operation, changed_fields, occurred_at)
    VALUES (
        v_actor_user_id,
        'clinical_documents',
        v_row.id,
        TG_OP,
        jsonb_build_object(
            'patient_id', v_row.patient_id,
            'consultation_id', v_row.consultation_id,
            'document_type', v_row.document_type,
            'mime_type', v_row.mime_type,
            'db_user', CURRENT_USER
        ),
        CURRENT_TIMESTAMP
    );

    RETURN COALESCE(NEW, OLD);
END;
$$;

DROP TRIGGER IF EXISTS trg_auditoria_documentos ON clinical_documents;

CREATE TRIGGER trg_auditoria_documentos
AFTER INSERT OR UPDATE OR DELETE ON clinical_documents
FOR EACH ROW
EXECUTE FUNCTION fn_auditoria_documentos();

CREATE OR REPLACE FUNCTION fn_auditoria_condiciones()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_actor_user_id BIGINT;
BEGIN
    v_actor_user_id := NULLIF(current_setting('app.current_user_id', true), '')::BIGINT;

    IF TG_OP = 'INSERT'
        OR OLD.clinical_status IS DISTINCT FROM NEW.clinical_status
        OR OLD.verification_status IS DISTINCT FROM NEW.verification_status
    THEN
        INSERT INTO audit_events (actor_user_id, entity_name, entity_id, operation, changed_fields, occurred_at)
        VALUES (
            v_actor_user_id,
            'patient_conditions',
            NEW.id,
            TG_OP,
            jsonb_build_object(
                'patient_id', NEW.patient_id,
                'code', NEW.code,
                'clinical_status', NEW.clinical_status,
                'verification_status', NEW.verification_status,
                'db_user', CURRENT_USER
            ),
            CURRENT_TIMESTAMP
        );
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_auditoria_condiciones ON patient_conditions;

CREATE TRIGGER trg_auditoria_condiciones
AFTER INSERT OR UPDATE ON patient_conditions
FOR EACH ROW
EXECUTE FUNCTION fn_auditoria_condiciones();

-- 3. PROCEDIMIENTOS ALMACENADOS TRANSACCIONALES Y CURSOR (PL/pgSQL)

-- 3.1 Registro atómico y firma de nueva consulta + apertura/vínculo de historia y episodio
DROP PROCEDURE IF EXISTS sp_registrar_y_firmar_consulta_segura(
    BIGINT, BIGINT, BIGINT, TEXT, TEXT, TEXT, TEXT, TEXT, VARCHAR, BIGINT
);
DROP PROCEDURE IF EXISTS sp_registrar_y_firmar_consulta_segura(
    BIGINT, BIGINT, BIGINT, TEXT, TEXT, TEXT, TEXT, TEXT, VARCHAR, BIGINT, BIGINT
);

CREATE PROCEDURE sp_registrar_y_firmar_consulta_segura(
    IN p_patient_id BIGINT,
    IN p_doctor_id BIGINT,
    IN p_appointment_id BIGINT,
    IN p_chief_complaint TEXT,
    IN p_subjective TEXT,
    IN p_objective TEXT,
    IN p_assessment TEXT,
    IN p_plan TEXT,
    IN p_episode_title VARCHAR(180),
    IN p_actor_user_id BIGINT DEFAULT NULL,
    IN p_care_episode_id BIGINT DEFAULT NULL
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_patient_id BIGINT;
    v_doctor_id BIGINT;
    v_appt_patient_id BIGINT;
    v_appt_doctor_id BIGINT;
    v_appt_status VARCHAR(20);
    v_occurred_at TIMESTAMPTZ;
    v_signed_at TIMESTAMPTZ;
    v_consultation_id BIGINT;
    v_clinical_record_id BIGINT;
    v_episode_id BIGINT;
    v_episode_record_id BIGINT;
    v_effective_title VARCHAR(180);
BEGIN
    IF p_actor_user_id IS NOT NULL THEN
        PERFORM set_config('app.current_user_id', p_actor_user_id::text, true);
    END IF;

    IF COALESCE(btrim(p_chief_complaint), '') = '' THEN
        RAISE EXCEPTION 'El motivo de consulta es obligatorio para firmar.';
    END IF;

    -- Bloqueo pesimista del paciente para evitar apertura concurrente duplicada de historia
    SELECT id INTO v_patient_id
    FROM patients
    WHERE id = p_patient_id AND is_active = TRUE
    FOR UPDATE;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'El paciente ID % no existe o está inactivo.', p_patient_id;
    END IF;

    SELECT id INTO v_doctor_id
    FROM doctors
    WHERE id = p_doctor_id AND is_active = TRUE;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'El médico ID % no existe o está inactivo.', p_doctor_id;
    END IF;

    IF p_appointment_id IS NOT NULL THEN
        SELECT patient_id, doctor_id, status
        INTO v_appt_patient_id, v_appt_doctor_id, v_appt_status
        FROM appointments
        WHERE id = p_appointment_id
        FOR UPDATE;

        IF NOT FOUND THEN
            RAISE EXCEPTION 'La cita ID % no existe.', p_appointment_id;
        END IF;

        IF v_appt_patient_id <> p_patient_id OR v_appt_doctor_id <> p_doctor_id THEN
            RAISE EXCEPTION 'La cita ID % no corresponde al paciente y médico seleccionados.', p_appointment_id;
        END IF;

        IF v_appt_status IN ('completed', 'cancelled', 'no_show') THEN
            RAISE EXCEPTION 'No se puede registrar una consulta sobre una cita en estado %.', v_appt_status;
        END IF;

        IF EXISTS (SELECT 1 FROM medical_consultations WHERE appointment_id = p_appointment_id) THEN
            RAISE EXCEPTION 'La cita ID % ya posee una consulta médica asociada.', p_appointment_id;
        END IF;
    END IF;

    v_occurred_at := clock_timestamp();

    INSERT INTO medical_consultations (
        patient_id,
        doctor_id,
        appointment_id,
        occurred_at,
        status,
        chief_complaint,
        subjective,
        objective,
        assessment,
        plan,
        created_at,
        updated_at
    )
    VALUES (
        p_patient_id,
        p_doctor_id,
        p_appointment_id,
        v_occurred_at,
        'draft',
        btrim(p_chief_complaint),
        NULLIF(btrim(COALESCE(p_subjective, '')), ''),
        NULLIF(btrim(COALESCE(p_objective, '')), ''),
        NULLIF(btrim(COALESCE(p_assessment, '')), ''),
        NULLIF(btrim(COALESCE(p_plan, '')), ''),
        v_occurred_at,
        v_occurred_at
    )
    RETURNING id INTO v_consultation_id;

    v_signed_at := clock_timestamp();
    IF v_signed_at < v_occurred_at THEN
        v_signed_at := v_occurred_at;
    END IF;

    UPDATE medical_consultations
    SET status = 'signed',
        signed_at = v_signed_at,
        updated_at = v_signed_at
    WHERE id = v_consultation_id;

    SELECT id INTO v_clinical_record_id
    FROM clinical_records
    WHERE patient_id = p_patient_id
    FOR UPDATE;

    IF NOT FOUND THEN
        INSERT INTO clinical_records (
            patient_id,
            opened_from_consultation_id,
            opened_at,
            created_at,
            updated_at
        )
        VALUES (
            p_patient_id,
            v_consultation_id,
            v_signed_at,
            v_signed_at,
            v_signed_at
        )
        RETURNING id INTO v_clinical_record_id;
    END IF;

    IF p_care_episode_id IS NOT NULL THEN
        SELECT clinical_record_id INTO v_episode_record_id
        FROM care_episodes
        WHERE id = p_care_episode_id
        FOR UPDATE;

        IF NOT FOUND OR v_episode_record_id <> v_clinical_record_id THEN
            RAISE EXCEPTION 'El episodio ID % no existe o no pertenece a la historia clínica del paciente.', p_care_episode_id;
        END IF;

        INSERT INTO episode_consultations (
            episode_id,
            consultation_id,
            relation_type,
            created_at
        )
        VALUES (
            p_care_episode_id,
            v_consultation_id,
            'follow_up',
            v_signed_at
        );
    ELSE
        v_effective_title := COALESCE(NULLIF(btrim(COALESCE(p_episode_title, '')), ''), 'Episodio clínico ambulatorio');

        INSERT INTO care_episodes (
            clinical_record_id,
            opening_consultation_id,
            title,
            status,
            opened_at,
            created_at,
            updated_at
        )
        VALUES (
            v_clinical_record_id,
            v_consultation_id,
            v_effective_title,
            'active',
            v_signed_at,
            v_signed_at,
            v_signed_at
        )
        RETURNING id INTO v_episode_id;

        INSERT INTO episode_consultations (
            episode_id,
            consultation_id,
            relation_type,
            created_at
        )
        VALUES (
            v_episode_id,
            v_consultation_id,
            'initial',
            v_signed_at
        );
    END IF;

    IF p_appointment_id IS NOT NULL THEN
        UPDATE appointments
        SET status = 'completed',
            updated_at = v_signed_at
        WHERE id = p_appointment_id;

        UPDATE follow_up_plans
        SET status = 'completed',
            resulting_consultation_id = v_consultation_id,
            updated_at = v_signed_at
        WHERE appointment_id = p_appointment_id
          AND status IN ('pending', 'scheduled');
    END IF;

    RAISE NOTICE 'Consulta ID % firmada e historia clínica ID % actualizada.',
        v_consultation_id, v_clinical_record_id;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'Error crítico: transacción clínica revertida. Detalle: %', SQLERRM;
END;
$$;

-- 3.2 Firma atómica de una consulta existente en borrador (para flujo SOAP en Filament)
DROP PROCEDURE IF EXISTS sp_firmar_consulta_existente(
    BIGINT, BIGINT, BIGINT, VARCHAR
);

CREATE PROCEDURE sp_firmar_consulta_existente(
    IN p_consultation_id BIGINT,
    IN p_actor_user_id BIGINT DEFAULT NULL,
    IN p_care_episode_id BIGINT DEFAULT NULL,
    IN p_episode_title VARCHAR(180) DEFAULT 'Episodio clínico ambulatorio'
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_patient_id BIGINT;
    v_appointment_id BIGINT;
    v_status VARCHAR(15);
    v_chief_complaint TEXT;
    v_occurred_at TIMESTAMPTZ;
    v_signed_at TIMESTAMPTZ;
    v_clinical_record_id BIGINT;
    v_episode_id BIGINT;
    v_episode_record_id BIGINT;
    v_effective_title VARCHAR(180);
BEGIN
    IF p_actor_user_id IS NOT NULL THEN
        PERFORM set_config('app.current_user_id', p_actor_user_id::text, true);
    END IF;

    SELECT patient_id, appointment_id, status, chief_complaint, occurred_at
    INTO v_patient_id, v_appointment_id, v_status, v_chief_complaint, v_occurred_at
    FROM medical_consultations
    WHERE id = p_consultation_id
    FOR UPDATE;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'La consulta ID % no existe.', p_consultation_id;
    END IF;

    IF v_status = 'signed' THEN
        RAISE EXCEPTION 'La consulta ID % ya se encuentra firmada.', p_consultation_id;
    END IF;

    IF COALESCE(btrim(v_chief_complaint), '') = '' THEN
        RAISE EXCEPTION 'El motivo de consulta es obligatorio antes de firmar.';
    END IF;

    v_signed_at := clock_timestamp();
    IF v_signed_at < v_occurred_at THEN
        v_signed_at := v_occurred_at;
    END IF;

    UPDATE medical_consultations
    SET status = 'signed',
        signed_at = v_signed_at,
        updated_at = v_signed_at
    WHERE id = p_consultation_id;

    SELECT id INTO v_clinical_record_id
    FROM clinical_records
    WHERE patient_id = v_patient_id
    FOR UPDATE;

    IF NOT FOUND THEN
        INSERT INTO clinical_records (
            patient_id,
            opened_from_consultation_id,
            opened_at,
            created_at,
            updated_at
        )
        VALUES (
            v_patient_id,
            p_consultation_id,
            v_signed_at,
            v_signed_at,
            v_signed_at
        )
        RETURNING id INTO v_clinical_record_id;
    END IF;

    IF p_care_episode_id IS NOT NULL THEN
        SELECT clinical_record_id INTO v_episode_record_id
        FROM care_episodes
        WHERE id = p_care_episode_id
        FOR UPDATE;

        IF NOT FOUND OR v_episode_record_id <> v_clinical_record_id THEN
            RAISE EXCEPTION 'El episodio ID % no pertenece a la historia clínica del paciente.', p_care_episode_id;
        END IF;

        INSERT INTO episode_consultations (
            episode_id,
            consultation_id,
            relation_type,
            created_at
        )
        VALUES (
            p_care_episode_id,
            p_consultation_id,
            'follow_up',
            v_signed_at
        );
    ELSE
        v_effective_title := COALESCE(NULLIF(btrim(COALESCE(p_episode_title, '')), ''), 'Episodio clínico ambulatorio');

        INSERT INTO care_episodes (
            clinical_record_id,
            opening_consultation_id,
            title,
            status,
            opened_at,
            created_at,
            updated_at
        )
        VALUES (
            v_clinical_record_id,
            p_consultation_id,
            v_effective_title,
            'active',
            v_signed_at,
            v_signed_at,
            v_signed_at
        )
        RETURNING id INTO v_episode_id;

        INSERT INTO episode_consultations (
            episode_id,
            consultation_id,
            relation_type,
            created_at
        )
        VALUES (
            v_episode_id,
            p_consultation_id,
            'initial',
            v_signed_at
        );
    END IF;

    IF v_appointment_id IS NOT NULL THEN
        UPDATE appointments
        SET status = 'completed',
            updated_at = v_signed_at
        WHERE id = v_appointment_id;

        UPDATE follow_up_plans
        SET status = 'completed',
            resulting_consultation_id = p_consultation_id,
            updated_at = v_signed_at
        WHERE appointment_id = v_appointment_id
          AND status IN ('pending', 'scheduled');
    END IF;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'Error crítico: firma de consulta revertida. Detalle: %', SQLERRM;
END;
$$;

-- 3.3 Sustitución segura de receta médica emitida (sin borrar la receta original)
DROP PROCEDURE IF EXISTS sp_sustituir_receta_segura(
    BIGINT, BIGINT, TEXT, OUT BIGINT
);

CREATE PROCEDURE sp_sustituir_receta_segura(
    IN p_old_prescription_id BIGINT,
    IN p_actor_user_id BIGINT,
    IN p_notes TEXT,
    OUT p_new_prescription_id BIGINT
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_consultation_id BIGINT;
    v_old_status VARCHAR(20);
BEGIN
    IF p_actor_user_id IS NOT NULL THEN
        PERFORM set_config('app.current_user_id', p_actor_user_id::text, true);
    END IF;

    SELECT consultation_id, status
    INTO v_consultation_id, v_old_status
    FROM prescriptions
    WHERE id = p_old_prescription_id
    FOR UPDATE;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'La receta ID % no existe.', p_old_prescription_id;
    END IF;

    IF v_old_status <> 'issued' THEN
        RAISE EXCEPTION 'Solo se puede sustituir una receta en estado issued (actual: %).', v_old_status;
    END IF;

    UPDATE prescriptions
    SET status = 'replaced'
    WHERE id = p_old_prescription_id;

    INSERT INTO prescriptions (
        consultation_id,
        supersedes_prescription_id,
        status,
        issued_at,
        notes,
        created_at
    )
    VALUES (
        v_consultation_id,
        p_old_prescription_id,
        'draft',
        NULL,
        NULLIF(btrim(COALESCE(p_notes, '')), ''),
        clock_timestamp()
    )
    RETURNING id INTO p_new_prescription_id;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'Error crítico: sustitución de receta revertida. Detalle: %', SQLERRM;
END;
$$;

-- 3.4 Reprogramación segura de cita con bloqueo FOR UPDATE y detección de solapamiento
DROP PROCEDURE IF EXISTS sp_reprogramar_cita_segura(
    BIGINT, BIGINT, TIMESTAMPTZ, TIMESTAMPTZ, TEXT
);

CREATE PROCEDURE sp_reprogramar_cita_segura(
    IN p_appointment_id BIGINT,
    IN p_changed_by_user_id BIGINT,
    IN p_new_start TIMESTAMPTZ,
    IN p_new_end TIMESTAMPTZ,
    IN p_reason TEXT
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_prev_start TIMESTAMPTZ;
    v_prev_end TIMESTAMPTZ;
    v_status VARCHAR(20);
BEGIN
    IF p_changed_by_user_id IS NOT NULL THEN
        PERFORM set_config('app.current_user_id', p_changed_by_user_id::text, true);
    END IF;

    IF p_new_end <= p_new_start THEN
        RAISE EXCEPTION 'La fecha de fin debe ser posterior a la fecha de inicio.';
    END IF;

    IF COALESCE(btrim(p_reason), '') = '' THEN
        RAISE EXCEPTION 'El motivo de reprogramación es obligatorio.';
    END IF;

    SELECT starts_at, ends_at, status
    INTO v_prev_start, v_prev_end, v_status
    FROM appointments
    WHERE id = p_appointment_id
    FOR UPDATE;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'La cita ID % no existe.', p_appointment_id;
    END IF;

    IF v_status IN ('completed', 'cancelled', 'no_show') THEN
        RAISE EXCEPTION 'No se puede reprogramar una cita en estado %.', v_status;
    END IF;

    IF v_prev_start = p_new_start AND v_prev_end = p_new_end THEN
        RAISE EXCEPTION 'El nuevo horario debe ser distinto al horario anterior.';
    END IF;

    INSERT INTO appointment_reschedules (
        appointment_id,
        changed_by_user_id,
        previous_start,
        previous_end,
        new_start,
        new_end,
        reason,
        created_at
    )
    VALUES (
        p_appointment_id,
        p_changed_by_user_id,
        v_prev_start,
        v_prev_end,
        p_new_start,
        p_new_end,
        btrim(p_reason),
        clock_timestamp()
    );

    UPDATE appointments
    SET starts_at = p_new_start,
        ends_at = p_new_end,
        updated_at = clock_timestamp()
    WHERE id = p_appointment_id;

    RAISE NOTICE 'Cita ID % reprogramada con éxito.', p_appointment_id;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'Error crítico: reprogramación revertida. Detalle: %', SQLERRM;
END;
$$;

-- 3.5 Resumen ejecutivo mediante CURSOR explícito (réplica de sp_valorizar_inventario)
DROP PROCEDURE IF EXISTS sp_valorizar_actividad_clinica();
DROP PROCEDURE IF EXISTS sp_valorizar_actividad_clinica(OUT INT, OUT INT, OUT NUMERIC);

CREATE OR REPLACE PROCEDURE sp_valorizar_actividad_clinica(
    OUT p_total_consultas_firmadas INT,
    OUT p_total_citas_activas INT,
    OUT p_total_horas_agenda NUMERIC(10,2)
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_status VARCHAR(20);
    v_horas NUMERIC(10,4);
    cur_agenda CURSOR FOR
        SELECT status, EXTRACT(EPOCH FROM (ends_at - starts_at)) / 3600.0
        FROM appointments;
BEGIN
    p_total_citas_activas := 0;
    p_total_horas_agenda := 0.00;

    SELECT COUNT(*)::INT
    INTO p_total_consultas_firmadas
    FROM medical_consultations
    WHERE status = 'signed';

    OPEN cur_agenda;
    LOOP
        FETCH cur_agenda INTO v_status, v_horas;
        EXIT WHEN NOT FOUND;

        IF v_status IN ('pending', 'confirmed', 'checked_in') THEN
            p_total_citas_activas := p_total_citas_activas + 1;
        END IF;

        IF v_status <> 'cancelled' THEN
            p_total_horas_agenda := p_total_horas_agenda + COALESCE(v_horas, 0);
        END IF;
    END LOOP;
    CLOSE cur_agenda;

    p_total_horas_agenda := ROUND(p_total_horas_agenda, 2);
END;
$$;

-- 4. VISTAS PÚBLICAS / CONSOLIDADAS (mínimo privilegio para capa web)

CREATE OR REPLACE VIEW vw_catalogo_medicos_publico AS
SELECT
    d.id AS doctor_id,
    d.professional_license,
    d.first_names || ' ' || d.last_names AS nombre_completo,
    COALESCE(d.specialty, 'Medicina general') AS specialty,
    d.is_active,
    COUNT(DISTINCT a.id)::INT AS total_citas,
    COUNT(DISTINCT CASE WHEN mc.status = 'signed' THEN mc.id END)::INT AS consultas_firmadas
FROM doctors d
LEFT JOIN appointments a ON a.doctor_id = d.id
LEFT JOIN medical_consultations mc ON mc.doctor_id = d.id
GROUP BY d.id, d.professional_license, d.first_names, d.last_names, d.specialty, d.is_active;

CREATE OR REPLACE VIEW vw_agenda_consultas_detalle AS
SELECT
    a.id AS appointment_id,
    'CITA-' || lpad(a.id::text, 4, '0') AS codigo_cita,
    p.id AS patient_id,
    p.first_names || ' ' || p.last_names AS paciente,
    COALESCE(p.document_type || '-' || p.document_number, 'Sin documento') AS documento_paciente,
    d.id AS doctor_id,
    d.first_names || ' ' || d.last_names AS medico,
    COALESCE(d.specialty, 'Medicina general') AS especialidad,
    a.starts_at,
    a.ends_at,
    a.status AS estado_cita,
    mc.id AS consultation_id,
    COALESCE(mc.status, 'sin_consulta') AS estado_consulta,
    mc.signed_at,
    (cr.id IS NOT NULL) AS tiene_expediente
FROM appointments a
INNER JOIN patients p ON p.id = a.patient_id
INNER JOIN doctors d ON d.id = a.doctor_id
LEFT JOIN medical_consultations mc ON mc.appointment_id = a.id
LEFT JOIN clinical_records cr ON cr.patient_id = p.id;

CREATE OR REPLACE VIEW vw_expedientes_episodios_resumen AS
SELECT
    cr.id AS clinical_record_id,
    'HC-' || lpad(cr.id::text, 4, '0') AS codigo_historia,
    p.id AS patient_id,
    p.first_names || ' ' || p.last_names AS paciente,
    COALESCE(p.document_type || '-' || p.document_number, 'Sin documento') AS documento_paciente,
    cr.opened_at,
    mc.id AS consultation_apertura_id,
    mc.status AS estado_consulta_apertura,
    COUNT(DISTINCT ce.id)::INT AS total_episodios,
    COUNT(DISTINCT fup.id)::INT AS seguimientos_pendientes
FROM clinical_records cr
INNER JOIN patients p ON p.id = cr.patient_id
INNER JOIN medical_consultations mc ON mc.id = cr.opened_from_consultation_id
LEFT JOIN care_episodes ce ON ce.clinical_record_id = cr.id
LEFT JOIN follow_up_plans fup ON fup.episode_id = ce.id AND fup.status = 'pending'
GROUP BY cr.id, p.id, p.first_names, p.last_names, p.document_type, p.document_number, cr.opened_at, mc.id, mc.status;

-- 5. SEGURIDAD DCL: ROL Y USUARIO RESTRINGIDO PARA VISTAS PÚBLICAS
DO $$
DECLARE
    v_has_assistant_user BOOLEAN := FALSE;
BEGIN
    BEGIN
        IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'rol_asistente_clinica') THEN
            CREATE ROLE rol_asistente_clinica NOLOGIN;
        END IF;

        IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'asistente_clinica_web') THEN
            CREATE USER asistente_clinica_web WITH PASSWORD 'ClinicaSecure2026!';
        ELSE
            ALTER USER asistente_clinica_web WITH PASSWORD 'ClinicaSecure2026!';
        END IF;

        EXECUTE format('GRANT CONNECT ON DATABASE %I TO rol_asistente_clinica', current_database());
        EXECUTE format('GRANT CONNECT ON DATABASE %I TO asistente_clinica_web', current_database());

        GRANT USAGE ON SCHEMA public TO rol_asistente_clinica;
        GRANT SELECT ON TABLE public.vw_catalogo_medicos_publico TO rol_asistente_clinica;
        GRANT SELECT ON TABLE public.vw_agenda_consultas_detalle TO rol_asistente_clinica;
        GRANT SELECT ON TABLE public.vw_expedientes_episodios_resumen TO rol_asistente_clinica;

        REVOKE ALL ON TABLE public.audit_events FROM PUBLIC, rol_asistente_clinica, asistente_clinica_web;
        REVOKE ALL ON TABLE public.users FROM PUBLIC, rol_asistente_clinica, asistente_clinica_web;
        REVOKE ALL ON TABLE public.clinical_records, public.consultation_amendments, public.prescriptions, public.patient_allergies, public.clinical_documents
            FROM PUBLIC, rol_asistente_clinica, asistente_clinica_web;

        GRANT rol_asistente_clinica TO asistente_clinica_web WITH INHERIT TRUE;
    EXCEPTION
        WHEN insufficient_privilege THEN
            RAISE NOTICE 'Aviso DCL: El entorno PostgreSQL administrado no concede CREATEROLE al usuario actual. Se conservan vistas públicas y se omite creación de rol adicional.';
    END;
END
$$;

-- Prueba de lectura de cursor
CALL sp_valorizar_actividad_clinica(NULL, NULL, NULL);
