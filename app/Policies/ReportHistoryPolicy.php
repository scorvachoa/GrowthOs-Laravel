<?php

namespace App\Policies;

use App\Models\ReportHistory;
use App\Models\User;

class ReportHistoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view reports');
    }

    public function view(User $user, ReportHistory $reportHistory): bool
    {
        return $user->can('view reports')
            && $this->inOrg($user, $reportHistory)
            && $this->isOwnerOrAdmin($user, $reportHistory);
    }

    public function create(User $user): bool
    {
        return $user->can('view reports');
    }

    public function download(User $user, ReportHistory $reportHistory): bool
    {
        return $user->can('download reports')
            && $this->inOrg($user, $reportHistory)
            && $this->isOwnerOrAdmin($user, $reportHistory);
    }

    public function delete(User $user, ReportHistory $reportHistory): bool
    {
        return $user->can('delete reports')
            && $this->inOrg($user, $reportHistory)
            && $this->isOwnerOrAdmin($user, $reportHistory);
    }

    private function inOrg(User $user, ReportHistory $reportHistory): bool
    {
        return $reportHistory->organization_id === $user->activeOrganizationId();
    }

    private function isOwnerOrAdmin(User $user, ReportHistory $reportHistory): bool
    {
        return $user->hasRole(['Super Admin', 'Admin'])
            || $reportHistory->user_id === $user->id;
    }
}
