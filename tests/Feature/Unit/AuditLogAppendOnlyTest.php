<?php

use App\Actions\Audit\RecordAuditLogAction;
use App\Models\Organization;
use App\Models\User;

it('prevents updating and deleting audit logs', function (): void {
    $organization = Organization::factory()->create();
    $actor = User::factory()->create();

    $auditLog = app(RecordAuditLogAction::class)->execute(
        organizationId: $organization->id,
        actor: $actor,
        action: 'document.created',
        targetType: 'document',
        targetId: 1,
    );

    expect(fn () => $auditLog->update(['action' => 'document.updated']))
        ->toThrow(\LogicException::class);

    expect(fn () => $auditLog->delete())
        ->toThrow(\LogicException::class);
});
