<?php

namespace App\Filament\Resources\AuditEvents\Tables;

use App\Filament\Pages\BitacoraAuditoriaPage;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class AuditEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label(__('sigcm.fields.id'))
                    ->formatStateUsing(fn ($state): string => "#{$state}")
                    ->sortable(),
                TextColumn::make('operation')
                    ->label(__('sigcm.fields.operation'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'SIGN' => 'success',
                        'INSERT' => 'info',
                        'UPDATE' => 'warning',
                        'AMEND' => 'primary',
                        'DELETE' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('entity_name')
                    ->label(__('sigcm.fields.entity'))
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('entity_id')
                    ->label(__('sigcm.fields.entity_id'))
                    ->formatStateUsing(fn ($state): string => "#{$state}")
                    ->searchable()
                    ->sortable(),
                TextColumn::make('actor.email')
                    ->label(__('sigcm.fields.actor'))
                    ->placeholder('Sistema / Interno')
                    ->searchable(),
                TextColumn::make('changed_fields')
                    ->label(__('sigcm.fields.summary'))
                    ->formatStateUsing(fn (mixed $state): string => is_array($state) ? (json_encode($state, JSON_UNESCAPED_UNICODE) ?: '{}') : (string) $state)
                    ->limit(55)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereRaw('changed_fields::text ILIKE ?', ["%{$search}%"]);
                    }),
                TextColumn::make('occurred_at')
                    ->label(__('sigcm.fields.date_time'))
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('entity_name')
                    ->label(__('sigcm.fields.entity'))
                    ->options([
                        'patients' => 'Pacientes (patients)',
                        'appointments' => 'Citas (appointments)',
                        'medical_consultations' => 'Consultas (medical_consultations)',
                        'clinical_records' => 'Historias clínicas (clinical_records)',
                        'care_episodes' => 'Episodios (care_episodes)',
                        'patient_allergies' => 'Alergias (patient_allergies)',
                        'patient_conditions' => 'Condiciones (patient_conditions)',
                        'prescriptions' => 'Recetas (prescriptions)',
                        'diagnostic_reports' => 'Informes (diagnostic_reports)',
                        'clinical_documents' => 'Documentos (clinical_documents)',
                        'consultation_amendments' => 'Enmiendas (consultation_amendments)',
                    ]),
                SelectFilter::make('operation')
                    ->label(__('sigcm.fields.operation'))
                    ->options([
                        'INSERT' => 'INSERT',
                        'UPDATE' => 'UPDATE',
                        'SIGN' => 'SIGN',
                        'AMEND' => 'AMEND',
                        'DELETE' => 'DELETE',
                    ]),
                SelectFilter::make('actor_user_id')
                    ->label(__('sigcm.fields.actor'))
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('email', 'id')->all()),
                Filter::make('occurred_at')
                    ->label(__('sigcm.fields.date_range'))
                    ->schema([
                        DatePicker::make('desde')->label(__('sigcm.fields.from_date')),
                        DatePicker::make('hasta')->label(__('sigcm.fields.until_date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['desde'] ?? null, fn (Builder $sub, string $date): Builder => $sub->whereDate('occurred_at', '>=', $date))
                            ->when($data['hasta'] ?? null, fn (Builder $sub, string $date): Builder => $sub->whereDate('occurred_at', '<=', $date));
                    }),
            ])
            ->headerActions([
                Action::make('diagnosticoSeguridad')
                    ->label(__('sigcm.actions.dcl_diagnostics'))
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->color('gray')
                    ->url(fn (): string => BitacoraAuditoriaPage::getUrl()),
                ExportAction::make('exportarAuditoria')
                    ->label(__('sigcm.actions.export_excel'))
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->withFilename('auditoria-sigcm-'.date('Y-m-d')),
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
