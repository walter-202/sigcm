<?php

namespace App\Filament\Pages;

use App\Services\SigcmDatabaseService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Throwable;

class BitacoraAuditoriaPage extends Page
{
    protected static ?string $slug = 'bitacora-auditoria';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = '3. Bitácora y Seguridad DCL';

    protected static ?string $title = 'Bitácora de Auditoría y Verificación DCL';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.bitacora-auditoria-page';

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('probarInmutabilidad')
                ->label('Probar Trigger Inmutabilidad')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('warning')
                ->modalHeading('Prueba de Trigger BEFORE UPDATE (trg_validar_inmutabilidad_consulta)')
                ->modalDescription('Intenta modificar el motivo de una consulta médica ya firmada para verificar que PostgreSQL 17 rechaza la alteración.')
                ->schema([
                    Select::make('consultation_id')
                        ->label('Consulta firmada a intentar alterar')
                        ->options(fn (): array => app(SigcmDatabaseService::class)->getSignedConsultationOptions())
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $service = app(SigcmDatabaseService::class);

                    try {
                        $service->probarInmutabilidadConsultaFirmada((int) $data['consultation_id']);

                        Notification::make()
                            ->title('Advertencia: La consulta fue modificada')
                            ->body('El trigger de inmutabilidad no detuvo la operación.')
                            ->danger()
                            ->send();
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title('Inmutabilidad verificada por Trigger PL/pgSQL')
                            ->body($service->formatDatabaseException($exception))
                            ->success()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $service = app(SigcmDatabaseService::class);

        return [
            'dclCheck' => $service->probarAccesoRestringidoBitacora(),
            'eventos' => $service->getEventosBitacoraSupervisor(),
            'asistenteUser' => (string) config('database.connections.pgsql_asistente.username', 'asistente_clinica_web'),
        ];
    }
}
