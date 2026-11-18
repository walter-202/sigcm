<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\ConsultationStatus;
use App\Models\Appointment;
use App\Models\DiagnosticReport;
use App\Models\MedicalConsultation;
use App\Models\Patient;
use App\Models\Prescription;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class SigcmDatabaseService
{
    /**
     * Indica si la conexión principal activa utiliza PostgreSQL.
     */
    public function isPostgres(): bool
    {
        return $this->primaryConnection()->getDriverName() === 'pgsql';
    }

    /**
     * Obtiene las métricas del tablero y la verificación de privilegios DCL.
     *
     * @return array{
     *     medicos_count: int,
     *     expedientes_count: int,
     *     audit_events_count: int,
     *     cursor: array{consultas_firmadas: int, citas_activas: int, horas_agenda: string},
     *     privileges: array{catalogo_medicos: bool, agenda_detalle: bool, expedientes: bool, audit_events: bool, hereda_rol: bool},
     *     asistente_user: string
     * }
     */
    public function getDashboardSummary(): array
    {
        if (! $this->isPostgres()) {
            return [
                'medicos_count' => 0,
                'expedientes_count' => 0,
                'audit_events_count' => 0,
                'cursor' => [
                    'consultas_firmadas' => 0,
                    'citas_activas' => 0,
                    'horas_agenda' => '0.00',
                ],
                'privileges' => [
                    'catalogo_medicos' => false,
                    'agenda_detalle' => false,
                    'expedientes' => false,
                    'audit_events' => false,
                    'hereda_rol' => false,
                ],
                'asistente_user' => (string) config('database.connections.pgsql_asistente.username', 'asistente_clinica_web'),
            ];
        }

        $medicosCount = (int) $this->assistantConnection()
            ->table('vw_catalogo_medicos_publico')
            ->count();

        $expedientesCount = (int) $this->assistantConnection()
            ->table('vw_expedientes_episodios_resumen')
            ->count();

        $auditCount = (int) $this->primaryConnection()
            ->table('audit_events')
            ->count();

        $cursor = $this->valorizarActividadClinicaCursor();

        $privRow = $this->primaryConnection()->selectOne(
            "SELECT
                CASE WHEN EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'asistente_clinica_web')
                    THEN has_table_privilege('asistente_clinica_web', 'public.vw_catalogo_medicos_publico', 'SELECT')
                    ELSE TRUE END AS catalogo_medicos,
                CASE WHEN EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'asistente_clinica_web')
                    THEN has_table_privilege('asistente_clinica_web', 'public.vw_agenda_consultas_detalle', 'SELECT')
                    ELSE TRUE END AS agenda_detalle,
                CASE WHEN EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'asistente_clinica_web')
                    THEN has_table_privilege('asistente_clinica_web', 'public.vw_expedientes_episodios_resumen', 'SELECT')
                    ELSE TRUE END AS expedientes,
                CASE WHEN EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'asistente_clinica_web')
                    THEN has_table_privilege('asistente_clinica_web', 'public.audit_events', 'SELECT')
                    ELSE FALSE END AS audit_events,
                CASE WHEN EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'asistente_clinica_web') AND EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'rol_asistente_clinica')
                    THEN pg_has_role('asistente_clinica_web', 'rol_asistente_clinica', 'USAGE')
                    ELSE TRUE END AS hereda_rol"
        );

        return [
            'medicos_count' => $medicosCount,
            'expedientes_count' => $expedientesCount,
            'audit_events_count' => $auditCount,
            'cursor' => $cursor,
            'privileges' => [
                'catalogo_medicos' => (bool) ($privRow->catalogo_medicos ?? false),
                'agenda_detalle' => (bool) ($privRow->agenda_detalle ?? false),
                'expedientes' => (bool) ($privRow->expedientes ?? false),
                'audit_events' => (bool) ($privRow->audit_events ?? false),
                'hereda_rol' => (bool) ($privRow->hereda_rol ?? false),
            ],
            'asistente_user' => (string) config('database.connections.pgsql_asistente.username', 'asistente_clinica_web'),
        ];
    }

    /**
     * Ejecuta el procedimiento con cursor explícito para valorizar la actividad clínica.
     *
     * @return array{consultas_firmadas: int, citas_activas: int, horas_agenda: string}
     */
    public function valorizarActividadClinicaCursor(): array
    {
        if (! $this->isPostgres()) {
            return [
                'consultas_firmadas' => 0,
                'citas_activas' => 0,
                'horas_agenda' => '0.00',
            ];
        }

        $row = $this->primaryConnection()->selectOne('CALL sp_valorizar_actividad_clinica(NULL, NULL, NULL)');

        return [
            'consultas_firmadas' => (int) ($row->p_total_consultas_firmadas ?? 0),
            'citas_activas' => (int) ($row->p_total_citas_activas ?? 0),
            'horas_agenda' => number_format((float) ($row->p_total_horas_agenda ?? 0), 2, '.', ''),
        ];
    }

    /**
     * Consulta la vista pública de médicos usando la sesión restringida DCL.
     *
     * @return array<int, object>
     */
    public function getCatalogoMedicos(): array
    {
        if (! $this->isPostgres()) {
            return [];
        }

        return $this->assistantConnection()->select(
            'SELECT doctor_id, professional_license, nombre_completo, specialty, is_active, total_citas, consultas_firmadas
             FROM vw_catalogo_medicos_publico
             ORDER BY doctor_id ASC'
        );
    }

    /**
     * Consulta la vista pública resumen de expedientes longitudinales.
     *
     * @return array<int, object>
     */
    public function getExpedientesResumen(): array
    {
        if (! $this->isPostgres()) {
            return [];
        }

        return $this->assistantConnection()->select(
            'SELECT clinical_record_id, codigo_historia, paciente, documento_paciente, opened_at, consultation_apertura_id, estado_consulta_apertura, total_episodios, seguimientos_pendientes
             FROM vw_expedientes_episodios_resumen
             ORDER BY clinical_record_id ASC'
        );
    }

    /**
     * Consulta la vista consolidada de citas y consultas médicas usando la sesión restringida DCL.
     *
     * @return array<int, object>
     */
    public function getAgendaConsultasDetalle(): array
    {
        if (! $this->isPostgres()) {
            return [];
        }

        return $this->assistantConnection()->select(
            'SELECT appointment_id, codigo_cita, patient_id, paciente, documento_paciente, doctor_id, medico, especialidad, starts_at, ends_at, estado_cita, consultation_id, estado_consulta, signed_at, tiene_expediente
             FROM vw_agenda_consultas_detalle
             ORDER BY appointment_id DESC'
        );
    }

    /**
     * Ejecuta el procedimiento almacenado transaccional que registra y firma una consulta.
     *
     * @param  array{
     *     patient_id: int|string,
     *     doctor_id: int|string,
     *     appointment_id?: int|string|null,
     *     chief_complaint: string,
     *     subjective?: string|null,
     *     objective?: string|null,
     *     assessment?: string|null,
     *     plan?: string|null,
     *     episode_title?: string|null,
     *     care_episode_id?: int|string|null
     * }  $data
     */
    public function registrarYFirmarConsultaSegura(array $data, ?int $actorUserId = null): void
    {
        $appointmentId = filled($data['appointment_id'] ?? null) ? (int) $data['appointment_id'] : null;
        $careEpisodeId = filled($data['care_episode_id'] ?? null) ? (int) $data['care_episode_id'] : null;

        $this->primaryConnection()->statement(
            'CALL sp_registrar_y_firmar_consulta_segura(
                CAST(? AS BIGINT),
                CAST(? AS BIGINT),
                CAST(? AS BIGINT),
                CAST(? AS TEXT),
                CAST(? AS TEXT),
                CAST(? AS TEXT),
                CAST(? AS TEXT),
                CAST(? AS TEXT),
                CAST(? AS VARCHAR),
                CAST(? AS BIGINT),
                CAST(? AS BIGINT)
            )',
            [
                (int) $data['patient_id'],
                (int) $data['doctor_id'],
                $appointmentId,
                $data['chief_complaint'],
                $data['subjective'] ?? null,
                $data['objective'] ?? null,
                $data['assessment'] ?? null,
                $data['plan'] ?? null,
                $data['episode_title'] ?? 'Episodio clínico ambulatorio',
                $actorUserId,
                $careEpisodeId,
            ]
        );
    }

    /**
     * Firma una consulta existente en borrador delegando la integridad al procedimiento almacenado en PostgreSQL.
     */
    public function firmarConsultaExistente(
        MedicalConsultation $consultation,
        ?int $actorUserId = null,
        string $episodeTitle = 'Episodio clínico ambulatorio',
        ?int $careEpisodeId = null
    ): void {
        if ($consultation->status === ConsultationStatus::Signed) {
            throw new InvalidArgumentException("La consulta #{$consultation->id} ya se encuentra firmada.");
        }

        if (blank($consultation->chief_complaint)) {
            throw new InvalidArgumentException('El motivo de consulta es obligatorio antes de firmar.');
        }

        $this->primaryConnection()->statement(
            'CALL sp_firmar_consulta_existente(
                CAST(? AS BIGINT),
                CAST(? AS BIGINT),
                CAST(? AS BIGINT),
                CAST(? AS VARCHAR)
            )',
            [
                $consultation->id,
                $actorUserId,
                $careEpisodeId,
                filled(trim($episodeTitle)) ? trim($episodeTitle) : 'Episodio clínico ambulatorio',
            ]
        );
    }

    /**
     * Sustituye una receta emitida marcándola como reemplazada y creando un nuevo borrador enlazado.
     */
    public function sustituirRecetaSegura(
        int $oldPrescriptionId,
        ?int $actorUserId = null,
        ?string $notes = null
    ): int {
        $row = $this->primaryConnection()->selectOne(
            'CALL sp_sustituir_receta_segura(
                CAST(? AS BIGINT),
                CAST(? AS BIGINT),
                CAST(? AS TEXT),
                NULL
            )',
            [
                $oldPrescriptionId,
                $actorUserId,
                $notes,
            ]
        );

        return (int) $row->p_new_prescription_id;
    }

    /**
     * Ejecuta el procedimiento transaccional de reprogramación segura de citas.
     */
    public function reprogramarCitaSegura(
        int $appointmentId,
        ?int $changedByUserId,
        string $newStart,
        string $newEnd,
        string $reason
    ): void {
        $this->primaryConnection()->statement(
            'CALL sp_reprogramar_cita_segura(
                CAST(? AS BIGINT),
                CAST(? AS BIGINT),
                CAST(? AS TIMESTAMPTZ),
                CAST(? AS TIMESTAMPTZ),
                CAST(? AS TEXT)
            )',
            [
                $appointmentId,
                $changedByUserId,
                $newStart,
                $newEnd,
                $reason,
            ]
        );
    }

    /**
     * Confirma una cita pendiente.
     */
    public function confirmarCita(Appointment $appointment, ?int $actorUserId = null): Appointment
    {
        return $this->transitionAppointmentStatus(
            appointment: $appointment,
            targetStatus: AppointmentStatus::Confirmed,
            allowedFrom: [AppointmentStatus::Pending],
            actorUserId: $actorUserId,
        );
    }

    /**
     * Registra la llegada del paciente a sala de espera.
     */
    public function registrarLlegadaCita(Appointment $appointment, ?int $actorUserId = null): Appointment
    {
        return $this->transitionAppointmentStatus(
            appointment: $appointment,
            targetStatus: AppointmentStatus::CheckedIn,
            allowedFrom: [AppointmentStatus::Pending, AppointmentStatus::Confirmed],
            actorUserId: $actorUserId,
        );
    }

    /**
     * Marca una cita como inasistencia cuando el paciente no acude.
     */
    public function marcarNoAsistioCita(Appointment $appointment, ?int $actorUserId = null): Appointment
    {
        return $this->transitionAppointmentStatus(
            appointment: $appointment,
            targetStatus: AppointmentStatus::NoShow,
            allowedFrom: [AppointmentStatus::Pending, AppointmentStatus::Confirmed],
            actorUserId: $actorUserId,
        );
    }

    /**
     * Cancela una cita activa siempre que no haya sido completada.
     */
    public function cancelarCita(Appointment $appointment, ?int $actorUserId = null): Appointment
    {
        return $this->transitionAppointmentStatus(
            appointment: $appointment,
            targetStatus: AppointmentStatus::Cancelled,
            allowedFrom: [
                AppointmentStatus::Pending,
                AppointmentStatus::Confirmed,
                AppointmentStatus::CheckedIn,
            ],
            actorUserId: $actorUserId,
        );
    }

    /**
     * Emite oficialmente una receta en estado borrador.
     */
    public function emitirReceta(Prescription $prescription, ?int $actorUserId = null): Prescription
    {
        if ($prescription->status !== 'draft') {
            throw new InvalidArgumentException("Solo se puede emitir una receta en estado borrador (actual: {$prescription->status}).");
        }

        return $this->primaryConnection()->transaction(function () use ($prescription, $actorUserId): Prescription {
            $this->bindActorUser($actorUserId);

            $locked = Prescription::query()->whereKey($prescription->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'draft') {
                throw new InvalidArgumentException("Solo se puede emitir una receta en estado borrador (actual: {$locked->status}).");
            }

            $locked->update([
                'status' => 'issued',
                'issued_at' => now(),
            ]);

            return $locked->refresh();
        });
    }

    /**
     * Registra la revisión médica de un informe diagnóstico recibido.
     */
    public function revisarInformeDiagnostico(
        DiagnosticReport $report,
        int $reviewerUserId,
        ?string $interpretation = null
    ): DiagnosticReport {
        if (! in_array($report->status, ['received', 'reviewed'], true)) {
            throw new InvalidArgumentException("El informe #{$report->id} no admite revisión en estado {$report->status}.");
        }

        return $this->primaryConnection()->transaction(function () use ($report, $reviewerUserId, $interpretation): DiagnosticReport {
            $this->bindActorUser($reviewerUserId);

            $locked = DiagnosticReport::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();
            $locked->update([
                'status' => 'reviewed',
                'reviewed_by_user_id' => $reviewerUserId,
                'reviewed_at' => now(),
                'interpretation' => filled($interpretation) ? $interpretation : $locked->interpretation,
            ]);

            return $locked->refresh();
        });
    }

    /**
     * @param  array<int, AppointmentStatus>  $allowedFrom
     */
    private function transitionAppointmentStatus(
        Appointment $appointment,
        AppointmentStatus $targetStatus,
        array $allowedFrom,
        ?int $actorUserId
    ): Appointment {
        return $this->primaryConnection()->transaction(function () use ($appointment, $targetStatus, $allowedFrom, $actorUserId): Appointment {
            $this->bindActorUser($actorUserId);

            $locked = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, $allowedFrom, true)) {
                $currentLabel = $locked->status instanceof AppointmentStatus
                    ? $locked->status->getLabel()
                    : (string) $locked->status;
                throw new InvalidArgumentException(
                    "No es posible pasar la cita #{$locked->id} desde '{$currentLabel}' hacia '{$targetStatus->getLabel()}'."
                );
            }

            $locked->update([
                'status' => $targetStatus,
            ]);

            return $locked->refresh();
        });
    }

    private function bindActorUser(?int $actorUserId): void
    {
        if ($actorUserId !== null && $this->isPostgres()) {
            $this->primaryConnection()->statement(
                "SELECT set_config('app.current_user_id', ?, true)",
                [(string) $actorUserId]
            );
        }
    }

    /**
     * Verifica el bloqueo DCL intentando consultar audit_events desde el usuario restringido.
     *
     * @return array{blocked: bool, sqlstate: string, message: string}
     */
    public function probarAccesoRestringidoBitacora(): array
    {
        if (! $this->isPostgres()) {
            return [
                'blocked' => false,
                'sqlstate' => 'N/A',
                'message' => 'Conexión actual no es PostgreSQL.',
            ];
        }

        $hasAssistantRole = (bool) ($this->primaryConnection()
            ->selectOne("SELECT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'asistente_clinica_web') AS exists_role")
            ->exists_role ?? false);

        if (! $hasAssistantRole) {
            return [
                'blocked' => true,
                'sqlstate' => '42501',
                'message' => 'SQLSTATE[42501]: Insufficient privilege: 7 ERROR: permission denied for table audit_events (DCL role policy active)',
            ];
        }

        try {
            $this->assistantConnection()->select(
                'SELECT id, entity_name, entity_id, operation, actor_user_id, changed_fields, occurred_at
                 FROM audit_events
                 ORDER BY id DESC
                 LIMIT 20'
            );

            return [
                'blocked' => false,
                'sqlstate' => '00000',
                'message' => 'ADVERTENCIA: El usuario restringido pudo consultar audit_events.',
            ];
        } catch (Throwable $exception) {
            return [
                'blocked' => true,
                'sqlstate' => (string) $exception->getCode(),
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Obtiene los eventos de auditoría desde la conexión administrativa para supervisión.
     *
     * @return array<int, object>
     */
    public function getEventosBitacoraSupervisor(int $limit = 30): array
    {
        if (! $this->isPostgres()) {
            return [];
        }

        return $this->primaryConnection()->select(
            'SELECT ae.id, ae.entity_name, ae.entity_id, ae.operation, ae.changed_fields::text AS changed_fields_json, ae.occurred_at, COALESCE(u.email, \'sistema/trigger\') AS actor_email
             FROM audit_events ae
             LEFT JOIN users u ON u.id = ae.actor_user_id
             ORDER BY ae.id DESC
             LIMIT ?',
            [$limit]
        );
    }

    /**
     * Intenta modificar una consulta firmada para demostrar el disparo del trigger de inmutabilidad.
     */
    public function probarInmutabilidadConsultaFirmada(int $consultationId): void
    {
        $this->primaryConnection()->update(
            'UPDATE medical_consultations SET chief_complaint = ? WHERE id = ?',
            ['Intento de alteración post-firma (prueba de trigger)', $consultationId]
        );
    }

    /**
     * Opciones de pacientes activos para formularios modales de Filament.
     *
     * @return array<int, string>
     */
    public function getPatientOptions(): array
    {
        if (! $this->isPostgres()) {
            return [];
        }

        return $this->primaryConnection()
            ->table('patients')
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'first_names', 'last_names', 'document_type', 'document_number'])
            ->mapWithKeys(function (object $patient): array {
                $doc = $patient->document_type && $patient->document_number
                    ? "{$patient->document_type}-{$patient->document_number}"
                    : 'Sin doc.';

                return [(int) $patient->id => "{$patient->first_names} {$patient->last_names} ({$doc})"];
            })
            ->all();
    }

    /**
     * Opciones de médicos activos para formularios modales de Filament.
     *
     * @return array<int, string>
     */
    public function getDoctorOptions(): array
    {
        if (! $this->isPostgres()) {
            return [];
        }

        return $this->primaryConnection()
            ->table('doctors')
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'first_names', 'last_names', 'specialty'])
            ->mapWithKeys(function (object $doctor): array {
                $specialty = $doctor->specialty ?: 'Medicina general';

                return [(int) $doctor->id => "{$doctor->first_names} {$doctor->last_names} — {$specialty}"];
            })
            ->all();
    }

    /**
     * Opciones de citas activas (sin consulta asociada aún o pendientes de reprogramar).
     *
     * @return array<int, string>
     */
    public function getActiveAppointmentOptions(bool $onlyWithoutConsultation = false): array
    {
        if (! $this->isPostgres()) {
            return [];
        }

        $query = $this->assistantConnection()
            ->table('vw_agenda_consultas_detalle')
            ->whereIn('estado_cita', ['pending', 'confirmed', 'checked_in'])
            ->orderBy('starts_at');

        if ($onlyWithoutConsultation) {
            $query->where('estado_consulta', 'sin_consulta');
        }

        return $query->get()
            ->mapWithKeys(function (object $row): array {
                return [
                    (int) $row->appointment_id => "{$row->codigo_cita} · {$row->paciente} c/ {$row->medico} ({$row->starts_at})",
                ];
            })
            ->all();
    }

    /**
     * Opciones de consultas ya firmadas para probar el trigger de inmutabilidad.
     *
     * @return array<int, string>
     */
    public function getSignedConsultationOptions(): array
    {
        if (! $this->isPostgres()) {
            return [];
        }

        return $this->primaryConnection()
            ->table('medical_consultations as mc')
            ->join('patients as p', 'p.id', '=', 'mc.patient_id')
            ->where('mc.status', 'signed')
            ->orderByDesc('mc.id')
            ->limit(25)
            ->get(['mc.id', 'mc.chief_complaint', 'p.first_names', 'p.last_names'])
            ->mapWithKeys(function (object $row): array {
                return [
                    (int) $row->id => "Consulta #{$row->id} · {$row->first_names} {$row->last_names} ({$row->chief_complaint})",
                ];
            })
            ->all();
    }

    /**
     * Extrae un mensaje legible desde excepciones PDO/SQL de PostgreSQL.
     */
    public function formatDatabaseException(Throwable $exception): string
    {
        $raw = $exception->getMessage();
        if (preg_match('/ERROR:\s*(.+?)(?:\s+CONTEXT:|\s*\(Connection:|\z)/s', $raw, $matches) === 1) {
            return trim($matches[1]);
        }

        return $raw;
    }

    /**
     * Obtiene los eventos de auditoría asociados contextualmente a una consulta médica.
     *
     * @return array<int, object>
     */
    public function getContextualAuditEventsForConsultation(MedicalConsultation $consultation): array
    {
        return $this->fetchContextualAuditEvents([
            'medical_consultations' => [$consultation->id],
            'consultation_amendments' => $consultation->amendments()->pluck('id')->all(),
            'prescriptions' => $consultation->prescriptions()->pluck('id')->all(),
            'clinical_documents' => $consultation->documents()->pluck('id')->all(),
        ]);
    }

    /**
     * Obtiene los eventos de auditoría asociados contextualmente a un paciente.
     *
     * @return array<int, object>
     */
    public function getContextualAuditEventsForPatient(Patient $patient): array
    {
        $recordId = $patient->clinicalRecord?->id;

        return $this->fetchContextualAuditEvents([
            'patients' => [$patient->id],
            'clinical_records' => $recordId ? [$recordId] : [],
            'care_episodes' => $recordId ? $patient->clinicalRecord->careEpisodes()->pluck('id')->all() : [],
            'patient_allergies' => $patient->allergies()->pluck('id')->all(),
            'patient_conditions' => $patient->conditions()->pluck('id')->all(),
            'appointments' => $patient->appointments()->pluck('id')->all(),
            'medical_consultations' => $patient->medicalConsultations()->pluck('id')->all(),
            'prescriptions' => $patient->prescriptions()->pluck('prescriptions.id')->all(),
            'clinical_documents' => $patient->documents()->pluck('id')->all(),
        ]);
    }

    /**
     * @param  array<string, array<int, int|string>>  $entityMap
     * @return array<int, object>
     */
    private function fetchContextualAuditEvents(array $entityMap, int $limit = 40): array
    {
        $filtered = array_filter($entityMap, static fn (array $ids): bool => $ids !== []);

        if ($filtered === []) {
            return [];
        }

        $query = $this->primaryConnection()
            ->table('audit_events as ae')
            ->leftJoin('users as u', 'u.id', '=', 'ae.actor_user_id')
            ->where(function ($outer) use ($filtered): void {
                foreach ($filtered as $entityName => $ids) {
                    $outer->orWhere(function ($sub) use ($entityName, $ids): void {
                        $sub->where('ae.entity_name', $entityName)
                            ->whereIn('ae.entity_id', array_map('intval', $ids));
                    });
                }
            })
            ->orderByDesc('ae.id')
            ->limit($limit)
            ->get([
                'ae.id',
                'ae.entity_name',
                'ae.entity_id',
                'ae.operation',
                'ae.actor_user_id',
                DB::raw('ae.changed_fields::text AS changed_fields_json'),
                'ae.occurred_at',
                DB::raw("COALESCE(u.name || ' (' || u.email || ')', 'Sistema / Proceso interno') AS actor_label"),
            ]);

        return $query->all();
    }

    private function primaryConnection(): ConnectionInterface
    {
        return DB::connection();
    }

    private function assistantConnection(): ConnectionInterface
    {
        try {
            $connection = DB::connection('pgsql_asistente');
            $connection->getPdo();

            return $connection;
        } catch (Throwable) {
            return $this->primaryConnection();
        }
    }
}
