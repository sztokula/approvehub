<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\UpdateOrganizationMemberRoleRequest;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Role;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Handles OrganizationMemberController responsibilities for the ApproveHub domain.
 */
class OrganizationMemberController extends Controller
{
    public function index(Organization $organization): View
    {
        $this->authorize('manageMembers', $organization);

        $members = $organization->memberships()
            ->with(['user:id,name,email', 'role:id,name'])
            ->orderBy('joined_at')
            ->get();

        return view('organizations.members', [
            'organization' => $organization,
            'members' => $members,
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
    }

    public function update(
        UpdateOrganizationMemberRoleRequest $request,
        Organization $organization,
        OrganizationUser $membership,
    ): RedirectResponse {
        $this->authorize('manageMembers', $organization);
        abort_if($membership->organization_id !== $organization->id, 404);

        $newRole = Role::query()->findOrFail((int) $request->validated('role_id'));

        $isSelf = $membership->user_id === $request->user()->id;
        if ($isSelf && $newRole->name !== UserRole::Admin) {
            throw new UnprocessableEntityHttpException('You cannot remove your own admin role.');
        }

        $membership->update([
            'role_id' => $newRole->id,
        ]);

        return redirect()
            ->route('organizations.members.index', $organization)
            ->with('status', 'Member role updated.');
    }
}
