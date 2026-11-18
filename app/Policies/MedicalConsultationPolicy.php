<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ConsultationStatus;
use App\Models\MedicalConsultation;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class MedicalConsultationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MedicalConsultation');
    }

    public function view(AuthUser $authUser, MedicalConsultation $medicalConsultation): bool
    {
        return $authUser->can('View:MedicalConsultation');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MedicalConsultation');
    }

    public function update(AuthUser $authUser, MedicalConsultation $medicalConsultation): bool
    {
        return $medicalConsultation->status !== ConsultationStatus::Signed
            && $authUser->can('Update:MedicalConsultation');
    }

    public function delete(AuthUser $authUser, MedicalConsultation $medicalConsultation): bool
    {
        return $medicalConsultation->status !== ConsultationStatus::Signed
            && $authUser->can('Delete:MedicalConsultation');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:MedicalConsultation');
    }

    public function restore(AuthUser $authUser, MedicalConsultation $medicalConsultation): bool
    {
        return $authUser->can('Restore:MedicalConsultation');
    }

    public function forceDelete(AuthUser $authUser, MedicalConsultation $medicalConsultation): bool
    {
        return $authUser->can('ForceDelete:MedicalConsultation');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MedicalConsultation');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MedicalConsultation');
    }

    public function replicate(AuthUser $authUser, MedicalConsultation $medicalConsultation): bool
    {
        return $authUser->can('Replicate:MedicalConsultation');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MedicalConsultation');
    }
}
