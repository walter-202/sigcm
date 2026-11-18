<x-filament-panels::page>
    <style>
        .sigcm-agenda-stack {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .sigcm-agenda-filters {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 1rem;
        }

        @media (min-width: 768px) {
            .sigcm-agenda-filters {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        .sigcm-agenda-label {
            display: block;
            margin-bottom: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
        }

        :is(.dark) .sigcm-agenda-label {
            color: #e5e7eb;
        }

        .sigcm-agenda-select {
            width: 100%;
            border-radius: 0.5rem;
            border: 1px solid #d1d5db;
            background-color: #ffffff;
            color: #111827;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
        }

        :is(.dark) .sigcm-agenda-select {
            border-color: #374151;
            background-color: #111827;
            color: #ffffff;
        }
    </style>

    <div class="sigcm-agenda-stack">
        <x-filament::section>
            <div class="sigcm-agenda-filters">
                <div>
                    <label for="agenda-doctor-filter" class="sigcm-agenda-label">
                        {{ __('sigcm.schedule.filter_doctor') }}
                    </label>
                    <select
                        id="agenda-doctor-filter"
                        wire:model.live="doctorId"
                        class="sigcm-agenda-select"
                    >
                        <option value="">{{ __('sigcm.schedule.all_doctors') }}</option>
                        @foreach ($doctors as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="agenda-status-filter" class="sigcm-agenda-label">
                        {{ __('sigcm.schedule.filter_status') }}
                    </label>
                    <select
                        id="agenda-status-filter"
                        wire:model.live="statusFilter"
                        class="sigcm-agenda-select"
                    >
                        <option value="">{{ __('sigcm.schedule.all_statuses') }}</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-filament::section>

        @livewire(\App\Filament\Widgets\AgendaCalendarWidget::class)
    </div>
</x-filament-panels::page>
