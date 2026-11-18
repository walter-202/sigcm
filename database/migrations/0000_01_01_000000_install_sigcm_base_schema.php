<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Instala el esquema base de SIGCM en PostgreSQL si aún no fue cargado.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (Schema::hasTable('patients')) {
            return;
        }

        $scripts = [
            base_path('sql/01_base_de_datos_e_inserts.sql'),
            base_path('sql/02_triggers_procedimientos_vistas_dcl.sql'),
        ];

        foreach ($scripts as $sqlPath) {
            if (is_file($sqlPath)) {
                DB::unprepared((string) file_get_contents($sqlPath));
            }
        }
    }

    /**
     * No destruye tablas clínicas desde rollback automático.
     */
    public function down(): void
    {
        // Conservación deliberada de tablas clínicas e historial.
    }
};
