<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Prescription;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PrescriptionPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Prescription');
    }

    public function view(AuthUser $authUser, Prescription $prescription): bool
    {
        return $authUser->can('View:Prescription');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Prescription');
    }

    public function update(AuthUser $authUser, Prescription $prescription): bool
    {
        return $prescription->status === 'draft'
            && $authUser->can('Update:Prescription');
    }

    public function delete(AuthUser $authUser, Prescription $prescription): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }
}
