<?php

namespace App\Http\Controllers;

use App\Actions\Audit\RecordAuditLogAction;
use App\Http\Requests\StoreDocumentPermissionRequest;
use App\Models\Document;
use App\Models\DocumentPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Handles DocumentPermissionController responsibilities for the ApproveHub domain.
 */
class DocumentPermissionController extends Controller
{
    public function store(
        StoreDocumentPermissionRequest $request,
        Document $document,
        RecordAuditLogAction $recordAuditLogAction,
    ): JsonResponse|RedirectResponse
    {
        $documentPermission = $document->permissions()->firstOrCreate([
            'user_id' => (int) $request->validated('user_id'),
            'permission' => $request->validated('permission'),
        ]);

        $recordAuditLogAction->execute(
            organizationId: $document->organization_id,
            actor: $request->user(),
            action: 'document.permission.granted',
            targetType: 'document_permission',
            targetId: $documentPermission->id,
            metadata: [
                'document_id' => $document->id,
                'user_id' => $documentPermission->user_id,
                'permission' => $documentPermission->permission,
            ],
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Document permission granted.',
            ], 201);
        }

        return redirect()
            ->route('documents.show', $document)
            ->with('status', 'Document permission granted.');
    }

    public function destroy(
        Request $request,
        Document $document,
        DocumentPermission $documentPermission,
        RecordAuditLogAction $recordAuditLogAction,
    ): JsonResponse|RedirectResponse {
        $this->authorize('managePermissions', $document);
        abort_if($documentPermission->document_id !== $document->id, 404);

        $recordAuditLogAction->execute(
            organizationId: $document->organization_id,
            actor: $request->user(),
            action: 'document.permission.revoked',
            targetType: 'document_permission',
            targetId: $documentPermission->id,
            metadata: [
                'document_id' => $document->id,
                'user_id' => $documentPermission->user_id,
                'permission' => $documentPermission->permission,
            ],
        );

        $documentPermission->delete();

        if (request()->expectsJson()) {
            return response()->json([
                'message' => 'Document permission revoked.',
            ]);
        }

        return redirect()
            ->route('documents.show', $document)
            ->with('status', 'Document permission revoked.');
    }
}
