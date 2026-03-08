<?php

namespace App\Policies;

use App\Enums\ApprovalStepStatus;
use App\Enums\UserRole;
use App\Models\ApprovalStep;
use App\Models\User;

/**
 * Handles ApprovalStepPolicy responsibilities for the ApproveHub domain.
 */
class ApprovalStepPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->organizations()->exists();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ApprovalStep $approvalStep): bool
    {
        $document = $approvalStep->workflow->documentVersion->document;

        if (! $user->organizations()->whereKey($document->organization_id)->exists()) {
            return false;
        }

        if ($approvalStep->assignees()->where('user_id', $user->id)->exists()) {
            return true;
        }

        if ($approvalStep->fallback_user_id === $user->id) {
            return true;
        }

        return $user->hasOrganizationRole($document->organization_id, UserRole::Admin);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ApprovalStep $approvalStep): bool
    {
        return $this->approve($user, $approvalStep);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ApprovalStep $approvalStep): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ApprovalStep $approvalStep): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ApprovalStep $approvalStep): bool
    {
        return false;
    }

    public function approve(User $user, ApprovalStep $approvalStep): bool
    {
        if ($approvalStep->status !== ApprovalStepStatus::Active) {
            return false;
        }

        if ($approvalStep->assignees()->where('user_id', $user->id)->exists()) {
            return true;
        }

        if ($approvalStep->fallback_user_id === $user->id) {
            return true;
        }

        if ($approvalStep->assignee_role === null) {
            return false;
        }

        $document = $approvalStep->workflow->documentVersion->document;
        $role = UserRole::tryFrom($approvalStep->assignee_role);

        if ($role === null) {
            return false;
        }

        return $user->hasOrganizationRole($document->organization_id, $role, UserRole::Admin);
    }

    public function reject(User $user, ApprovalStep $approvalStep): bool
    {
        return $this->approve($user, $approvalStep);
    }
}
