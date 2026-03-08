<?php

namespace App\Http\Controllers;

use App\Actions\Audit\RecordAuditLogAction;
use App\Enums\UserRole;
use App\Http\Requests\StoreWorkflowTemplateRequest;
use App\Http\Requests\UpdateWorkflowTemplateRequest;
use App\Models\Organization;
use App\Models\WorkflowTemplate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Handles WorkflowTemplateController responsibilities for the ApproveHub domain.
 */
class WorkflowTemplateController extends Controller
{
    public function index(Organization $organization): View
    {
        $this->authorize('manageWorkflowTemplates', $organization);

        $workflowTemplates = $organization->workflowTemplates()
            ->with(['steps.assigneeUser:id,name', 'steps.fallbackUser:id,name'])
            ->orderByDesc('is_default')
            ->orderBy('document_type')
            ->orderBy('name')
            ->get();

        $organizationUsers = $organization->memberships()
            ->with('user:id,name')
            ->orderBy('joined_at')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->values();

        return view('organizations.workflow-templates', [
            'organization' => $organization,
            'workflowTemplates' => $workflowTemplates,
            'organizationUsers' => $organizationUsers,
            'roleOptions' => UserRole::cases(),
        ]);
    }

    public function store(
        StoreWorkflowTemplateRequest $request,
        Organization $organization,
        RecordAuditLogAction $recordAuditLogAction,
    ): RedirectResponse {
        $this->authorize('manageWorkflowTemplates', $organization);
        $validated = $request->validated();

        DB::transaction(function () use ($organization, $validated, $request, $recordAuditLogAction): void {
            if ((bool) ($validated['is_default'] ?? false)) {
                $organization->workflowTemplates()
                    ->where('document_type', $validated['document_type'])
                    ->update(['is_default' => false]);
            }

            $workflowTemplate = $organization->workflowTemplates()->create([
                'name' => $validated['name'],
                'document_type' => $validated['document_type'],
                'is_default' => (bool) ($validated['is_default'] ?? false),
            ]);

            foreach (collect($validated['steps'])->sortBy('step_order') as $step) {
                $workflowTemplate->steps()->create([
                    'step_order' => $step['step_order'],
                    'name' => $step['name'],
                    'assignee_type' => $step['assignee_type'],
                    'assignee_role' => $step['assignee_role'],
                    'assignee_user_id' => $step['assignee_user_id'],
                    'fallback_user_id' => $step['fallback_user_id'],
                    'due_in_hours' => $step['due_in_hours'],
                ]);
            }

            $recordAuditLogAction->execute(
                organizationId: $organization->id,
                actor: $request->user(),
                action: 'workflow_template.created',
                targetType: 'workflow_template',
                targetId: $workflowTemplate->id,
                metadata: [
                    'document_type' => $workflowTemplate->document_type,
                    'steps_count' => $workflowTemplate->steps()->count(),
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );
        });

        return redirect()
            ->route('organizations.workflow-templates.index', $organization)
            ->with('status', 'Workflow template created.');
    }

    public function update(
        UpdateWorkflowTemplateRequest $request,
        Organization $organization,
        WorkflowTemplate $workflowTemplate,
        RecordAuditLogAction $recordAuditLogAction,
    ): RedirectResponse {
        $this->authorize('manageWorkflowTemplates', $organization);
        abort_if($workflowTemplate->organization_id !== $organization->id, 404);
        $validated = $request->validated();

        DB::transaction(function () use ($organization, $workflowTemplate, $validated, $request, $recordAuditLogAction): void {
            if ((bool) ($validated['is_default'] ?? false)) {
                $organization->workflowTemplates()
                    ->where('document_type', $validated['document_type'])
                    ->whereKeyNot($workflowTemplate->id)
                    ->update(['is_default' => false]);
            }

            $workflowTemplate->update([
                'name' => $validated['name'],
                'document_type' => $validated['document_type'],
                'is_default' => (bool) ($validated['is_default'] ?? false),
            ]);

            $workflowTemplate->steps()->delete();

            foreach (collect($validated['steps'])->sortBy('step_order') as $step) {
                $workflowTemplate->steps()->create([
                    'step_order' => $step['step_order'],
                    'name' => $step['name'],
                    'assignee_type' => $step['assignee_type'],
                    'assignee_role' => $step['assignee_role'],
                    'assignee_user_id' => $step['assignee_user_id'],
                    'fallback_user_id' => $step['fallback_user_id'],
                    'due_in_hours' => $step['due_in_hours'],
                ]);
            }

            $recordAuditLogAction->execute(
                organizationId: $organization->id,
                actor: $request->user(),
                action: 'workflow_template.updated',
                targetType: 'workflow_template',
                targetId: $workflowTemplate->id,
                metadata: [
                    'document_type' => $workflowTemplate->document_type,
                    'steps_count' => $workflowTemplate->steps()->count(),
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );
        });

        return redirect()
            ->route('organizations.workflow-templates.index', $organization)
            ->with('status', 'Workflow template updated.');
    }

    public function destroy(
        Request $request,
        Organization $organization,
        WorkflowTemplate $workflowTemplate,
        RecordAuditLogAction $recordAuditLogAction,
    ): RedirectResponse {
        $this->authorize('manageWorkflowTemplates', $organization);
        abort_if($workflowTemplate->organization_id !== $organization->id, 404);

        DB::transaction(function () use ($organization, $workflowTemplate, $recordAuditLogAction, $request): void {
            $recordAuditLogAction->execute(
                organizationId: $organization->id,
                actor: $request->user(),
                action: 'workflow_template.deleted',
                targetType: 'workflow_template',
                targetId: $workflowTemplate->id,
                metadata: [
                    'name' => $workflowTemplate->name,
                    'document_type' => $workflowTemplate->document_type,
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );

            $workflowTemplate->delete();
        });

        return redirect()
            ->route('organizations.workflow-templates.index', $organization)
            ->with('status', 'Workflow template deleted.');
    }
}
