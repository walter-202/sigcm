<x-filament-widgets::widget>
    @if (isset($patient))
        <style>
            .sigcm-summary-grid {
                display: grid;
                grid-template-columns: minmax(0, 1fr);
                gap: 1rem;
            }

            @media (min-width: 768px) {
                .sigcm-summary-grid {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                }
            }

            .sigcm-summary-dl {
                margin: 0;
                display: flex;
                flex-direction: column;
                gap: 0.625rem;
                font-size: 0.875rem;
                line-height: 1.35;
            }

            .sigcm-summary-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
                padding-bottom: 0.375rem;
                border-bottom: 1px solid rgba(128, 128, 128, 0.14);
            }

            .sigcm-summary-row:last-child {
                padding-bottom: 0;
                border-bottom: none;
            }

            .sigcm-summary-term {
                color: #6b7280;
                flex-shrink: 0;
            }

            :is(.dark) .sigcm-summary-term {
                color: #9ca3af;
            }

            .sigcm-summary-val {
                font-weight: 500;
                color: #111827;
                text-align: right;
                overflow-wrap: anywhere;
            }

            :is(.dark) .sigcm-summary-val {
                color: #f9fafb;
            }

            .sigcm-alerts-stack {
                display: flex;
                flex-direction: column;
                gap: 0.875rem;
                font-size: 0.875rem;
            }

            .sigcm-alerts-label {
                margin: 0 0 0.375rem 0;
                font-weight: 500;
                color: #374151;
            }

            :is(.dark) .sigcm-alerts-label {
                color: #d1d5db;
            }

            .sigcm-badge-list {
                display: flex;
                flex-wrap: wrap;
                gap: 0.375rem;
            }
        </style>

        <div class="sigcm-summary-grid">
            <x-filament::section :heading="__('sigcm.patients.cards.patient_data')">
                <dl class="sigcm-summary-dl">
                    <div class="sigcm-summary-row">
                        <dt class="sigcm-summary-term">{{ __('sigcm.fields.id') }}</dt>
                        <dd class="sigcm-summary-val">#{{ $patient->id }}</dd>
                    </div>
                    <div class="sigcm-summary-row">
                        <dt class="sigcm-summary-term">{{ __('sigcm.fields.document') }}</dt>
                        <dd class="sigcm-summary-val">
                            {{ $patient->document_type && $patient->document_number ? "{$patient->document_type}-{$patient->document_number}" : __('sigcm.patients.cards.no_document') }}
                        </dd>
                    </div>
                    <div class="sigcm-summary-row">
                        <dt class="sigcm-summary-term">{{ __('sigcm.fields.clinical_record') }}</dt>
                        <dd class="sigcm-summary-val">
                            @if ($patient->clinicalRecord && $recordUrl)
                                <a href="{{ $recordUrl }}" style="display: inline-flex; text-decoration: none;">
                                    <x-filament::badge color="success">
                                        HC-{{ str_pad((string) $patient->clinicalRecord->id, 4, '0', STR_PAD_LEFT) }}
                                    </x-filament::badge>
                                </a>
                            @else
                                <x-filament::badge color="gray">
                                    {{ __('sigcm.patients.no_record') }}
                                </x-filament::badge>
                            @endif
                        </dd>
                    </div>
                    <div class="sigcm-summary-row">
                        <dt class="sigcm-summary-term">{{ __('sigcm.fields.phone') }}</dt>
                        <dd class="sigcm-summary-val">{{ $patient->phone ?: '-' }}</dd>
                    </div>
                </dl>
            </x-filament::section>

            <x-filament::section :heading="__('sigcm.patients.cards.personal_info')">
                <dl class="sigcm-summary-dl">
                    <div class="sigcm-summary-row">
                        <dt class="sigcm-summary-term">{{ __('sigcm.fields.full_name') }}</dt>
                        <dd class="sigcm-summary-val">{{ $patient->full_name }}</dd>
                    </div>
                    <div class="sigcm-summary-row">
                        <dt class="sigcm-summary-term">{{ __('sigcm.fields.birth_date') }}</dt>
                        <dd class="sigcm-summary-val">
                            @if ($patient->birth_date)
                                {{ $patient->birth_date->format('d/m/Y') }} ({{ __('sigcm.patients.years_old', ['age' => $patient->birth_date->age]) }})
                            @else
                                -
                            @endif
                        </dd>
                    </div>
                    <div class="sigcm-summary-row">
                        <dt class="sigcm-summary-term">{{ __('sigcm.fields.email') }}</dt>
                        <dd class="sigcm-summary-val">{{ $patient->email ?: '-' }}</dd>
                    </div>
                    <div class="sigcm-summary-row">
                        <dt class="sigcm-summary-term">{{ __('sigcm.fields.status') }}</dt>
                        <dd class="sigcm-summary-val">
                            <x-filament::badge :color="$patient->is_active ? 'success' : 'gray'">
                                {{ $patient->is_active ? __('sigcm.patients.cards.active') : __('sigcm.patients.cards.inactive') }}
                            </x-filament::badge>
                        </dd>
                    </div>
                </dl>
            </x-filament::section>

            <x-filament::section :heading="__('sigcm.patients.cards.clinical_alerts')">
                <div class="sigcm-alerts-stack">
                    <div>
                        <p class="sigcm-alerts-label">{{ __('sigcm.patients.cards.active_allergies') }}</p>
                        @if ($activeAllergies->isNotEmpty())
                            <div class="sigcm-badge-list">
                                @foreach ($activeAllergies as $allergy)
                                    @php
                                        $severityColor = match ($allergy->severity) {
                                            'severe' => 'danger',
                                            'moderate' => 'warning',
                                            default => 'info',
                                        };
                                    @endphp
                                    <x-filament::badge :color="$severityColor">
                                        {{ $allergy->substance }}
                                        @if ($allergy->severity)
                                            ({{ __("enums.allergy_severity.{$allergy->severity}") }})
                                        @endif
                                    </x-filament::badge>
                                @endforeach
                            </div>
                        @else
                            <x-filament::badge color="gray">{{ __('sigcm.patients.no_allergies') }}</x-filament::badge>
                        @endif
                    </div>

                    <div>
                        <p class="sigcm-alerts-label">{{ __('sigcm.patients.cards.chronic_conditions') }}</p>
                        @if ($activeConditions->isNotEmpty())
                            <div class="sigcm-badge-list">
                                @foreach ($activeConditions->take(4) as $condition)
                                    <x-filament::badge color="warning">
                                        {{ $condition->code ? "{$condition->code} · " : '' }}{{ $condition->display }}
                                    </x-filament::badge>
                                @endforeach
                            </div>
                        @else
                            <x-filament::badge color="gray">{{ __('sigcm.patients.no_conditions') }}</x-filament::badge>
                        @endif
                    </div>
                </div>
            </x-filament::section>
        </div>
    @endif
</x-filament-widgets::widget>
