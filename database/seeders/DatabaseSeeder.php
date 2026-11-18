<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allPermissionNames = [
            'ViewAny:Patient', 'View:Patient', 'Create:Patient', 'Update:Patient',
            'ViewAny:Appointment', 'View:Appointment', 'Create:Appointment', 'Update:Appointment',
            'ViewAny:MedicalConsultation', 'View:MedicalConsultation', 'Create:MedicalConsultation', 'Update:MedicalConsultation',
            'ViewAny:ClinicalRecord', 'View:ClinicalRecord', 'Update:ClinicalRecord',
            'ViewAny:Doctor', 'View:Doctor', 'Create:Doctor', 'Update:Doctor',
            'ViewAny:Prescription', 'View:Prescription', 'Create:Prescription', 'Update:Prescription',
            'ViewAny:AuditEvent', 'View:AuditEvent',
            'ViewAny:User', 'View:User', 'Create:User', 'Update:User',
            'ViewAny:Role', 'View:Role', 'Create:Role', 'Update:Role', 'Delete:Role',
            'View:SigcmStatsOverviewWidget',
            'View:BitacoraAuditoriaPage',
        ];

        foreach ($allPermissionNames as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $superAdminRole = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);
        $superAdminRole->syncPermissions($allPermissionNames);

        $medicoRole = Role::firstOrCreate([
            'name' => 'medico',
            'guard_name' => 'web',
        ]);
        $medicoRole->syncPermissions([
            'ViewAny:Patient', 'View:Patient', 'Create:Patient', 'Update:Patient',
            'ViewAny:Appointment', 'View:Appointment', 'Create:Appointment', 'Update:Appointment',
            'ViewAny:MedicalConsultation', 'View:MedicalConsultation', 'Create:MedicalConsultation', 'Update:MedicalConsultation',
            'ViewAny:ClinicalRecord', 'View:ClinicalRecord', 'Update:ClinicalRecord',
            'ViewAny:Doctor', 'View:Doctor',
            'ViewAny:Prescription', 'View:Prescription', 'Create:Prescription', 'Update:Prescription',
            'View:SigcmStatsOverviewWidget',
        ]);

        $recepcionRole = Role::firstOrCreate([
            'name' => 'recepcion',
            'guard_name' => 'web',
        ]);
        $recepcionRole->syncPermissions([
            'ViewAny:Patient', 'View:Patient', 'Create:Patient', 'Update:Patient',
            'ViewAny:Appointment', 'View:Appointment', 'Create:Appointment', 'Update:Appointment',
            'ViewAny:Doctor', 'View:Doctor',
            'View:SigcmStatsOverviewWidget',
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
        $admin->syncRoles([$superAdminRole]);

        $medicoUser = User::query()->updateOrCreate(
            ['email' => 'medico@sigcm.local'],
            [
                'name' => 'Dr. Carlos Mendoza',
                'password' => Hash::make('Medico2026!'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $medicoUser->syncRoles([$medicoRole]);

        $recepcionUser = User::query()->updateOrCreate(
            ['email' => 'recepcion@sigcm.local'],
            [
                'name' => 'Recepción SIGCM',
                'password' => Hash::make('Recepcion2026!'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $recepcionUser->syncRoles([$recepcionRole]);

        if (DB::connection()->getDriverName() === 'pgsql' && Schema::hasTable('appointments')) {
            $activeAppointments = (int) DB::table('appointments')
                ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
                ->count();

            if ($activeAppointments === 0) {
                $patientIds = DB::table('patients')->orderBy('id')->limit(5)->pluck('id')->all();
                $doctorIds = DB::table('doctors')->orderBy('id')->limit(5)->pluck('id')->all();
                $today = now()->startOfDay();

                if (count($patientIds) >= 5 && count($doctorIds) >= 5) {
                    DB::table('appointments')->insert([
                        [
                            'patient_id' => $patientIds[0],
                            'doctor_id' => $doctorIds[0],
                            'created_by_user_id' => $recepcionUser->id,
                            'starts_at' => $today->copy()->setTime(9, 0),
                            'ends_at' => $today->copy()->setTime(9, 45),
                            'status' => 'checked_in',
                            'reason' => 'Control de presión arterial con registro domiciliario',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                        [
                            'patient_id' => $patientIds[1],
                            'doctor_id' => $doctorIds[1],
                            'created_by_user_id' => $recepcionUser->id,
                            'starts_at' => $today->copy()->setTime(10, 0),
                            'ends_at' => $today->copy()->setTime(10, 45),
                            'status' => 'confirmed',
                            'reason' => 'Revisión de perfil tiroideo y electrocardiograma',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                        [
                            'patient_id' => $patientIds[2],
                            'doctor_id' => $doctorIds[2],
                            'created_by_user_id' => $recepcionUser->id,
                            'starts_at' => $today->copy()->setTime(11, 15),
                            'ends_at' => $today->copy()->setTime(12, 0),
                            'status' => 'pending',
                            'reason' => 'Evaluación de hemoglobina glicosilada HbA1c',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                        [
                            'patient_id' => $patientIds[3],
                            'doctor_id' => $doctorIds[3],
                            'created_by_user_id' => $recepcionUser->id,
                            'starts_at' => $today->copy()->addDay()->setTime(9, 30),
                            'ends_at' => $today->copy()->addDay()->setTime(10, 15),
                            'status' => 'confirmed',
                            'reason' => 'Control respiratorio y técnica inhalatoria',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                        [
                            'patient_id' => $patientIds[4],
                            'doctor_id' => $doctorIds[4],
                            'created_by_user_id' => $recepcionUser->id,
                            'starts_at' => $today->copy()->addDays(2)->setTime(15, 0),
                            'ends_at' => $today->copy()->addDays(2)->setTime(15, 45),
                            'status' => 'pending',
                            'reason' => 'Seguimiento de tratamiento antirreflujo',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    ]);
                }
            }
        }
    }
}
