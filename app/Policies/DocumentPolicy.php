<?php

namespace App\Policies;

use App\Enums\DocumentStatus;
use App\Enums\DocumentVisibility;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\User;

/**
 * Handles DocumentPolicy responsibilities for the ApproveHub domain.
 */
class DocumentPolicy
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
    public function view(User $user, Document $document): bool
    {
        if (! $user->organizations()->whereKey($document->organization_id)->exists()) {
            return false;
        }

        if ($user->id === $document->owner_id) {
            return true;
        }

        if ($user->hasOrganizationRole($document->organization_id, UserRole::Admin)) {
            return true;
        }

        if ($document->permissions()
            ->where('user_id', $user->id)
            ->whereIn('permission', ['view', 'review'])
            ->exists()) {
            return true;
        }

        if ($document->visibility === DocumentVisibility::Organization) {
            return true;
        }

        return $document->versions()
            ->whereHas('workflow.steps.assignees', fn ($query) => $query->where('user_id', $user->id))
            ->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->organizationMemberships()
            ->whereHas('role', fn ($query) => $query->whereIn('name', [UserRole::Admin->value, UserRole::Editor->value]))
            ->exists();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Document $document): bool
    {
        if (! in_array($document->status, [DocumentStatus::Draft, DocumentStatus::Rejected], true)) {
            return false;
        }

        if (! $user->organizations()->whereKey($document->organization_id)->exists()) {
            return false;
        }

        if ($user->id === $document->owner_id) {
            return true;
        }

        return $user->hasOrganizationRole($document->organization_id, UserRole::Admin, UserRole::Editor);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Document $document): bool
    {
        return $user->hasOrganizationRole($document->organization_id, UserRole::Admin);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Document $document): bool
    {
        return $user->hasOrganizationRole($document->organization_id, UserRole::Admin);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Document $document): bool
    {
        return false;
    }

    public function submitForReview(User $user, Document $document): bool
    {
        if (! in_array($document->status, [DocumentStatus::Draft, DocumentStatus::Rejected], true)) {
            return false;
        }

        if ($user->id === $document->owner_id) {
            return true;
        }

        return $user->hasOrganizationRole($document->organization_id, UserRole::Admin, UserRole::Editor);
    }

    public function manageShareLinks(User $user, Document $document): bool
    {
        if (! $user->organizations()->whereKey($document->organization_id)->exists()) {
            return false;
        }

        if ($user->id === $document->owner_id) {
            return true;
        }

        return $user->hasOrganizationRole($document->organization_id, UserRole::Admin, UserRole::Editor);
    }

    public function archive(User $user, Document $document): bool
    {
        return $user->hasOrganizationRole($document->organization_id, UserRole::Admin);
    }

    public function updateVisibility(User $user, Document $document): bool
    {
        if (! $user->organizations()->whereKey($document->organization_id)->exists()) {
            return false;
        }

        if ($user->id === $document->owner_id) {
            return true;
        }

        return $user->hasOrganizationRole($document->organization_id, UserRole::Admin, UserRole::Editor);
    }

    public function managePermissions(User $user, Document $document): bool
    {
        if (! $user->organizations()->whereKey($document->organization_id)->exists()) {
            return false;
        }

        if ($user->id === $document->owner_id) {
            return true;
        }

        return $user->hasOrganizationRole($document->organization_id, UserRole::Admin, UserRole::Editor);
    }
}
