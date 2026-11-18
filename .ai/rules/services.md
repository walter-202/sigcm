---
paths:
  - 'app/Services/**'
---

# Services

## Centralizar llamadas a PostgreSQL 17 en SigcmDatabaseService
Encapsular llamadas CALL sp_*, lecturas sobre vistas restringidas con la conexión pgsql_asistente y traducción de errores PDO en SigcmDatabaseService, usando casts explícitos en parámetros SQL.
