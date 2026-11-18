<?php

namespace App\Filament\Resources\MedicalConsultations\Pages;

use App\Enums\ConsultationStatus;
use App\Filament\Resources\MedicalConsultations\MedicalConsultationResource;
use App\Models\MedicalConsultation;
use App\Services\SigcmDatabaseService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Throwable;

class CreateMedicalConsultation extends CreateRecord
{
    protected static string $resource = MedicalConsultationResource::class;

    public static function getNavigationLabel(): string
    {
        return __('sigcm.spotlight.create');
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label(__('sigcm.consultation.actions.save_draft'));
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('sigcm.consultation.actions.draft_saved_ok');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('registrarYFirmarAhora')
                ->label(__('sigcm.consultation.actions.sign_now'))
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('success')
                ->schema([
                    TextInput::make('episode_title')
                        ->label(__('sigcm.fields.episode_title'))
                        ->default('Episodio clínico ambulatorio')
                        ->required()
                        ->maxLength(180),
                ])
                ->action(function (array $data, SigcmDatabaseService $service): void {
                    $formState = $this->form->getState();

                    try {
                        $service->registrarYFirmarConsultaSegura([
                            ...$formState,
                            'episode_title' => $data['episode_title'] ?? 'Episodio clínico ambulatorio',
                        ], Auth::id());

                        Notification::make()
                            ->title('Consulta registrada y firmada')
                            ->success()
                            ->send();

                        $latest = MedicalConsultation::query()
                            ->where('patient_id', $formState['patient_id'])
                            ->orderByDesc('id')
                            ->first();

                        $this->redirect($latest
                            ? MedicalConsultationResource::getUrl('view', ['record' => $latest])
                            : MedicalConsultationResource::getUrl('index'));
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title('No se pudo registrar y firmar la consulta')
                            ->body($service->formatDatabaseException($exception))
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = ConsultationStatus::Draft->value;
        $data['signed_at'] = null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
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
