# SIGCM — Sistema Integral de Gestión de Consultas Médicas

SIGCM es un sistema web para organizar la atención de un **centro médico**, desde el registro de pacientes y la programación de citas hasta la documentación de consultas e historias clínicas, integrando reglas de integridad, auditoría y seguridad directamente en **PostgreSQL 17**.

## Objetivo

Gestionar de forma consistente, segura y trazable la información operativa y clínica del centro médico.

## Alcance funcional (v1)

1. **Pacientes:** registro, identificación y consulta de datos.
2. **Médicos:** registro y administración de profesionales.
3. **Citas:** programación, confirmación y seguimiento de estados.
4. **Consultas médicas:** registro de la atención y evolución clínica.
5. **Historias clínicas:** antecedentes del paciente y acceso a sus consultas históricas.

**Distinción:** una cita es una reserva; una consulta es la atención efectivamente realizada; la historia clínica es el expediente longitudinal del paciente.

## Tecnologías utilizadas

| Capa / Componente | Tecnología | ¿Para qué sirve en SIGCM? |
|---|---|---|
| **Backend** | PHP 8.3 + Laravel 13 (`v13.42.0`) | Gestiona el enrutamiento web, migraciones, seeders, sesiones, pruebas automatizadas PHPUnit y la ejecución de consultas y procedimientos almacenados sobre PostgreSQL mediante PDO (`pgsql` y `pgsql_asistente`). |
| **Interfaz y Panel** | Filament 5 (`v5.11.4`) + Livewire 4 + Tailwind CSS 4 + Vite 8 | Construye el panel administrativo (`/admin`), el tablero ejecutivo en tiempo real y las vistas de operación e inspección BD II (Catálogo Público, Historial de Citas/Consultas con modales transaccionales y Bitácora DCL). |
| **Autorización RBAC** | Filament Shield 4 (`v4.3.1`) + Spatie Permission 8 (`v8.5.0`) | Administra roles y permisos de aplicación (`super_admin`, políticas de recursos y control de acceso al panel Filament) con `teams = false`. |
| **Base de datos** | PostgreSQL 17 (`db_sigcm`) | Motor relacional principal que garantiza integridad referencial y de dominio mediante claves primarias, `FOREIGN KEY` compuestas con `RESTRICT`, `UNIQUE`, `CHECK`, rangos `TIMESTAMPTZ` y bloqueos pesimistas (`FOR UPDATE`). |
| **Lógica en motor (BD II)** | PL/pgSQL (Índices, Triggers, Procedimientos, Cursor y Vistas) | Aplica reglas críticas directamente en la base de datos: impide modificar consultas firmadas, bloquea solapamientos de agenda, firma consultas y abre expedientes en transacciones atómicas, valoriza métricas con `CURSOR` y expone vistas de lectura segura. |
| **Seguridad DCL y Auditoría** | Roles/Privilegios PostgreSQL (`rol_asistente_clinica`) + Bitácora nativa `audit_events` (`JSONB`) | Aplica el principio de mínimo privilegio (la sesión web `asistente_clinica_web` solo lee las 3 vistas públicas y recibe bloqueo `SQLSTATE 42501` sobre `audit_events`) mientras los triggers registran automáticamente eventos `INSERT`, `UPDATE`, `SIGN` y `AMEND` sin paquetes externos de auditoría. |

## Scripts SQL (`sql/`)

Los scripts de base de datos se mantienen separados en la carpeta [`sql/`](sql):

| Orden | Archivo | Contenido |
|---|---|---|
| `01` | [`sql/01_base_de_datos_e_inserts.sql`](sql/01_base_de_datos_e_inserts.sql) | DDL completo de las **27 tablas** clínicas y de auditoría en PostgreSQL 17 + **270 registros semilla** (`10` por tabla). |
| `02` | [`sql/02_triggers_procedimientos_vistas_dcl.sql`](sql/02_triggers_procedimientos_vistas_dcl.sql) | **5 índices**, **5 triggers PL/pgSQL** (inmutabilidad, solapamiento de citas y auditoría a `audit_events`), **3 procedimientos almacenados** (`sp_registrar_y_firmar_consulta_segura`, `sp_reprogramar_cita_segura`, `sp_valorizar_actividad_clinica` con `CURSOR`), **3 vistas SQL** y **seguridad DCL** (`rol_asistente_clinica` / `asistente_clinica_web`). |

La migración [`0000_01_01_000000_install_sigcm_base_schema.php`](database/migrations/0000_01_01_000000_install_sigcm_base_schema.php) ejecuta ambos archivos automáticamente en orden cuando `php artisan migrate` corre sobre una base PostgreSQL nueva.

## Estructura y Arquitectura del Proyecto

```text
clinica/
├── sql/                             # Scripts fuente PostgreSQL 17 (esquema + BD II)
├── docs/arquitectura/               # Modelo ER (Chen + Mermaid) y auditoría técnica
├── app/
│   ├── Models/                      # Modelos Eloquent (PHP 8 attributes + casts())
│   ├── Services/                    # SigcmDatabaseService (SPs, cursor, vistas y DCL)
│   ├── Policies/                    # Políticas de autorización RBAC (Spatie/Shield)
│   └── Filament/
│       ├── Pages/                   # Páginas del panel (Catálogo, Citas/SPs, Bitácora DCL)
│       ├── Widgets/                 # KPIs en tiempo real y tabla de arquitectura BD II
│       └── Resources/               # Recursos CRUD del núcleo clínico (en construcción)
├── database/
│   ├── migrations/                  # Instalación base + sesiones/cache/jobs + Spatie
│   ├── factories/                   # Factories de prueba
│   └── seeders/                     # Usuario admin Filament y citas iniciales
├── resources/views/filament/        # Vistas Blade y estilos de soporte del panel
├── lang/es/                         # Localización en español (Laravel + Shield)
├── tests/Feature/                   # Pruebas automatizadas PHPUnit contra db_sigcm
└── .ai/rules/                       # Reglas de arquitectura por área (Laravel Boost)
```

## Documentación de Arquitectura de Datos

- [Modelo entidad–relación v1: conceptual Peter Chen y lógico Mermaid](docs/arquitectura/modelo-entidad-relacion-v1.md).
- [Comparativa técnica con Clinic (referencia, no alcance)](docs/arquitectura/auditoria-comparativa-migraciones-clinic-sigcm.md).

El diseño v1 se centra en **un centro médico**. No contempla por ahora inventario, facturación, inteligencia artificial ni gestión multi-clínica.

> **Estado del repositorio:** base consolidada en Laravel 13 + Filament 5 + Shield (`es`) sobre PostgreSQL 17 (`db_sigcm`), lista para continuar con los modelos Eloquent, recursos CRUD, políticas por rol y pruebas del núcleo clínico.
