<?php

namespace App\Filament\Resources\MedicalConsultations\Pages;

use App\Enums\ConsultationStatus;
use App\Filament\Resources\MedicalConsultations\MedicalConsultationResource;
use App\Models\AuditEvent;
use App\Models\MedicalConsultation;
use App\Services\SigcmDatabaseService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class ViewMedicalConsultation extends ViewRecord
{
    protected static string $resource = MedicalConsultationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('guardarBorrador')
                ->label(__('sigcm.consultation.actions.save_draft'))
                ->icon(Heroicon::OutlinedDocumentCheck)
                ->color('warning')
                ->visible(fn (MedicalConsultation $record): bool => $record->status === ConsultationStatus::Draft
                    && (Auth::user()?->can('update', $record) ?? false))
                ->action(function (MedicalConsultation $record): void {
                    $record->touch();
                    Notification::make()
                        ->title(__('sigcm.consultation.actions.draft_saved_ok'))
                        ->success()
                        ->send();
                }),
            MedicalConsultationResource::makeSignAction(),
            Action::make('imprimirConsulta')
                ->label(__('sigcm.clinical.print_consultation'))
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn (MedicalConsultation $record): string => route('sigcm.print.consultation', $record))
                ->openUrlInNewTab(),
            Action::make('trazabilidadAuditada')
                ->label(__('sigcm.audit.contextual_title'))
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('gray')
                ->visible(fn (MedicalConsultation $record): bool => (Auth::user()?->can('viewAny', AuditEvent::class) ?? false)
                    || (Auth::user()?->can('create', MedicalConsultation::class) ?? false))
                ->modalHeading(__('sigcm.audit.contextual_heading'))
                ->modalWidth('5xl')
                ->modalSubmitAction(false)
                ->modalContent(fn (MedicalConsultation $record): View => view('filament.components.contextual-audit-modal', [
                    'events' => app(SigcmDatabaseService::class)->getContextualAuditEventsForConsultation($record),
                ])),
            EditAction::make(),
        ];
    }
}
