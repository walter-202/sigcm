<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ConsultationStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Signed = 'signed';

    public function getLabel(): string
    {
        return __("enums.consultation_status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'warning',
            self::Signed => 'success',
        };
    }
}
