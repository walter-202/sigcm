<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\CareEpisode;
use App\Models\ClinicalDocument;
use App\Models\ClinicalRecord;
use App\Models\ConsultationAmendment;
use App\Models\DiagnosticReport;
use App\Models\MedicalConsultation;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Models\PatientCondition;
use App\Models\Prescription;
use App\Observers\PostgresAuditActorObserver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $auditedModels = [
            Patient::class,
            Appointment::class,
            MedicalConsultation::class,
            ClinicalRecord::class,
            CareEpisode::class,
            PatientAllergy::class,
            PatientCondition::class,
            Prescription::class,
            DiagnosticReport::class,
            ClinicalDocument::class,
            ConsultationAmendment::class,
        ];

        foreach ($auditedModels as $modelClass) {
            $modelClass::observe(PostgresAuditActorObserver::class);
        }
    }
}
