<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Appointment;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AppointmentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Appointment');
    }

    public function view(AuthUser $authUser, Appointment $appointment): bool
    {
        return $authUser->can('View:Appointment');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Appointment');
    }

    public function update(AuthUser $authUser, Appointment $appointment): bool
    {
        return $authUser->can('Update:Appointment');
    }

    public function delete(AuthUser $authUser, Appointment $appointment): bool
    {
        return $authUser->can('Delete:Appointment');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Appointment');
    }

    public function restore(AuthUser $authUser, Appointment $appointment): bool
    {
        return $authUser->can('Restore:Appointment');
    }

    public function forceDelete(AuthUser $authUser, Appointment $appointment): bool
    {
        return $authUser->can('ForceDelete:Appointment');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Appointment');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Appointment');
    }

    public function replicate(AuthUser $authUser, Appointment $appointment): bool
    {
        return $authUser->can('Replicate:Appointment');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Appointment');
    }
}
