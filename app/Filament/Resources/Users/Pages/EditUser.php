<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Doctor;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $userId = $this->record->getKey();
        $doctorId = $this->data['linked_doctor_id'] ?? null;

        DB::transaction(function () use ($userId, $doctorId): void {
            Doctor::query()
                ->where('user_id', $userId)
                ->when(filled($doctorId), fn ($query) => $query->where('id', '!=', (int) $doctorId))
                ->update(['user_id' => null]);

            if (filled($doctorId)) {
                Doctor::query()
                    ->where('id', (int) $doctorId)
                    ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $userId))
                    ->update(['user_id' => $userId]);
            }
        });
    }
}
