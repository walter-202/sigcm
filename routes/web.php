<?php

use App\Models\MedicalConsultation;
use App\Models\Prescription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

Route::get('/', function (): RedirectResponse {
    return redirect('/admin');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/consultas/{consultation}/imprimir', function (MedicalConsultation $consultation): View {
        abort_unless(Auth::user()?->can('view', $consultation) ?? false, 403);

        $consultation->loadMissing([
            'patient.clinicalRecord',
            'doctor',
            'observations',
            'diagnoses',
            'prescriptions.items',
            'clinicalOrders',
            'referrals',
            'amendments.author',
        ]);

        return view('print.consultation-summary', [
            'consultation' => $consultation,
        ]);
    })->name('sigcm.print.consultation');

    Route::get('/recetas/{prescription}/imprimir', function (Prescription $prescription): View {
        abort_unless(Auth::user()?->can('view', $prescription) ?? false, 403);

        $prescription->loadMissing([
            'consultation.patient.clinicalRecord',
            'consultation.doctor',
            'items',
        ]);

        return view('print.prescription', [
            'prescription' => $prescription,
        ]);
    })->name('sigcm.print.prescription');
});
