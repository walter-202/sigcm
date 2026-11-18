---
paths:
  - 'sql/**'
---

# Sql

## PostgreSQL 17 y separación de scripts SQL
Mantener el esquema base y los registros semilla en sql/01_base_de_datos_e_inserts.sql y los objetos PL/pgSQL (índices, triggers, procedimientos almacenados, cursores, vistas y seguridad DCL) en sql/02_triggers_procedimientos_vistas_dcl.sql. Usar TIMESTAMPTZ, ON DELETE RESTRICT y auditoría nativa en audit_events mediante triggers sin paquetes externos.
