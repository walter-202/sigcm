<?php

namespace App\Filament\Resources\AuditEvents\Schemas;

use App\Models\AuditEvent;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AuditEventInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detalle del evento de auditoría')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('id')
                            ->label(__('sigcm.fields.id'))
                            ->formatStateUsing(fn ($state): string => "#{$state}"),
                        TextEntry::make('operation')
                            ->label(__('sigcm.fields.operation'))
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'SIGN' => 'success',
                                'INSERT' => 'info',
                                'UPDATE' => 'warning',
                                'AMEND' => 'primary',
                                'DELETE' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('occurred_at')
                            ->label(__('sigcm.fields.date_time'))
                            ->dateTime('d/m/Y H:i:s'),
                        TextEntry::make('entity_name')
                            ->label(__('sigcm.fields.entity'))
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('entity_id')
                            ->label(__('sigcm.fields.entity_id'))
                            ->formatStateUsing(fn ($state): string => "#{$state}"),
                        TextEntry::make('actor_display')
                            ->label(__('sigcm.fields.actor'))
                            ->state(fn (AuditEvent $record): string => $record->actor
                                ? "{$record->actor->name} ({$record->actor->email})"
                                : 'Sistema / Proceso interno'),
                    ]),

                Section::make('Metadatos registrados')
                    ->columnSpanFull()
                    ->schema([
                        KeyValueEntry::make('changed_fields')
                            ->label(__('sigcm.fields.changed_fields'))
                            ->keyLabel(__('sigcm.fields.attribute'))
                            ->valueLabel(__('sigcm.fields.value'))
                            ->state(function (AuditEvent $record): array {
                                return collect($record->changed_fields ?? [])
                                    ->mapWithKeys(function (mixed $value, string $key): array {
                                        if (is_array($value)) {
                                            return [$key => json_encode($value, JSON_UNESCAPED_UNICODE) ?: '-'];
                                        }

                                        if (is_bool($value)) {
                                            return [$key => $value ? 'Sí' : 'No'];
                                        }

                                        return [$key => $value === null ? '-' : (string) $value];
                                    })
                                    ->all();
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
