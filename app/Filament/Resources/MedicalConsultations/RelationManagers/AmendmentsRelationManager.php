<?php

namespace App\Filament\Resources\MedicalConsultations\RelationManagers;

use App\Enums\ConsultationStatus;
use App\Models\MedicalConsultation;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AmendmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'amendments';

    protected static bool $isLazy = false;

    protected static ?string $modelLabel = 'Enmienda';

    protected static ?string $pluralModelLabel = 'Enmiendas';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('sigcm.clinical.amendments_tab');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('reason')
                    ->label(__('sigcm.fields.amendment_reason'))
                    ->required()
                    ->rows(2)
                    ->columnSpanFull(),
                Textarea::make('additional_note')
                    ->label(__('sigcm.fields.additional_note'))
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')
                    ->label(__('sigcm.fields.id'))
                    ->numeric(),
                TextEntry::make('author.name')
                    ->label(__('sigcm.fields.author')),
                TextEntry::make('reason')
                    ->label(__('sigcm.fields.reason'))
                    ->columnSpanFull(),
                TextEntry::make('additional_note')
                    ->label(__('sigcm.fields.additional_note'))
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->label(__('sigcm.fields.registered_at'))
                    ->dateTime('d/m/Y H:i'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reason')
            ->columns([
                TextColumn::make('id')
                    ->label(__('sigcm.fields.id'))
                    ->numeric()
                    ->sortable(),
                TextColumn::make('author.name')
                    ->label(__('sigcm.fields.author'))
                    ->searchable(),
                TextColumn::make('reason')
                    ->label(__('sigcm.fields.reason'))
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('additional_note')
                    ->label(__('sigcm.fields.additional_note'))
                    ->limit(60),
                TextColumn::make('created_at')
                    ->label(__('sigcm.fields.date'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label(__('sigcm.consultation.actions.amend'))
                    ->visible(function (): bool {
                        $owner = $this->getOwnerRecord();

                        return $owner instanceof MedicalConsultation
                            && $owner->status === ConsultationStatus::Signed;
                    })
                    ->mutateDataUsing(function (array $data): array {
                        $data['author_user_id'] = Auth::id();
                        $data['created_at'] = now();

                        return $data;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
