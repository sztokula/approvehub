<?php

use App\Http\Controllers\ApprovalStepDecisionController;
use App\Http\Controllers\DocumentAuditExportController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentPermissionController;
use App\Http\Controllers\DocumentPdfAsyncExportController;
use App\Http\Controllers\DocumentReviewController;
use App\Http\Controllers\DocumentVersionController;
use App\Http\Controllers\DocumentVersionRestoreController;
use Illuminate\Support\Facades\Route;

// Session-authenticated internal API used by the UI and integrations.
Route::middleware(['web', 'auth', 'verified'])->prefix('v1')->name('api.v1.')->group(function () {
    // Document write operations.
    Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::post('documents/{document}/versions', [DocumentVersionController::class, 'store'])->name('documents.versions.store');
    Route::post('documents/{document}/review', [DocumentReviewController::class, 'store'])->name('documents.review.store');
    Route::post('documents/{document}/permissions', [DocumentPermissionController::class, 'store'])->name('documents.permissions.store');
    Route::delete('documents/{document}/permissions/{documentPermission}', [DocumentPermissionController::class, 'destroy'])->name('documents.permissions.destroy');
    // Approval step decisions.
    Route::post('approval-steps/{step}/approve', [ApprovalStepDecisionController::class, 'approve'])->name('approval-steps.approve');
    Route::post('approval-steps/{step}/reject', [ApprovalStepDecisionController::class, 'reject'])->name('approval-steps.reject');
    // Version restoration and audit exports.
    Route::post('documents/{document}/versions/{version}/restore', [DocumentVersionRestoreController::class, 'store'])->name('documents.versions.restore');
    Route::get('documents/{document}/audit', DocumentAuditExportController::class)->name('documents.audit.index');
    // Async PDF export flow.
    Route::post('documents/{document}/pdf-exports', [DocumentPdfAsyncExportController::class, 'store'])->name('documents.pdf-exports.store');
    Route::get('documents/pdf-exports/{token}', [DocumentPdfAsyncExportController::class, 'show'])->name('documents.pdf-exports.show');
});
