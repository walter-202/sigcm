---
paths:
  - 'database/migrations/**'
---

# Migrations

## Compatibilidad de migraciones con esquema PostgreSQL 17
Respetar las tablas cargadas por 0000_01_01_000000_install_sigcm_base_schema.php desde sql/. No agregar role_id legado en users; usar las tablas estándar de Spatie Permission con teams = false y proteger tablas compartidas con Schema::hasTable().
