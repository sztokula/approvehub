<?php

namespace App\Models;

use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * Handles User responsibilities for the ApproveHub domain.
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->using(OrganizationUser::class)
            ->withPivot(['role_id', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<OrganizationUser, $this>
     */
    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationUser::class);
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function ownedDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'owner_id');
    }

    /**
     * @return HasMany<DocumentVersion, $this>
     */
    public function createdVersions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'created_by');
    }

    /**
     * @return HasMany<ApprovalWorkflow, $this>
     */
    public function submittedWorkflows(): HasMany
    {
        return $this->hasMany(ApprovalWorkflow::class, 'submitted_by');
    }

    /**
     * @return HasMany<ApprovalDecision, $this>
     */
    public function approvalDecisions(): HasMany
    {
        return $this->hasMany(ApprovalDecision::class, 'actor_id');
    }

    /**
     * @return HasMany<DocumentPermission, $this>
     */
    public function documentPermissions(): HasMany
    {
        return $this->hasMany(DocumentPermission::class);
    }

    public function roleInOrganization(int $organizationId): ?UserRole
    {
        $membership = $this->organizationMemberships()
            ->with('role')
            ->where('organization_id', $organizationId)
            ->first();

        if (! ($membership?->role?->name instanceof UserRole)) {
            return null;
        }

        return $membership->role->name;
    }

    public function hasOrganizationRole(int $organizationId, UserRole ...$roles): bool
    {
        $role = $this->roleInOrganization($organizationId);

        if ($role === null) {
            return false;
        }

        return in_array($role, $roles, true);
    }
}
