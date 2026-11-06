# SIGCM — Sistema Integral de Gestión de Consultas Médicas

SIGCM es un sistema web para organizar la atención de un **centro médico**, desde el registro de pacientes y la programación de citas hasta la documentación de consultas e historias clínicas.

## Objetivo

Gestionar de forma consistente, segura y trazable la información operativa y clínica del centro médico.

## Alcance funcional (v1)

1. **Pacientes:** registro, identificación y consulta de datos.
2. **Médicos:** registro y administración de profesionales.
3. **Citas:** programación, confirmación y seguimiento de estados.
4. **Consultas médicas:** registro de la atención y evolución clínica.
5. **Historias clínicas:** antecedentes del paciente y acceso a sus consultas históricas.

**Distinción:** una cita es una reserva; una consulta es la atención efectivamente realizada; la historia clínica es el expediente longitudinal del paciente.

## Tecnologías previstas

- **Backend:** PHP con Laravel.
- **Interfaz:** HTML, CSS y JavaScript con Filament.
- **Autorización:** Filament Shield y Spatie Permission.
- **Base de datos:** **PostgreSQL 17** (PK, FK, UNIQUE, CHECK, rangos, transacciones, vistas y triggers cuando correspondan).
- **Auditoría:** bitácora propia en PostgreSQL, sin paquetes de auditoría de Laravel.

## Arquitectura de datos

- [Modelo entidad–relación v1: conceptual Peter Chen y lógico Mermaid](docs/arquitectura/modelo-entidad-relacion-v1.md).
- [Comparativa técnica con Clinic (referencia, no alcance)](docs/arquitectura/auditoria-comparativa-migraciones-clinic-sigcm.md).

El diseño v1 se centra en **un centro médico**. No contempla por ahora inventario, facturación, inteligencia artificial ni gestión multi-clínica. El alcance se mantiene sujeto a validación antes de implementar.

> **Estado del repositorio:** fase de diseño y documentación; no se han implementado migraciones ni modelos Eloquent de SIGCM.
