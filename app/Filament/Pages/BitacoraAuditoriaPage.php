<?php

namespace App\Filament\Pages;

use App\Models\AuditEvent;
use App\Services\SigcmDatabaseService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Throwable;
use UnitEnum;

class BitacoraAuditoriaPage extends Page
{
    protected static ?string $slug = 'bitacora-auditoria';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.bitacora-auditoria-page';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('sigcm.nav.security_admin');
    }

    public static function getNavigationLabel(): string
    {
        return __('sigcm.audit.page_nav');
    }

    public function getTitle(): string
    {
        return __('sigcm.audit.page_title');
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->can('viewAny', AuditEvent::class) ?? false;
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('probarInmutabilidad')
                ->label(__('sigcm.audit.check_immutability'))
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color('warning')
                ->modalHeading(__('sigcm.audit.modal_heading'))
                ->modalDescription(__('sigcm.audit.modal_description'))
                ->schema([
                    Select::make('consultation_id')
                        ->label(__('sigcm.audit.select_signed_consultation'))
                        ->options(fn (): array => app(SigcmDatabaseService::class)->getSignedConsultationOptions())
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $service = app(SigcmDatabaseService::class);

                    try {
                        $service->probarInmutabilidadConsultaFirmada((int) $data['consultation_id']);

                        Notification::make()
                            ->title(__('sigcm.audit.immutability_failed_title'))
                            ->body(__('sigcm.audit.immutability_failed_body'))
                            ->danger()
                            ->send();
                    } catch (Throwable $exception) {
                        Notification::make()
                            ->title(__('sigcm.audit.immutability_ok_title'))
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
