---
paths:
  - 'tests/Feature/**'
---

# Feature

## Pruebas PHPUnit aisladas contra db_sigcm
Configurar database.default = pgsql en pruebas de integración contra db_sigcm y envolver escrituras en DB::connection('pgsql')->beginTransaction() / rollBack() dentro de try/finally para preservar los datos iniciales.
