<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EpisodeStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Resolved = 'resolved';
    case Suspended = 'suspended';

    public function getLabel(): string
    {
        return __("enums.episode_status.{$this->value}");
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'info',
            self::Resolved => 'success',
            self::Suspended => 'warning',
        };
    }
}
