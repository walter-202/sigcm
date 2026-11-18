<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Patient;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PatientPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Patient');
    }

    public function view(AuthUser $authUser, Patient $patient): bool
    {
        return $authUser->can('View:Patient');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Patient');
    }

    public function update(AuthUser $authUser, Patient $patient): bool
    {
        return $authUser->can('Update:Patient');
    }

    public function delete(AuthUser $authUser, Patient $patient): bool
    {
        return $authUser->can('Delete:Patient');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Patient');
    }

    public function restore(AuthUser $authUser, Patient $patient): bool
    {
        return $authUser->can('Restore:Patient');
    }

    public function forceDelete(AuthUser $authUser, Patient $patient): bool
    {
        return $authUser->can('ForceDelete:Patient');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Patient');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Patient');
    }

    public function replicate(AuthUser $authUser, Patient $patient): bool
    {
        return $authUser->can('Replicate:Patient');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Patient');
    }
}
