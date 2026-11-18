<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\Doctor;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    public static function getNavigationLabel(): string
    {
        return __('sigcm.spotlight.create');
    }

    protected function afterCreate(): void
    {
        $doctorId = $this->data['linked_doctor_id'] ?? null;

        if (filled($doctorId)) {
            DB::transaction(function () use ($doctorId): void {
                Doctor::query()
                    ->where('id', (int) $doctorId)
                    ->whereNull('user_id')
                    ->update(['user_id' => $this->record->getKey()]);
            });
        }
    }
}
