<?php

namespace App\Filament\Resources\MedicalConsultations\Pages;

use App\Filament\Resources\MedicalConsultations\MedicalConsultationResource;
use App\Services\SigcmDatabaseService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

class EditMedicalConsultation extends EditRecord
{
    protected static string $resource = MedicalConsultationResource::class;

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->label(__('sigcm.consultation.actions.save_draft'));
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('sigcm.consultation.actions.draft_saved_ok');
    }

    protected function getHeaderActions(): array
    {
        return [
            MedicalConsultationResource::makeSignAction(),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (QueryException $exception) {
            Notification::make()
                ->title('Error de integridad en consulta médica')
                ->body(app(SigcmDatabaseService::class)->formatDatabaseException($exception))
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
            throw $exception;
        }
    }
}
