# SIGCM — Modelo Entidad–Relación v1 (propuesta)

**Estado:** Propuesto, pendiente de validación funcional.  
**Fecha:** 2026-11-02  
**Fuente de alcance:** [README de SIGCM](../../README.md), en su versión de `main` consultada el 2026-11-02.  
**Referencia técnica comparativa:** [walter-202/clinic](https://github.com/walter-202/clinic) (no se replica su esquema ni se asume que sus módulos son parte de SIGCM).  
**Importante:** Este documento describe un modelo objetivo; SIGCM aún no contiene migraciones ni modelos Laravel.

## 1. Alcance y lenguaje ubicuo

El README compromete exactamente cinco capacidades funcionales:
1. Pacientes.
2. Médicos.
3. Citas.
4. Consultas médicas.
5. Historias clínicas.

Se adopta un **monolito modular Laravel + PostgreSQL** para una sola institución inicialmente. No se presupone multi-clínica, inventarios, facturación, recetas electrónicas, colas de espera, integraciones, inteligencia artificial, seguros ni agendas de múltiples consultorios: nada de eso aparece en el alcance aprobado.

**Glosario**
- **Paciente:** persona a quien se brinda atención.
- **Médico:** profesional responsable de una consulta o cita.
- **Cita:** reserva administrativa de un intervalo futuro. Una cita puede cancelarse o no llegar a generar atención.
- **Consulta médica:** acto clínico realmente documentado, que puede originarse en una cita o registrarse sin cita previa.
- **Historia clínica:** expediente longitudinal único por paciente, compuesto por antecedentes persistentes y el historial cronológico de consultas. La tabla `clinical_records` almacena los antecedentes; no duplica las consultas.

## 2. Diagrama ER — esquema lógico propuesto

![Modelo entidad–relación conceptual SIGCM en notación de Peter Chen](./diagramas/sigcm-chen-conceptual-v1.svg)

**Notación:** entidades en rectángulos; relaciones en rombos; atributos en óvalos; identificadores subrayados. Las etiquetas `(mínimo,máximo)` muestran la participación de cada entidad en la relación. La relación **REÚNE** figura con líneas discontinuas porque la colección de consultas de la historia se **deriva de `patient_id`**, sin introducir una FK adicional en `medical_consultations`.

- [Abrir el diagrama vectorial SVG](./diagramas/sigcm-chen-conceptual-v1.svg).
- **Modelo conceptual vs. físico:** el diagrama representa los cinco dominios del README. Las entidades técnicas `users` y `consultation_amendments` se mantienen en el diccionario lógico de este documento, sin mezclarse con el alcance conceptual de negocio.
- **Participación:** `PACIENTE (1,1) — POSEE — (1,1) HISTORIA CLÍNICA` asume el alta transaccional de ambos registros indicada por RI-01. Antes del alta, no existe ninguno de los dos.


`PK` = primary key; `FK` = foreign key; `UK` = clave única. Las columnas `created_at` y `updated_at` convencionales se omiten del diagrama para mejorar su lectura. En `patients`, la unicidad es **compuesta** entre `document_type` y `document_number` cuando ambos se conocen, no individual por campo.

### Cardinalidades y opcionalidad

| Relación | Cardinalidad | Justificación |
|---|---|---|
| Paciente — historia clínica | 1 — 0..1 | Crear expediente junto al paciente en una transacción; `clinical_records.patient_id` es `UNIQUE NOT NULL`. |
| Paciente — cita | 1 — 0..N | Puede registrarse antes de reservar. |
| Médico — cita | 1 — 0..N | Un médico atiende muchas citas, pero no debe solapar intervalos activos. |
| Paciente — consulta | 1 — 0..N | Soporta longitudinalidad clínica. |
| Médico — consulta | 1 — 0..N | Identifica la responsabilidad profesional. |
| Cita — consulta | 0..1 — 0..1 | Un acto clínico por cita; consulta sin cita válida; cita cancelada sin consulta. `appointment_id` es `UNIQUE NULLABLE`. |
| Consulta — enmienda | 1 — 0..N | Correcciones posteriores a firma sin sobrescribir originales. |
| Usuario — médico | 0..1 — 0..1 | `doctors.user_id` es opcional y único: identidad profesional distinta de credencial de acceso. |

## 3. Diccionario de entidades

### 3.1. `patients`
- Datos mínimos: `id`, `first_names`, `last_names`, documento (tipo y número opcionales), nacimiento y medios de contacto opcionales, timestamps.
- `UNIQUE(document_type, document_number)`; normalizar espacios y formato antes de persistir; **sin asumir documento para todos los pacientes**.
- No almacenar diagnósticos ni antecedentes clínicos repetidos en esta tabla.
- Borrado de datos clínicos: **no** usar cascadas desde pacientes.

### 3.2. `doctors`
- `id`, nombre, registro profesional (sujeto a validación de formato y unicidad), especialidad descriptiva y activo.
- `user_id` opcional y único: permite registrar médicos antes de habilitar cuentas.
- No duplicar autorización en `doctors`: acceso/autenticación son responsabilidad de `users`.

### 3.3. `appointments`
- `id`, paciente, médico, inicio y fin (ambos `timestamptz`), estado, motivo y cancelación, autor opcional.
- Estados iniciales propuestos: `pending`, `confirmed`, `checked_in`, `completed`, `cancelled`, `no_show`.
- Intervalos semiabiertos `[inicio, fin)` para permitir citas contiguas sin falso conflicto.
- `CHECK (ends_at > starts_at)`. El tiempo se persiste como instante con zona; presentar en la zona configurada del centro.
- Índices: `(doctor_id, starts_at)`, `(patient_id, starts_at DESC)` y exclusión de solapamiento bajo la misma condición de vigencia.
- Prohibir asignar nuevas citas a médico inactivo.

### 3.4. `clinical_records`
- Una sola fila de antecedentes longitudinales por paciente; columnas clínicas de texto editables bajo permisos y auditoría.
- `patient_id` es `NOT NULL UNIQUE`.
- La **vista de historia clínica completa** combina `patients` + `clinical_records` + `medical_consultations` (ordenadas cronológicamente). No se duplica `patient_id` ni se almacena un arreglo JSON de consultas dentro del expediente.

### 3.5. `medical_consultations`
- `patient_id` y `doctor_id` requeridos; `appointment_id` opcional y único.
- `reason_for_visit` requerido al firmar; los campos SOAP (`subjective`, `objective`, `assessment`, `plan`) permiten documentación clínica estructurada sin agregar módulos no aprobados.
- Estados mínimos: `draft` y `signed`; `signed_at` se fija al firmar.
- Una consulta `signed` no puede editarse ni borrarse: se corrige mediante `consultation_amendments`.
- Validación transaccional: si existe cita, esta debe corresponder exactamente al mismo paciente y médico y no estar cancelada. Para endurecimiento en PostgreSQL se recomienda FK compuesta `(appointment_id, patient_id, doctor_id)` hacia un índice único de `appointments(id, patient_id, doctor_id)`; se implementará durante las migraciones, no se presume implementado.

### 3.6. `consultation_amendments`
- `medical_consultation_id`, `author_user_id`, `reason`, `additional_note`, `created_at`.
- Fila de solo anexado; no modificar ni eliminar enmiendas existentes.
- Es infraestructura mínima de integridad de documentación clínica, **no** un módulo adicional de negocio.

### 3.7. `users`
- Identidades de inicio de sesión: nombre, correo único, hash de contraseña, estado y timestamps.
- Separar autenticación de la entidad profesional. No confundir la existencia de una cuenta con la autorización a leer datos clínicos.
- Matriz de acceso mínima pendiente de validación por el equipo: administrador, médico y personal de recepción. No implementar todavía un catálogo complejo de roles si no hay requisitos funcionales cerrados.

## 4. Reglas de integridad y concurrencia (invariantes)

**RI-01.** Cada historia clínica pertenece a un solo paciente, con relación 1:1 efectiva creada al registrar un paciente.  
**RI-02.** Cada cita referencia un paciente y un médico válidos.  
**RI-03.** Citas no canceladas de un mismo médico no pueden solaparse, incluso cuando dos operadores intentan reservar concurrentemente.  
**RI-04.** El intervalo de una cita es estrictamente positivo.  
**RI-05.** La consulta puede carecer de cita (atención espontánea), pero una misma cita no puede originar dos consultas.  
**RI-06.** Cuando la consulta tiene cita, paciente y médico deben coincidir con los de la cita.  
**RI-07.** La historia se obtiene de antecedentes más consultas; nunca duplicar la narración de una visita en ambas tablas.  
**RI-08.** Ningún borrado en cascada debe eliminar datos clínicos históricos.  
**RI-09.** Una consulta firmada es inmutable; toda corrección posterior requiere enmienda trazable.  
**RI-10.** Acceso a información sensible requiere políticas de autorización, auditoría y minimización de los datos expuestos.

### Exclusión de solapamiento en PostgreSQL (propuesta física, no migración ejecutada)

```sql
CREATE EXTENSION IF NOT EXISTS btree_gist;

ALTER TABLE appointments
  ADD CONSTRAINT appointments_valid_interval
  CHECK (ends_at > starts_at);

ALTER TABLE appointments
  ADD CONSTRAINT appointments_no_doctor_overlap
  EXCLUDE USING gist (
    doctor_id WITH =,
    tstzrange(starts_at, ends_at, '[)') WITH &&
  )
  WHERE (status <> 'cancelled');
```

Aplicar en una migración PostgreSQL mediante `DB::statement(...)` cuando exista el esqueleto Laravel. **No basta con una consulta previa `SELECT`**, porque dos peticiones simultáneas pueden leer disponibilidad y reservar el mismo intervalo. Capturar `SQLSTATE 23P01` y comunicar un conflicto de horario. Para pacientes simultáneos se recomienda además una exclusión por `patient_id` con el mismo rango (pendiente de validación del negocio).

## 5. Flujos transaccionales

1. **Registrar paciente**: validar identificación; insertar `patients` y su `clinical_records` en una única transacción.
2. **Programar cita**: validar médico y paciente; verificar reglas de estado; insertar cita. La base de datos rechaza intervalos de médico solapados; la aplicación convierte el conflicto en error entendible.
3. **Registrar consulta**: crear `medical_consultations` para un paciente/médico, con cita opcional. Si hay cita, exigir concordancia de identidades y estado compatible.
4. **Firmar consulta**: comprobar mínimos clínicos, autorizar médico, fijar estado y momento de firma en una transacción. Una cita puede marcarse completada como parte del mismo caso de uso.
5. **Consultar historia**: buscar expediente por paciente e incluir consultas firmadas cronológicamente con eventuales enmiendas, sin exponer datos a usuarios no autorizados.

## 6. Arquitectura de aplicación sugerida

Monolito modular, sin microservicios. Separar por responsabilidad:

```text
app/
  Domain/
    Patients/
    Doctors/
    Scheduling/
    Consultations/
    ClinicalRecords/
  Application/
    Patients/RegisterPatient.php
    Scheduling/BookAppointment.php
    Consultations/RecordConsultation.php
    Consultations/SignConsultation.php
  Policies/
  Http/
database/
  migrations/
  factories/
  seeders/
tests/
  Feature/
  Unit/
```

Este árbol es **propuesta**, no estructura actual del repositorio. Mantener invariantes transaccionales en casos de uso/servicios, no en controladores de gran tamaño. Eloquent aporta persistencia y relaciones; las exclusiones e índices corresponden a PostgreSQL. Por el alcance actual, bastan páginas Laravel/Blade con JavaScript ligero; no es necesario añadir Filament, Livewire, una SPA o servicios distribuidos sin decisión posterior.

## 7. Aceptación del diseño antes de implementar migraciones

- [ ] Entidades y cardinalidades aceptadas por responsable funcional.
- [ ] Decisión sobre atención sin cita.
- [ ] Decisión sobre una sola historia longitudinal por paciente.
- [ ] Definir estados de cita y transiciones válidas.
- [ ] Definir si la misma persona puede tener citas simultáneas con médicos distintos.
- [ ] Definir quién puede registrar, ver, firmar y corregir información clínica.
- [ ] Cerrar campos clínicos mínimos obligatorios y conservación/auditoría de registros.
- [ ] Confirmar si SIGCM es para una sola institución; no asumir multi-tenancy.
- [ ] Escribir tests: cardinalidad, consultas sin cita, citas duplicadas en concurrencia, FK de identidad y correcciones post-firma.

## 8. Fuera del alcance actual (sin migraciones)

Catálogo de servicios, especialidades M:N, salas y sillas, disponibilidad recurrente, farmacia, inventarios, seguros, pagos, SIAT, facturación, multi-clínica, IA, MCP, telemedicina, integraciones de calendario, prescripciones y órdenes. Evaluar solo ante requisitos aprobados.

**Fundamentos:** [PostgreSQL — rangos y exclusiones](https://www.postgresql.org/docs/current/rangetypes.html); [Laravel 13 — migraciones e integridad referencial](https://laravel.com/docs/13.x/migrations).
