<?php

namespace App\Filament\Pages;

use App\Enums\AppointmentStatus;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\Appointment;
use App\Services\SigcmDatabaseService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AgendaMedicaPage extends Page
{
    protected static ?string $slug = 'agenda-medica';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDateRange;

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.agenda-medica-page';

    public ?int $doctorId = null;

    public ?string $statusFilter = null;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('sigcm.nav.clinical_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('sigcm.nav.medical_schedule');
    }

    public function getTitle(): string
    {
        return __('sigcm.schedule.title');
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->can('viewAny', Appointment::class) ?? false;
    }

    public function updatedDoctorId(): void
    {
        $this->dispatch('agenda-filter-updated', doctorId: $this->doctorId, statusFilter: $this->statusFilter);
    }

    public function updatedStatusFilter(): void
    {
        $this->dispatch('agenda-filter-updated', doctorId: $this->doctorId, statusFilter: $this->statusFilter);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('listadoCitas')
                ->label(__('sigcm.schedule.table_view'))
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->url(fn (): string => AppointmentResource::getUrl('index')),
            Action::make('nuevaCita')
                ->label(__('sigcm.actions.new_appointment'))
                ->icon(Heroicon::OutlinedPlusCircle)
                ->visible(fn (): bool => Auth::user()?->can('create', Appointment::class) ?? false)
                ->url(fn (): string => AppointmentResource::getUrl('create')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'doctors' => app(SigcmDatabaseService::class)->getDoctorOptions(),
            'statuses' => collect(AppointmentStatus::cases())
                ->mapWithKeys(fn (AppointmentStatus $status): array => [$status->value => $status->getLabel()])
                ->all(),
        ];
    }
}
