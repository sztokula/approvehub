<?php

namespace App\Http\Controllers;

use App\Actions\Documents\CreateDocumentAction;
use App\Enums\DocumentStatus;
use App\Enums\DocumentVisibility;
use App\Http\Requests\StoreDocumentRequest;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Handles DocumentController responsibilities for the ApproveHub domain.
 */
class DocumentController extends Controller
{
    public function index(Request $request): JsonResponse|View
    {
        $user = $request->user();
        $organizationIds = $user->organizations()->pluck('organizations.id');
        $status = $request->string('status')->toString();
        $ownerId = $request->integer('owner_id');
        $reviewerId = $request->integer('reviewer_id');
        $updatedFrom = $request->date('updated_from');
        $updatedTo = $request->date('updated_to');

        $statusEnum = DocumentStatus::tryFrom($status);

        $documents = Document::query()
            ->with(['owner:id,name', 'currentVersion:id,document_id,version_number'])
            ->whereIn('organization_id', $organizationIds)
            ->when($statusEnum !== null, fn ($query) => $query->where('status', $statusEnum))
            ->when($ownerId > 0, fn ($query) => $query->where('owner_id', $ownerId))
            ->when($reviewerId > 0, function ($query) use ($reviewerId): void {
                $query->whereHas('versions.workflow.steps.assignees', function ($assignees) use ($reviewerId): void {
                    $assignees->where('user_id', $reviewerId);
                });
            })
            ->when($updatedFrom !== null, fn ($query) => $query->whereDate('updated_at', '>=', $updatedFrom->toDateString()))
            ->when($updatedTo !== null, fn ($query) => $query->whereDate('updated_at', '<=', $updatedTo->toDateString()))
            ->latest('updated_at')
            ->paginate(15);

        if (! $request->expectsJson()) {
            $organizationUsers = User::query()
                ->whereHas('organizationMemberships', fn ($query) => $query->whereIn('organization_id', $organizationIds))
                ->orderBy('name')
                ->get(['id', 'name']);

            return view('documents.index', [
                'documents' => $documents,
                'organizations' => $user->organizations()->get(['organizations.id', 'organizations.name']),
                'statusFilter' => $status,
                'statusOptions' => DocumentStatus::cases(),
                'ownerFilter' => $ownerId,
                'reviewerFilter' => $reviewerId,
                'updatedFromFilter' => $updatedFrom?->toDateString(),
                'updatedToFilter' => $updatedTo?->toDateString(),
                'organizationUsers' => $organizationUsers,
            ]);
        }

        return response()->json($documents);
    }

    public function show(
        Document $document,
        Request $request,
    ): JsonResponse|View {
        $this->authorize('view', $document);

        $document->load([
            'organization:id,name',
            'owner:id,name',
            'currentVersion',
            'versions.creator:id,name',
            'versions.workflow.steps.assignees.user:id,name',
            'comments.user:id,name',
            'attachments.uploader:id,name',
            'publicShareLinks.creator:id,name',
            'currentVersion.workflow.steps.assignees.user:id,name',
            'permissions.user:id,name,email',
        ]);

        $auditLogs = AuditLog::query()
            ->with('actor:id,name')
            ->where('organization_id', $document->organization_id)
            ->where(function ($query) use ($document): void {
                $query->where(function ($direct) use ($document): void {
                    $direct->where('target_type', 'document')
                        ->where('target_id', $document->id);
                })->orWhereRaw("json_extract(metadata, '$.document_id') = ?", [$document->id]);
            })
            ->latest('occurred_at')
            ->limit(50)
            ->get();

        $workflowTemplates = $document->organization->workflowTemplates()
            ->where(function ($query) use ($document): void {
                $query->where('document_type', $document->document_type)
                    ->orWhere('document_type', 'general');
            })
            ->with('steps')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        if (! $request->expectsJson()) {
            $organizationUsers = User::query()
                ->whereHas('organizationMemberships', fn ($query) => $query->where('organization_id', $document->organization_id))
                ->orderBy('name')
                ->get(['id', 'name']);

            return view('documents.show', [
                'document' => $document,
                'statusOptions' => DocumentStatus::cases(),
                'auditLogs' => $auditLogs,
                'workflowTemplates' => $workflowTemplates,
                'organizationUsers' => $organizationUsers,
            ]);
        }

        return response()->json([
            'document' => $document,
            'audit_logs' => $auditLogs,
            'workflow_templates' => $workflowTemplates,
        ]);
    }

    public function store(StoreDocumentRequest $request, CreateDocumentAction $createDocumentAction): JsonResponse|RedirectResponse
    {
        $visibility = $request->enum('visibility', DocumentVisibility::class);

        $organization = Organization::query()->findOrFail((int) $request->validated('organization_id'));

        $document = $createDocumentAction->execute(
            organization: $organization,
            owner: $request->user(),
            title: $request->validated('title'),
            description: $request->validated('description', ''),
            content: $request->validated('content'),
            documentType: $request->validated('document_type'),
            visibility: $visibility ?? DocumentVisibility::Private,
            metaSnapshot: $request->validated('meta_snapshot'),
        );

        if (! $request->expectsJson()) {
            return redirect()
                ->route('documents.show', $document)
                ->with('status', 'Document created.');
        }

        return response()->json($document->load(['currentVersion', 'owner:id,name']), 201);
    }
}
