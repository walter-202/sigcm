<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $superAdminRole = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@sigcm.local'],
            [
                'name' => 'Administrador SIGCM',
                'password' => Hash::make('Admin2026!'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        if (! $admin->hasRole($superAdminRole)) {
            $admin->assignRole($superAdminRole);
        }

        if (DB::connection()->getDriverName() === 'pgsql' && Schema::hasTable('appointments')) {
            $activeAppointments = (int) DB::table('appointments')
                ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
                ->count();

            if ($activeAppointments === 0) {
                $patientIds = DB::table('patients')->orderBy('id')->limit(2)->pluck('id')->all();
                $doctorIds = DB::table('doctors')->orderBy('id')->limit(2)->pluck('id')->all();

                if (count($patientIds) >= 2 && count($doctorIds) >= 2) {
                    DB::table('appointments')->insert([
                        [
                            'patient_id' => $patientIds[0],
                            'doctor_id' => $doctorIds[0],
                            'created_by_user_id' => $admin->id,
                            'starts_at' => '2026-11-18 09:00:00-04',
                            'ends_at' => '2026-11-18 10:00:00-04',
                            'status' => 'confirmed',
                            'reason' => 'Control ambulatorio programado (demo BD II)',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                        [
                            'patient_id' => $patientIds[1],
                            'doctor_id' => $doctorIds[1],
                            'created_by_user_id' => $admin->id,
                            'starts_at' => '2026-11-18 10:30:00-04',
                            'ends_at' => '2026-11-18 11:30:00-04',
                            'status' => 'pending',
                            'reason' => 'Seguimiento médico pendiente (demo BD II)',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    ]);
                }
            }
        }
    }
}
