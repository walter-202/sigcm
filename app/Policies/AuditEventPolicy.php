<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AuditEvent;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AuditEventPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AuditEvent');
    }

    public function view(AuthUser $authUser, AuditEvent $auditEvent): bool
    {
        return $authUser->can('View:AuditEvent');
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, AuditEvent $auditEvent): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, AuditEvent $auditEvent): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }
}
