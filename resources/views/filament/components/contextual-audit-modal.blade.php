@include('filament.components.bd-table-styles')

<div class="sigcm-sql-wrap">
    <table class="sigcm-sql-table">
        <thead>
            <tr>
                <th>{{ __('sigcm.fields.id') }}</th>
                <th>{{ __('sigcm.fields.operation') }}</th>
                <th>{{ __('sigcm.fields.entity') }}</th>
                <th>{{ __('sigcm.fields.entity_id') }}</th>
                <th>{{ __('sigcm.fields.actor') }}</th>
                <th>{{ __('sigcm.fields.summary') }}</th>
                <th>{{ __('sigcm.fields.date_time') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($events as $event)
                @php
                    $opColor = match ($event->operation) {
                        'SIGN' => 'success',
                        'INSERT' => 'info',
                        'UPDATE' => 'warning',
                        'AMEND' => 'primary',
                        'DELETE' => 'danger',
                        default => 'gray',
                    };
                @endphp
                <tr>
                    <td><strong>#{{ $event->id }}</strong></td>
                    <td>
                        <x-filament::badge :color="$opColor">
                            {{ $event->operation }}
                        </x-filament::badge>
                    </td>
                    <td><span class="sigcm-code">{{ $event->entity_name }}</span></td>
                    <td>#{{ $event->entity_id }}</td>
                    <td>{{ $event->actor_label }}</td>
                    <td><span class="sigcm-code">{{ $event->changed_fields_json }}</span></td>
                    <td>{{ \Illuminate\Support\Carbon::parse($event->occurred_at)->format('d/m/Y H:i:s') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center;">{{ __('sigcm.audit.contextual_empty') }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
