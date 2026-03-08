<?php

use App\Http\Controllers\ApprovalStepDecisionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentAttachmentController;
use App\Http\Controllers\DocumentArchiveController;
use App\Http\Controllers\DocumentAuditExportController;
use App\Http\Controllers\DocumentCommentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentPermissionController;
use App\Http\Controllers\DocumentPdfExportController;
use App\Http\Controllers\DocumentPdfAsyncExportController;
use App\Http\Controllers\DocumentReviewController;
use App\Http\Controllers\DocumentShareLinkController;
use App\Http\Controllers\DocumentVersionController;
use App\Http\Controllers\DocumentVersionDiffController;
use App\Http\Controllers\DocumentVersionRestoreController;
use App\Http\Controllers\DocumentVisibilityController;
use App\Http\Controllers\OrganizationMemberController;
use App\Http\Controllers\ProjectDocsController;
use App\Http\Controllers\PublicShareLinkController;
use App\Http\Controllers\WorkflowTemplateController;
use Illuminate\Support\Facades\Route;

// Public entrypoints.
Route::redirect('/', '/dashboard')->name('home');
// Token-based read-only view for external stakeholders.
Route::get('share/{publicShareLink:token}', [PublicShareLinkController::class, 'show'])
    ->middleware('throttle:public-share-links')
    ->name('public-share-links.show');

// Main authenticated application surface.
Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard and document listing.
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('project-docs/{slug}', [ProjectDocsController::class, 'show'])
        ->whereIn('slug', ['documentation', 'changelog', 'what-i-learn'])
        ->name('project-docs.show');

    // Document CRUD + exports.
    Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('documents', [DocumentController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('documents.store');
    Route::get('documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('documents/{document}/audit-export', DocumentAuditExportController::class)
        ->name('documents.audit.export');
    Route::get('documents/{document}/export-pdf', DocumentPdfExportController::class)
        ->name('documents.pdf.export');
    Route::post('documents/{document}/export-pdf-async', [DocumentPdfAsyncExportController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('documents.pdf.exports.store');
    Route::get('documents/pdf-exports/{token}', [DocumentPdfAsyncExportController::class, 'show'])
        ->name('documents.pdf.exports.show');

    // Version management.
    Route::post('documents/{document}/versions', [DocumentVersionController::class, 'store'])
        ->middleware('throttle:120,1')
        ->name('documents.versions.store');
    Route::post('documents/{document}/versions/{version}/restore', [DocumentVersionRestoreController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('documents.versions.restore');
    Route::get('documents/{document}/versions/diff', [DocumentVersionDiffController::class, 'show'])
        ->name('documents.versions.diff');

    // Review workflow actions.
    Route::post('documents/{document}/review', [DocumentReviewController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('documents.review.store');
    Route::put('documents/{document}/visibility', [DocumentVisibilityController::class, 'update'])
        ->middleware('throttle:60,1')
        ->name('documents.visibility.update');
    Route::post('documents/{document}/archive', [DocumentArchiveController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('documents.archive.store');
    Route::post('documents/{document}/comments', [DocumentCommentController::class, 'store'])
        ->middleware('throttle:120,1')
        ->name('documents.comments.store');
    Route::post('documents/{document}/permissions', [DocumentPermissionController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('documents.permissions.store');
    Route::delete('documents/{document}/permissions/{documentPermission}', [DocumentPermissionController::class, 'destroy'])
        ->middleware('throttle:60,1')
        ->name('documents.permissions.destroy');
    Route::post('documents/{document}/attachments', [DocumentAttachmentController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('documents.attachments.store');
    Route::get('documents/{document}/attachments/{attachment}', [DocumentAttachmentController::class, 'download'])
        ->name('documents.attachments.download');
    Route::delete('documents/{document}/attachments/{attachment}', [DocumentAttachmentController::class, 'destroy'])
        ->middleware('throttle:60,1')
        ->name('documents.attachments.destroy');
    Route::post('documents/{document}/share-links', [DocumentShareLinkController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('documents.share-links.store');
    Route::delete('documents/{document}/share-links/{publicShareLink}', [DocumentShareLinkController::class, 'destroy'])
        ->middleware('throttle:60,1')
        ->name('documents.share-links.destroy');

    // Step-level approval decisions.
    Route::post('approval-steps/{step}/approve', [ApprovalStepDecisionController::class, 'approve'])
        ->middleware('throttle:120,1')
        ->name('approval-steps.approve');
    Route::post('approval-steps/{step}/reject', [ApprovalStepDecisionController::class, 'reject'])
        ->middleware('throttle:120,1')
        ->name('approval-steps.reject');

    // Organization administration.
    Route::get('organizations/{organization}/members', [OrganizationMemberController::class, 'index'])
        ->name('organizations.members.index');
    Route::put('organizations/{organization}/members/{membership}', [OrganizationMemberController::class, 'update'])
        ->middleware('throttle:120,1')
        ->name('organizations.members.update');

    Route::get('organizations/{organization}/workflow-templates', [WorkflowTemplateController::class, 'index'])
        ->name('organizations.workflow-templates.index');
    Route::post('organizations/{organization}/workflow-templates', [WorkflowTemplateController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('organizations.workflow-templates.store');
    Route::put('organizations/{organization}/workflow-templates/{workflowTemplate}', [WorkflowTemplateController::class, 'update'])
        ->middleware('throttle:60,1')
        ->name('organizations.workflow-templates.update');
    Route::delete('organizations/{organization}/workflow-templates/{workflowTemplate}', [WorkflowTemplateController::class, 'destroy'])
        ->middleware('throttle:60,1')
        ->name('organizations.workflow-templates.destroy');

});

require __DIR__.'/settings.php';
