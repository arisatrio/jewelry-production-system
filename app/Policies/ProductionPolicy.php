<?php

namespace App\Policies;

use App\Models\Production;
use App\Models\User;
use App\Support\SpkApprovalRoles;
use App\Support\SpkApprovalService;

class ProductionPolicy
{
    public function __construct(
        private SpkApprovalService $approval,
    ) {}

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Production $production): bool
    {
        return true;
    }

    public function create(?User $user): bool
    {
        return SpkApprovalRoles::canCreate($user) || SpkApprovalRoles::canEditDraft($user);
    }

    public function update(?User $user, Production $production): bool
    {
        return SpkApprovalRoles::canEditDraft($user);
    }

    public function delete(?User $user, Production $production): bool
    {
        return ! $this->approval->isApproved($production)
            && SpkApprovalRoles::canEditDraft($user);
    }

    public function submit(?User $user, Production $production): bool
    {
        return $this->approval->isDraft($production)
            && SpkApprovalRoles::canSubmit($user);
    }

    public function approve(?User $user, Production $production): bool
    {
        return $this->approval->isDraft($production)
            && SpkApprovalRoles::canApprove($user);
    }

    public function managerApprove(?User $user, Production $production): bool
    {
        return $this->approval->isPendingManager($production)
            && SpkApprovalRoles::canManagerApprove($user);
    }

    public function reject(?User $user, Production $production): bool
    {
        return $this->approval->isPendingManager($production)
            && SpkApprovalRoles::canReject($user);
    }
}
