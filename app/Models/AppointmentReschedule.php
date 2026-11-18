<?php

namespace App\Models;

use Database\Factories\AppointmentRescheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'appointment_id',
    'changed_by_user_id',
    'previous_start',
    'previous_end',
    'new_start',
    'new_end',
    'reason',
    'created_at',
])]
class AppointmentReschedule extends Model
{
    /** @use HasFactory<AppointmentRescheduleFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'previous_start' => 'datetime',
            'previous_end' => 'datetime',
            'new_start' => 'datetime',
            'new_end' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
