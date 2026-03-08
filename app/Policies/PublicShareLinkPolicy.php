<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\PublicShareLink;
use App\Models\User;

/**
 * Handles PublicShareLinkPolicy responsibilities for the ApproveHub domain.
 */
class PublicShareLinkPolicy
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
    public function view(User $user, PublicShareLink $publicShareLink): bool
    {
        return $user->can('view', $publicShareLink->document);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->organizations()->exists();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PublicShareLink $publicShareLink): bool
    {
        return $this->delete($user, $publicShareLink);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PublicShareLink $publicShareLink): bool
    {
        $document = $publicShareLink->document;

        if (! $user->organizations()->whereKey($document->organization_id)->exists()) {
            return false;
        }

        if ($document->owner_id === $user->id) {
            return true;
        }

        return $user->hasOrganizationRole($document->organization_id, UserRole::Admin, UserRole::Editor);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PublicShareLink $publicShareLink): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PublicShareLink $publicShareLink): bool
    {
        return false;
    }
}
