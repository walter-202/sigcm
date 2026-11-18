<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use App\Filament\Resources\MedicalConsultations\MedicalConsultationResource;
use App\Models\Referral;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PatientReferralsRelationManager extends RelationManager
{
    protected static string $relationship = 'referrals';

    protected static bool $isLazy = false;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('sigcm.clinical.referrals_tab');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('specialty_or_service')
            ->columns([
                TextColumn::make('id')
                    ->label(__('sigcm.fields.id'))
                    ->formatStateUsing(fn ($state): string => "INT-#{$state}")
                    ->sortable(),
                TextColumn::make('consultation_id')
                    ->label(__('sigcm.fields.consultation'))
                    ->formatStateUsing(fn ($state): string => "#{$state}")
                    ->url(fn (Referral $record): string => MedicalConsultationResource::getUrl('view', ['record' => $record->consultation_id])),
                TextColumn::make('specialty_or_service')
                    ->label(__('sigcm.fields.specialty_or_service'))
                    ->searchable(),
                TextColumn::make('reason')
                    ->label(__('sigcm.fields.reason'))
                    ->limit(45),
                TextColumn::make('status')
                    ->label(__('sigcm.fields.status'))
                    ->badge(),
                TextColumn::make('requested_at')
                    ->label(__('sigcm.fields.requested_at'))
                    ->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('referrals.id', 'desc')
            ->recordActions([
                Action::make('abrirConsulta')
                    ->label(__('sigcm.actions.view_detail'))
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (Referral $record): string => MedicalConsultationResource::getUrl('view', ['record' => $record->consultation_id])),
            ]);
    }
}
