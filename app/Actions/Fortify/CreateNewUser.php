<?php

namespace App\Actions\Fortify;

use App\Actions\Workflows\EnsureOrganizationWorkflowTemplatesAction;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Handles CreateNewUser responsibilities for the ApproveHub domain.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(
        private readonly EnsureOrganizationWorkflowTemplatesAction $ensureOrganizationWorkflowTemplatesAction,
    ) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'organization_name' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $this->ensureDefaultRoles();

            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $organization = Organization::query()->create([
                'name' => $input['organization_name'],
                'slug' => $this->uniqueOrganizationSlug($input['organization_name']),
            ]);

            $this->ensureOrganizationWorkflowTemplatesAction->execute($organization);

            $adminRole = Role::query()->where('name', UserRole::Admin)->firstOrFail();

            $user->organizations()->attach($organization->id, [
                'role_id' => $adminRole->id,
                'joined_at' => now(),
            ]);

            return $user;
        });
    }

    private function ensureDefaultRoles(): void
    {
        foreach (UserRole::cases() as $role) {
            Role::query()->firstOrCreate([
                'name' => $role,
            ]);
        }
    }

    private function uniqueOrganizationSlug(string $name): string
    {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'organization';
        }

        $slug = $baseSlug;
        $suffix = 1;

        while (Organization::query()->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = "{$baseSlug}-{$suffix}";
        }

        return $slug;
    }
}
