<?php

namespace App\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
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
                has_table_privilege('asistente_clinica_web', 'public.vw_catalogo_medicos_publico', 'SELECT') AS catalogo_medicos,
                has_table_privilege('asistente_clinica_web', 'public.vw_agenda_consultas_detalle', 'SELECT') AS agenda_detalle,
                has_table_privilege('asistente_clinica_web', 'public.vw_expedientes_episodios_resumen', 'SELECT') AS expedientes,
                has_table_privilege('asistente_clinica_web', 'public.audit_events', 'SELECT') AS audit_events,
                pg_has_role('asistente_clinica_web', 'rol_asistente_clinica', 'USAGE') AS hereda_rol"
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
     *     episode_title?: string|null
     * }  $data
     */
    public function registrarYFirmarConsultaSegura(array $data, ?int $actorUserId = null): void
    {
        $appointmentId = filled($data['appointment_id'] ?? null) ? (int) $data['appointment_id'] : null;

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
            ]
        );
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

    private function primaryConnection(): ConnectionInterface
    {
        return DB::connection();
    }

    private function assistantConnection(): ConnectionInterface
    {
        return DB::connection('pgsql_asistente');
    }
}
