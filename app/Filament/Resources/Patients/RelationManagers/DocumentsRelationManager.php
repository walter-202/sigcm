<?php

namespace App\Filament\Resources\Patients\RelationManagers;

use App\Models\ClinicalDocument;
use App\Models\MedicalConsultation;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static bool $isLazy = false;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('sigcm.patients.documents_tab');
    }

    public function isReadOnly(): bool
    {
        return ! (auth()->user()?->can('update', $this->getOwnerRecord()) ?? false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('document_type')
                    ->label(__('sigcm.fields.document_type_doc'))
                    ->datalist([
                        'informe_laboratorio',
                        'estudio_imagen',
                        'consentimiento_informado',
                        'epicrisis_externa',
                        'identificacion',
                    ])
                    ->required()
                    ->maxLength(60),
                Select::make('consultation_id')
                    ->label(__('sigcm.fields.optional_consultation'))
                    ->options(fn (): array => MedicalConsultation::query()
                        ->where('patient_id', $this->getOwnerRecord()->getKey())
                        ->orderByDesc('occurred_at')
                        ->get(['id', 'chief_complaint', 'occurred_at'])
                        ->mapWithKeys(fn (MedicalConsultation $consultation): array => [
                            $consultation->id => "#{$consultation->id} · {$consultation->occurred_at?->format('d/m/Y')} — {$consultation->chief_complaint}",
                        ])
                        ->all())
                    ->searchable()
                    ->nullable(),
                FileUpload::make('storage_path')
                    ->label(__('sigcm.fields.clinical_file'))
                    ->disk('local')
                    ->directory('clinical-documents')
                    ->visibility('private')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(10240)
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('document_type')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['uploadedBy', 'consultation']))
            ->columns([
                TextColumn::make('id')
                    ->label(__('sigcm.fields.id'))
                    ->formatStateUsing(fn ($state): string => "DOC-#{$state}")
                    ->sortable(),
                TextColumn::make('document_type')
                    ->label(__('sigcm.fields.document_type_doc'))
                    ->badge()
                    ->searchable(),
                TextColumn::make('consultation_id')
                    ->label(__('sigcm.fields.consultation'))
                    ->formatStateUsing(fn ($state): string => $state ? "#{$state}" : 'Expediente general')
                    ->placeholder('Expediente general'),
                TextColumn::make('mime_type')
                    ->label(__('sigcm.fields.mime_type'))
                    ->toggleable(),
                TextColumn::make('uploadedBy.name')
                    ->label(__('sigcm.fields.uploaded_by')),
                TextColumn::make('uploaded_at')
                    ->label(__('sigcm.fields.date'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label(__('sigcm.actions.attach_document'))
                    ->mutateDataUsing(function (array $data): array {
                        $path = (string) ($data['storage_path'] ?? '');
                        $mime = $path !== '' && Storage::disk('local')->exists($path)
                            ? (Storage::disk('local')->mimeType($path) ?: 'application/octet-stream')
                            : 'application/octet-stream';

                        return [
                            ...$data,
                            'mime_type' => $mime,
                            'uploaded_by_user_id' => Auth::id(),
                            'uploaded_at' => now(),
                            'created_at' => now(),
                        ];
                    }),
            ])
            ->recordActions([
                Action::make('descargar')
                    ->label(__('sigcm.actions.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn (ClinicalDocument $record): bool => Storage::disk('local')->exists($record->storage_path))
                    ->action(fn (ClinicalDocument $record) => Storage::disk('local')->download($record->storage_path)),
            ]);
    }
}
