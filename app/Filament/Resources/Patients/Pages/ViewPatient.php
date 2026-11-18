<?php

namespace App\Filament\Resources\Patients\Pages;

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Filament\Resources\MedicalConsultations\MedicalConsultationResource;
use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\Patients\Widgets\PatientSummaryWidget;
use App\Models\Appointment;
use App\Models\AuditEvent;
use App\Models\MedicalConsultation;
use App\Services\SigcmDatabaseService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class ViewPatient extends ViewRecord
{
    protected static string $resource = PatientResource::class;

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return __('sigcm.patients.summary_tab');
    }

    public function getContentTabIcon(): string|BackedEnum|Htmlable|null
    {
        return Heroicon::OutlinedClipboardDocumentList;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PatientSummaryWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('nuevaCita')
                ->label(__('sigcm.actions.new_appointment'))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('info')
                ->visible(fn (): bool => Auth::user()?->can('create', Appointment::class) ?? false)
                ->url(fn (): string => AppointmentResource::getUrl('create', [
                    'patient_id' => $this->getRecord()->getKey(),
                ])),
            Action::make('nuevaConsulta')
                ->label(__('sigcm.actions.new_consultation'))
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('primary')
                ->visible(fn (): bool => Auth::user()?->can('create', MedicalConsultation::class) ?? false)
                ->url(fn (): string => MedicalConsultationResource::getUrl('create', [
                    'patient_id' => $this->getRecord()->getKey(),
                ])),
            Action::make('trazabilidadAuditada')
                ->label(__('sigcm.audit.contextual_title'))
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('gray')
                ->visible(fn (): bool => (Auth::user()?->can('viewAny', AuditEvent::class) ?? false)
                    || (Auth::user()?->can('create', MedicalConsultation::class) ?? false))
                ->modalHeading(__('sigcm.audit.contextual_heading'))
                ->modalWidth('5xl')
                ->modalSubmitAction(false)
                ->modalContent(fn (): View => view('filament.components.contextual-audit-modal', [
                    'events' => app(SigcmDatabaseService::class)->getContextualAuditEventsForPatient($this->getRecord()),
                ])),
        ];
    }
}
