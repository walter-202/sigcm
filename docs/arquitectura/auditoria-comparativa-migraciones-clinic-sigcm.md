# Auditoría comparativa de migraciones — Clinic vs. SIGCM

**Estado:** propuesta para revisión, **no aprobada como nuevo alcance funcional**.  
**Fecha de inspección:** 2026-11-06.  
**Origen SIGCM:** [README principal](../../README.md) y [modelo lógico vigente](./modelo-entidad-relacion-v1.md).  
**Referencia:** [walter-202/clinic, rama main](https://github.com/walter-202/clinic/tree/main/database/migrations).  
**Resultado de inventario:** en la revisión realizada, `clinic/main` muestra **71 archivos de migración y 27 modelos en `app/Models`**. SIGCM `main` aún contiene solo el README; en la rama `docs/system-design-erd-v1` hay documentación, no migraciones ejecutables.

> Regla de diseño: **no migrar el historial de migraciones de clinic**. SIGCM debe iniciar con migraciones nuevas y coherentes, siguiendo el esquema objetivo y los requerimientos efectivamente aprobados.

## 1. Separación de responsabilidades

| Capa | Propietario | Qué necesita persistirse | Qué no crea tablas por sí solo |
|---|---|---|---|
| Dominio clínico SIGCM | Aplicación | Pacientes, médicos, citas, historia, consultas, enmiendas | Recursos y formularios de Filament |
| Autenticación | Laravel | `users`, `password_reset_tokens`; `sessions` según driver | `FilamentUser`, formularios de inicio de sesión |
| RBAC | Spatie Permission | `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | Shield no define otras tablas de roles |
| Auditoría | Spatie Activitylog | `activity_log` (con migraciones de evento y UUID según versión) | Trait `LogsActivity` por sí solo |
| Notificaciones | Laravel / Filament | `notifications`, **solo para notificaciones persistentes** | Alertas tipo flash |
| Queue / cache | Laravel | `jobs`, `failed_jobs`, `job_batches`, `cache`, `cache_locks`, `sessions` de acuerdo con drivers | Jobs sincrónicos y cache/sesiones en archivos |
| Import/export masivo | Filament Actions | `imports`, `exports`, `failed_import_rows` + infraestructura de lotes | Tabla Filament por cada `Resource` |
| MFA | Filament Auth | Columnas de secreto y recuperación en `users` cuando se habilita autenticador | Una tabla `filament_mfa` |
| API tokens | Sanctum | `personal_access_tokens` solo si se usa autenticación por tokens | Panel web basado en sesiones |
| Telescope | Laravel Telescope | `telescope_entries`, `telescope_entries_tags`, `telescope_monitoring` | Requisito funcional de SIGCM |

### Evidencia específica de Clinic

- `clinic/.env.example` usa `SESSION_DRIVER=file`, `CACHE_DRIVER=file` y `QUEUE_CONNECTION=sync`: por eso no hay archivos propios de migración `create_sessions`, `create_cache` o `create_jobs` en ese repositorio. **Esto no implica que Laravel 13 no pueda necesitarlos** si SIGCM elige drivers de base de datos.
- `clinic/database/migrations/2014_10_12_000000_create_users_table.php` tiene `users.role_id` y `clinic/database/migrations/2023_08_12_202231_create_roles_table.php` crea `roles` legado. Luego `2025_11_17_033747_create_permission_tables.php` adapta las estructuras para Spatie. **No copiar ese legado**: crear solo el esquema Spatie de la versión instalada.
- En `clinic/app/Models/User.php` existe lógica de sincronización para `role_id` heredado, aunque `HasRoles` es la fuente de verdad. SIGCM no necesita este código.
- En `clinic/config/permission.php`, `teams=false`. Shield configura el modelo autorizable `App\\Models\\User`.
- Las 3 migraciones de `activity_log` pertenecen a Spatie Activitylog: esquema base, campo `event` y campo `batch_uuid`.
- `2026_09_04_144614_add_app_authentication_columns_to_users_table.php` agrega dos columnas para MFA de Filament y no una tabla nueva.
- La migración de `notifications` de Clinic utiliza `text('data')`. **En PostgreSQL y Filament 5**, la documentación exige `json('data')`; no copiarla literalmente.
- El paquete `filament/spatie-laravel-media-library-plugin` figura en `composer.json` de Clinic, pero en el árbol examinado no se identifica una migración `create_media_table`. Si SIGCM adopta la biblioteca de medios, deberá revisar y publicar la migración de Spatie Media Library. Un archivo subido con `FileUpload` simple no requiere por sí mismo tabla `media`.

## 2. Matriz de migraciones comparadas

**Clasificación:** A = adoptar al inicio; C = adoptar si se habilita el componente; P1 = mejora priorizable sin aprobación funcional actual; N = no trasladar.

| Grupo real en Clinic | Tabla(s)/cambio | Estado SIGCM | Justificación y tratamiento |
|---|---|---|---|
| `create_users_table` | `users` | **A — adaptar** | Crear con identidad Laravel, `email_verified_at` y `remember_token` si se usan las funciones correspondientes; **sin** `role_id`. |
| `create_password_reset_tokens_table` | `password_reset_tokens` | **A** | La recuperación de contraseña de Filament usa el broker Laravel. |
| `create_roles_table` (2023) | `roles` heredada | **N** | Evitar rol único en `users` y doble procedencia de permisos. |
| `create_permission_tables` (2025) | Cinco tablas Spatie | **A — migración oficial** | El modelo actual ya incluye estas cinco tablas. Publicar la migración de la versión instalada; no reutilizar adaptaciones históricas de Clinic. |
| `create_activity_log_table`, `add_event_column`, `add_batch_uuid_column` | `activity_log` | **A** | Trazabilidad de ediciones; limitar campos clínicos y privados que se registran en `properties`. |
| `create_notifications_table` | `notifications` | **C / aconsejable** | Para notificaciones persistentes del panel; en PostgreSQL usar columna `data JSON`. |
| `add_app_authentication_columns_to_users_table` | Dos columnas MFA en `users` | **C / aconsejable** | Requiere habilitar autenticador TOTP de Filament, configurar recuperación y pruebas de seguridad. |
| `create_failed_jobs_table` | `failed_jobs` | **C** | Necesario con manejo de trabajos fallidos en base de datos. |
| No aparece en Clinic | `jobs` | **C** | Laravel lo requiere con `QUEUE_CONNECTION=database`. No es exigido por `sync` o una cola Redis. |
| No aparece en Clinic | `sessions` | **C** | Se requiere si SIGCM usa `SESSION_DRIVER=database`. |
| No aparece en Clinic | `cache`, `cache_locks` | **C** | Si se utiliza `CACHE_STORE=database`; no son necesarias con caché en archivos/Redis. |
| `create_job_batches_table` | `job_batches` | **C** | Para `Bus::batch` y acciones de import/export Filament. |
| `create_imports_table`, `create_exports_table`, `create_failed_import_rows_table` | Tres tablas Filament | **P1/C** | Cuando se apruebe importación/exportación masiva, con `job_batches` y `notifications`. |
| `create_personal_access_tokens_table` | `personal_access_tokens` | **N/C** | Solo al habilitar tokens de API con Sanctum. No usar por inercia en un panel web. |
| `create_telescope_entries_table` | Tres tablas Telescope | **N/C desarrollo** | Diagnóstico opcional; no exponer registros y cargas clínicas en producción. |
| `create_patients_table` + mejoras | `patients` | **A — adaptar** | Conservar entidad; elegir identidad opcional compuesta, sin hacer obligatorio documento; evaluar `privacy_consent_at` / `privacy_consent_version` contra requerimientos. |
| `create_appointments_table` + mejoras de booking | `appointments` | **A — rediseñar** | SIGCM tiene cita de intervalos `timestamptz` por médico, no `slot_id`, asientos o servicios múltiples. |
| `create_clinical_histories_table` | `clinical_histories` | **No copiar** | En Clinic funciona como episodio clínico, distinto del expediente longitudinal `clinical_records` de SIGCM. |
| `create_clinical_follow_ups_table` | `clinical_follow_ups` | **No copiar** | SIGCM modela cada encuentro como `medical_consultations`, con SOAP en la misma tabla. |
| `add_episode_followup_and_reschedule_support` | `appointment_reschedules` y cambios de episodio | **P1 — adaptar solo reschedules** | Tabla de reprogramaciones útil si se aprueba flujo específico; no traer episodios y órdenes por ese archivo. |
| `add_check_in_vitals_to_appointments_table` | `appointments.check_in_vitals` | **P1 — rediseñar** | Si se registran signos vitales, distinguir captura administrativa de mediciones clínicas; evitar duplicación inconsistente. |
| `add_privacy_consent_to_patients_table` | Columnas de consentimiento | **P1** | Considerar consentimiento, versión, momento y evidencia cuando se validen requisitos operativos y normativos. |
| `add_user_id_to_patients_table` | `patients.user_id` | **N/C** | Se necesita solo al implementar portal del paciente; no es requisito actual. |
| `create_service_schedules_table` | `service_schedules` | **N/P1** | Sistema de horarios por servicio y asientos; SIGCM no tiene todavía catálogo de servicios ni multi-asiento. |
| `create_doctor_schedule_assignments_table` | `doctor_schedule_assignments` | **N/P1** | Si se aprueba disponibilidad semanal, diseñar alternativa pequeña `doctor_availabilities` en lugar de copiar dependencia de `service_schedules`. |
| `create_clinic_holidays_table` | `clinic_holidays`, `clinic_holiday_service` | **N/P1** | Cierres y excepciones de agenda pueden modelarse más adelante sin multi-clínica. |
| `create_appointment_waitlist_entries_table` | `appointment_waitlist_entries` | **N** | Espera automática fuera de alcance. |
| `create_notes_table` | `notes` polimórfica | **N** | Los textos de consulta ya tienen destino. Solo agregar notas compartidas si surge una relación de negocio. |
| `create_care_team_assignments_table` | `care_team_assignments` | **N** | Colaboración multiprofesional por episodio, no requerida hoy. |
| `create_clinical_orders_and_prescriptions_tables` | `clinical_orders`, `prescriptions`, `prescription_items` | **N** | Recetas/órdenes no están en el README de SIGCM. |
| `create_materials_table`, `create_medications_table` | Inventario | **N** | Fuera del alcance. |
| Migraciones de `charges`, `payments`, `siat_invoices`, `billing_credentials` | Caja y cobros | **N** | Fuera del alcance. |
| Migraciones IA/MCP, `agent_conversations`, `ai_messages`, credenciales | Asistentes | **N** | No integrar sin requisito nuevo. |
| Migraciones `clinics`, `clinic_user`, `clinic_patient` | Multi-clínica | **N** | SIGCM parte de un centro médico, sin multitenancy declarado. |
| `drop_legacy_slot_schedule_tables` | Desmontaje histórico de `slots`, `schedules` | **N** | No crear ni migrar estructuras históricas que nunca existieron en SIGCM. |

## 3. Migraciones objetivo y orden recomendado

### P0 — modelo inicial con capacidades de seguridad

1. `users` (Laravel adaptado: sin `role_id`).
2. `password_reset_tokens` (Laravel).
3. Migración publicada de `spatie/laravel-permission`: cinco tablas.
4. `patients`.
5. `doctors` (FK opcional única `user_id`).
6. `clinical_records` (FK `patient_id` única).
7. `appointments` (rangos temporales, FK, estados, bloqueo de solapamiento).
8. `medical_consultations` (FK opcional y única a citas, consistencia compuesta de paciente/médico).
9. `consultation_amendments` (append-only).
10. Migración publicada de `spatie/laravel-activitylog`: tabla y columnas que correspondan a la versión.

**Conteo de tablas:** 12 del núcleo actual + 1 `password_reset_tokens` + 1 `activity_log` = **14 tablas** en este perfil. La tabla `migrations` de Laravel, y cualquier otra tabla de administración del framework, no se incluyen en esta contabilidad funcional.

**Características recomendadas pero activables:** `notifications` (+1 tabla), columnas TOTP MFA en `users` (+0 tablas). Si se eligen `database` para sesiones/caché/cola, se incorporan respectivamente `sessions` (+1), `cache` y `cache_locks` (+2), `jobs` y `failed_jobs` (+2). No presumir esos controladores antes de decidir despliegue.

### P1 — mejoras de alcance sujeto a aceptación

- Reprogramación: `appointment_reschedules`, con autor, fecha, horario anterior/nuevo y motivo, o evento auditado estructurado equivalente.
- Disponibilidad médica semanal: `doctor_availabilities` pequeña y excepciones, solo si se quiere calcular oferta de citas en vez de impedir conflictos entre reservas.
- Consentimiento y tratamiento de información clínica: campos y evidencia, definidos según proceso real.
- Signos vitales: columnas tipadas de consulta o entidad `consultation_vital_signs` cuando exista el requisito, evitando almacenar simultáneamente dos fuentes de verdad.

### P2 — funcionalidades explícitamente futuras

- `jobs`, `job_batches`, `notifications`, `imports`, `exports` y `failed_import_rows` para tareas asíncronas y movimientos masivos controlados (según los drivers seleccionados).
- `personal_access_tokens` para API Sanctum.
- `patients.user_id` y permisos autocontenidos para portal de paciente.
- `media` si se adopta Spatie Media Library para adjuntos; **no** es requisito del componente Filament `FileUpload` por sí solo.

## 4. Mejoras necesarias en el documento ER vigente

| Prioridad | Ajuste recomendado | Razón |
|---|---|---|
| P0 | Sustituir “esquema completo de 12 tablas” por “núcleo lógico funcional de 12 tablas” | El modelo actual omite deliberadamente tablas de soporte dependientes de configuración. |
| P0 | Agregar clasificación “dominio / autenticación / autorización / operaciones / opcionales” | Permite diferenciar modelos Eloquent propios y modelos de paquetes. |
| P0 | Añadir `password_reset_tokens` y `activity_log` en un diagrama técnico independiente | No saturar el Chen conceptual ni confundir la auditoría con entidad médica. |
| P0 | Aclarar que Filament Resources, Filament Pages, widgets y `FilamentUser` no producen migraciones | Evita tablas ficticias por componentes de interfaz. |
| P0 | Completar columnas Laravel `email_verified_at` y `remember_token` cuando las capacidades correspondientes estén habilitadas | El `users` propuesto no refleja todavía todas las opciones del sistema de autenticación. |
| P0 | Revisar todos los `TIMESTAMPTZ`: usar explícitamente `timestampTz` / `timestampsTz` cuando se requiera | `timestamps()` no expresa por sí mismo el uso de columnas con zona horaria en PostgreSQL. |
| P0 | Explicitar las FKs compuestas y las reglas de fechas/estados a nivel de base de datos | Las validaciones de Filament/Livewire no protegen escrituras concurrentes por sí solas. |
| P0 | Definir políticas por registro clínico y acceso mínimo para recepción/administración | Un rol de Shield no resuelve la visibilidad contextual de datos sensibles. |
| P0 | Restringir `activity_log.properties` para no duplicar datos sensibles ni secretos | Auditar **quién/cuándo/qué** sin persistir innecesariamente historias en logs. |
| P1 | Añadir matriz “migración → tabla → modelo → recurso Filament → policy → test” | Establece trazabilidad arquitectónica y criterios de aceptación. |
| P1 | Corregir afirmación categórica de “3FN”: identificar las FKs duplicadas que se validan con restricciones compuestas y las derivaciones longitudinales | La forma normal debe verificarse por dependencias funcionales, no solo declararse. |
| P1 | Especificar una política de modificación de `clinical_records` y de adendas post-firma | Evita reescrituras de información clínica sin trazabilidad. |
| P1 | Incorporar tabla de control “driver / tabla necesaria / valor `.env`” | Mantiene consistencia entre despliegue y modelo físico. |

## 5. Advertencias de seguridad y compatibilidad

1. **Filament Shield y Spatie:** adoptar **un** esquema de permisos y guard consistente; no copiar `users.role_id` ni el seeder de Clinic íntegro.
2. **Datos personales:** la propia auditoría debe evitar guardar payloads completos de anamnesis, diagnósticos o notas clínicas en `properties` de Activitylog y `failed_jobs.payload`; evaluar minimización, controles de acceso y retención.
3. **Firma clínica:** una policy no garantiza inmutabilidad frente a SQL o código mal configurado. Revisar restricciones y mecanismos de append-only verificables según riesgo.
4. **PostgreSQL:** no copiar ciegamente migraciones históricas con `->after()`, dependencias de tablas antiguas o `ON DELETE CASCADE` sobre expedientes; diseñar de cero.
5. **Importaciones Filament:** cuando se agreguen, implementar **autorización por registro** en el importador, ya que los permisos visuales de Filament no se aplican automáticamente por fila.
6. **Las cifras son orientativas:** la cantidad exacta de migraciones no es igual a la cantidad de tablas, porque una migración puede crear múltiples tablas y varias migraciones pueden alterar una misma tabla.

## 6. Criterios de aceptación del próximo diseño

- [ ] Definir `SESSION_DRIVER`, `CACHE_STORE` y `QUEUE_CONNECTION` de SIGCM.
- [ ] Confirmar si se habilita contraseña recuperable, notificaciones en base de datos y MFA de personal.
- [ ] Confirmar si gestionar citas implica reprogramación y disponibilidad médica semanal.
- [ ] Elegir si pacientes tendrán portal en la primera versión.
- [ ] Adoptar Spatie Permission `teams=false` y evitar roles duplicados.
- [ ] Definir el mínimo de auditoría de acceso, cambios y firmas clínicas sin exponer datos sensibles.
- [ ] Validar migraciones PostgreSQL en una base limpia y probar solapamientos mediante concurrencia.
- [ ] Mantener el README como única autoridad de alcance hasta que se aprueben nuevos requisitos.

## 7. Fuentes

**Código inspeccionado:**
- [Migrations de Clinic](https://github.com/walter-202/clinic/tree/main/database/migrations)
- [Modelos Eloquent de Clinic](https://github.com/walter-202/clinic/tree/main/app/Models)
- [User.php de Clinic](https://github.com/walter-202/clinic/blob/main/app/Models/User.php)
- [Config/permission.php de Clinic](https://github.com/walter-202/clinic/blob/main/config/permission.php)
- [Config/filament-shield.php de Clinic](https://github.com/walter-202/clinic/blob/main/config/filament-shield.php)
- [Migración de notificaciones de Clinic](https://github.com/walter-202/clinic/blob/main/database/migrations/2026_05_12_000000_create_notifications_table.php)
- [Migraciones de Permission de Clinic](https://github.com/walter-202/clinic/blob/main/database/migrations/2025_11_17_033747_create_permission_tables.php)

**Documentación oficial:**
- [Laravel: Session](https://laravel.com/docs/13.x/session)
- [Laravel: Cache](https://laravel.com/docs/13.x/cache)
- [Laravel: Queues](https://laravel.com/docs/13.x/queues)
- [Filament 5: importaciones](https://filamentphp.com/docs/5.x/actions/import)
- [Filament 5: notificaciones de base de datos](https://filamentphp.com/docs/5.x/notifications/database-notifications)
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission/v8/introduction)
