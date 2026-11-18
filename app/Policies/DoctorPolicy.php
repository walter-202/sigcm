<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Doctor;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class DoctorPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Doctor');
    }

    public function view(AuthUser $authUser, Doctor $doctor): bool
    {
        return $authUser->can('View:Doctor');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Doctor');
    }

    public function update(AuthUser $authUser, Doctor $doctor): bool
    {
        return $authUser->can('Update:Doctor');
    }

    public function delete(AuthUser $authUser, Doctor $doctor): bool
    {
        return $authUser->can('Delete:Doctor');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Doctor');
    }

    public function restore(AuthUser $authUser, Doctor $doctor): bool
    {
        return $authUser->can('Restore:Doctor');
    }

    public function forceDelete(AuthUser $authUser, Doctor $doctor): bool
    {
        return $authUser->can('ForceDelete:Doctor');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Doctor');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Doctor');
    }

    public function replicate(AuthUser $authUser, Doctor $doctor): bool
    {
        return $authUser->can('Replicate:Doctor');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Doctor');
    }
}
