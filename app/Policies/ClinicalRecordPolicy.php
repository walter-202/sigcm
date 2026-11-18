<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ClinicalRecord;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class ClinicalRecordPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ClinicalRecord');
    }

    public function view(AuthUser $authUser, ClinicalRecord $clinicalRecord): bool
    {
        return $authUser->can('View:ClinicalRecord');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ClinicalRecord');
    }

    public function update(AuthUser $authUser, ClinicalRecord $clinicalRecord): bool
    {
        return $authUser->can('Update:ClinicalRecord');
    }

    public function delete(AuthUser $authUser, ClinicalRecord $clinicalRecord): bool
    {
        return $authUser->can('Delete:ClinicalRecord');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ClinicalRecord');
    }

    public function restore(AuthUser $authUser, ClinicalRecord $clinicalRecord): bool
    {
        return $authUser->can('Restore:ClinicalRecord');
    }

    public function forceDelete(AuthUser $authUser, ClinicalRecord $clinicalRecord): bool
    {
        return $authUser->can('ForceDelete:ClinicalRecord');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ClinicalRecord');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ClinicalRecord');
    }

    public function replicate(AuthUser $authUser, ClinicalRecord $clinicalRecord): bool
    {
        return $authUser->can('Replicate:ClinicalRecord');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ClinicalRecord');
    }
}
