<x-layouts::app :title="$document->title">
    <div class="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-6">
        {{-- Global feedback banner for updates performed on this document. --}}
        @if (session('status'))
            <flux:callout variant="success" :heading="session('status')" />
        @endif

        {{-- Document header with ownership and high-level actions. --}}
        <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <flux:heading size="xl">{{ $document->title }}</flux:heading>
                    <flux:text class="mt-2">{{ $document->description }}</flux:text>
                    <flux:text class="mt-2">
                        {{ __('Owner') }}: {{ $document->owner->name }} |
                        {{ __('Type') }}: {{ str($document->document_type)->title() }} |
                        {{ __('Current Version') }}: v{{ $document->currentVersion?->version_number ?? 0 }}
                    </flux:text>
                </div>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <flux:badge>{{ str($document->status->value)->replace('_', ' ')->title() }}</flux:badge>
                    @can('updateVisibility', $document)
                        <form method="POST" action="{{ route('documents.visibility.update', $document) }}" class="flex flex-wrap items-center gap-2">
                            @csrf
                            @method('PUT')
                            <select name="visibility" class="rounded-md border border-zinc-300 bg-white px-2 py-1 text-xs dark:border-zinc-600 dark:bg-zinc-800">
                                <option value="private" @selected($document->visibility->value === 'private')>{{ __('Private') }}</option>
                                <option value="organization" @selected($document->visibility->value === 'organization')>{{ __('Organization') }}</option>
                            </select>
                            <flux:button size="sm">{{ __('Set Visibility') }}</flux:button>
                        </form>
                    @endcan
                    @can('archive', $document)
                        <form method="POST" action="{{ route('documents.archive.store', $document) }}">
                            @csrf
                            <flux:button size="sm" variant="danger">{{ __('Archive') }}</flux:button>
                        </form>
                    @endcan
                </div>
            </div>
        </div>

        {{-- Sticky local navigation for long single-page workflow view. --}}
        <div class="sticky top-2 z-10 rounded-xl border border-zinc-200 bg-white/95 p-3 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/90">
            <div class="flex flex-wrap items-center gap-2">
                <a href="#section-workbench"><flux:button size="sm" variant="ghost">{{ __('Workbench') }}</flux:button></a>
                <a href="#section-approval"><flux:button size="sm" variant="ghost">{{ __('Approval') }}</flux:button></a>
                <a href="#section-versions"><flux:button size="sm" variant="ghost">{{ __('Versions') }}</flux:button></a>
                <a href="#section-collaboration"><flux:button size="sm" variant="ghost">{{ __('Collaboration') }}</flux:button></a>
                @can('manageShareLinks', $document)
                    <a href="#section-sharing"><flux:button size="sm" variant="ghost">{{ __('Sharing') }}</flux:button></a>
                @endcan
                @can('managePermissions', $document)
                    <a href="#section-access"><flux:button size="sm" variant="ghost">{{ __('Access') }}</flux:button></a>
                @endcan
                <a href="#section-audit"><flux:button size="sm" variant="ghost">{{ __('Audit') }}</flux:button></a>
            </div>
        </div>

        {{-- Editing area for new versions and review submission. --}}
        <details id="section-workbench" class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <summary class="cursor-pointer">
                <flux:heading>{{ __('Workbench') }}</flux:heading>
            </summary>
            <div class="mt-4 grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
                <flux:heading>{{ __('Create New Version') }}</flux:heading>
                <form method="POST" action="{{ route('documents.versions.store', $document) }}" class="mt-4 space-y-4">
                    @csrf
                    <flux:input name="title_snapshot" :label="__('Title Snapshot')" :value="$document->title" required />
                    <x-markdown-editor
                        name="content_snapshot"
                        :label="__('Content Snapshot')"
                        :value="old('content_snapshot', $document->currentVersion?->content_snapshot)"
                        :rows="12"
                        :required="true"
                    />
                    <flux:button type="submit">{{ __('Save Version') }}</flux:button>
                </form>
            </div>

            <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
                <flux:heading>{{ __('Submit For Review') }}</flux:heading>
                <form method="POST" action="{{ route('documents.review.store', $document) }}" class="mt-4 space-y-3">
                    @csrf
                    @if ($workflowTemplates->isNotEmpty())
                        <label class="block text-sm font-medium">{{ __('Workflow Template') }}</label>
                        <select
                            name="template_id"
                            class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800"
                        >
                            <option value="">{{ __('No template (manual reviewers)') }}</option>
                            @foreach ($workflowTemplates as $workflowTemplate)
                                <option value="{{ $workflowTemplate->id }}" @selected((int) old('template_id') === $workflowTemplate->id)>
                                    {{ $workflowTemplate->name }} ({{ str($workflowTemplate->document_type)->title() }})
                                </option>
                            @endforeach
                        </select>
                    @endif
                    <input type="hidden" name="steps[0][name]" value="Reviewer">
                    <input type="hidden" name="steps[0][assignee_type]" value="user">
                    <label class="block text-sm font-medium">{{ __('Manual Reviewers (optional when no template)') }}</label>
                    <select
                        name="steps[0][assignees][]"
                        multiple
                        size="5"
                        class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800"
                    >
                        @foreach ($organizationUsers as $organizationUser)
                            <option value="{{ $organizationUser->id }}">{{ $organizationUser->name }}</option>
                        @endforeach
                    </select>
                    <flux:text>{{ __('Template has priority. Without template, this form submits a single reviewer step.') }}</flux:text>
                    <flux:button type="submit" variant="primary">{{ __('Submit Review Workflow') }}</flux:button>
                </form>
            </div>
            </div>
        </details>

        {{-- Active and historical approval state of the current version. --}}
        <details id="section-approval" open class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <summary class="cursor-pointer">
                <flux:heading>{{ __('Approval Steps') }}</flux:heading>
            </summary>
            <div class="mt-4 max-h-[26rem] space-y-3 overflow-y-auto pr-1">
                @forelse ($document->currentVersion?->workflow?->steps ?? [] as $step)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <flux:text>
                                #{{ $step->step_order }} - {{ $step->name }} ({{ $step->status->value }})
                            </flux:text>
                            @if ($step->status->value === 'active')
                                <div class="flex flex-wrap items-center gap-2">
                                    <form method="POST" action="{{ route('approval-steps.approve', $step) }}">
                                        @csrf
                                        <flux:button type="submit" size="sm">{{ __('Approve') }}</flux:button>
                                    </form>
                                    <form method="POST" action="{{ route('approval-steps.reject', $step) }}">
                                        @csrf
                                        <flux:button type="submit" size="sm" variant="danger">{{ __('Reject') }}</flux:button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <flux:text>{{ __('No workflow attached to current version.') }}</flux:text>
                @endforelse
            </div>
        </details>

        {{-- Immutable snapshots and restore actions. --}}
        <details id="section-versions" class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <summary class="cursor-pointer">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:heading>{{ __('Version History') }}</flux:heading>
                @php
                    $diffVersions = $document->versions->sortByDesc('version_number')->values();
                    $toVersion = $diffVersions->get(0);
                    $fromVersion = $diffVersions->get(1);
                @endphp
                @if ($fromVersion && $toVersion)
                    <a href="{{ route('documents.versions.diff', [$document, 'from_version_id' => $fromVersion->id, 'to_version_id' => $toVersion->id]) }}">
                        <flux:button size="sm">{{ __('Compare Latest Versions') }}</flux:button>
                    </a>
                @endif
            </div>
            </summary>
            <div class="mt-4 max-h-[30rem] space-y-3 overflow-y-auto pr-1">
                @forelse ($document->versions->sortByDesc('version_number') as $version)
                    <details class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <summary class="cursor-pointer text-sm font-medium">
                            v{{ $version->version_number }} - {{ $version->title_snapshot }} ({{ $version->created_at->toDateTimeString() }})
                        </summary>
                        <div class="mt-3 space-y-2">
                            <flux:text>{{ __('Author') }}: {{ $version->creator->name }}</flux:text>
                            <form method="POST" action="{{ route('documents.versions.restore', [$document, $version]) }}">
                                @csrf
                                <flux:button type="submit" size="sm">{{ __('Restore As New Version') }}</flux:button>
                            </form>
                            <pre class="overflow-x-auto rounded-md bg-zinc-100 p-3 text-xs dark:bg-zinc-800">{{ $version->content_snapshot }}</pre>
                        </div>
                    </details>
                @empty
                    <flux:text>{{ __('No versions available.') }}</flux:text>
                @endforelse
            </div>
        </details>

        {{-- Collaboration area for comments and file attachments. --}}
        <details id="section-collaboration" class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <summary class="cursor-pointer">
                <flux:heading>{{ __('Collaboration') }}</flux:heading>
            </summary>
            <div class="mt-4 grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
                <flux:heading>{{ __('Comments Timeline') }}</flux:heading>
                <form method="POST" action="{{ route('documents.comments.store', $document) }}" class="mt-4 space-y-3">
                    @csrf
                    <input type="hidden" name="version_id" value="{{ $document->current_version_id }}">
                    <x-markdown-editor
                        name="body"
                        :label="__('Comment')"
                        :value="old('body')"
                        :rows="6"
                        :required="true"
                        :placeholder="__('Write a contextual comment for this version...')"
                    />
                    <flux:button type="submit">{{ __('Add Comment') }}</flux:button>
                </form>

                <div class="mt-6 max-h-[22rem] space-y-3 overflow-y-auto pr-1">
                    @forelse ($document->comments->sortByDesc('created_at') as $comment)
                        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <flux:text>{{ $comment->body }}</flux:text>
                            <flux:text class="mt-2">
                                {{ $comment->user->name }} - {{ $comment->created_at->diffForHumans() }}
                            </flux:text>
                        </div>
                    @empty
                        <flux:text>{{ __('No comments yet.') }}</flux:text>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
                <flux:heading>{{ __('Attachments') }}</flux:heading>
                <form method="POST" action="{{ route('documents.attachments.store', $document) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                    @csrf
                    <input type="hidden" name="version_id" value="{{ $document->current_version_id }}">
                    <label class="block text-sm font-medium">{{ __('File') }}</label>
                    <input
                        type="file"
                        name="file"
                        class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800"
                        required
                    >
                    <flux:button type="submit">{{ __('Upload Attachment') }}</flux:button>
                </form>

                <div class="mt-6 max-h-[22rem] space-y-3 overflow-y-auto pr-1">
                    @forelse ($document->attachments->sortByDesc('created_at') as $attachment)
                        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <flux:text>{{ $attachment->original_name }}</flux:text>
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('documents.attachments.download', [$document, $attachment]) }}">
                                        <flux:button size="sm">{{ __('Download') }}</flux:button>
                                    </a>
                                    <form method="POST" action="{{ route('documents.attachments.destroy', [$document, $attachment]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <flux:button size="sm" variant="danger">{{ __('Delete') }}</flux:button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <flux:text>{{ __('No attachments yet.') }}</flux:text>
                    @endforelse
                </div>
            </div>
            </div>
        </details>

        @can('manageShareLinks', $document)
            {{-- Public read-only link management for external stakeholders. --}}
            <details id="section-sharing" class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
                <summary class="cursor-pointer">
                <flux:heading>{{ __('Public Share Links') }}</flux:heading>
                </summary>
                <form method="POST" action="{{ route('documents.share-links.store', $document) }}" class="mt-4 grid gap-3 md:grid-cols-3">
                    @csrf
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium">{{ __('Expires At (optional)') }}</label>
                        <input
                            type="datetime-local"
                            name="expires_at"
                            class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800"
                        >
                    </div>
                    <div class="md:col-span-1 md:self-end">
                        <flux:button type="submit">{{ __('Generate Share Link') }}</flux:button>
                    </div>
                </form>

                <div class="mt-4 max-h-[20rem] space-y-3 overflow-y-auto pr-1">
                    @forelse ($document->publicShareLinks->sortByDesc('created_at') as $publicShareLink)
                        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="space-y-1">
                                    <flux:text>
                                        <a href="{{ route('public-share-links.show', $publicShareLink) }}" target="_blank" class="underline underline-offset-4">
                                            {{ route('public-share-links.show', $publicShareLink) }}
                                        </a>
                                    </flux:text>
                                    <flux:text>
                                        {{ __('Created by') }}: {{ $publicShareLink->creator->name }} |
                                        {{ __('Status') }}: {{ $publicShareLink->is_active ? __('active') : __('revoked') }} |
                                        {{ __('Expires') }}: {{ $publicShareLink->expires_at?->toDateTimeString() ?? __('never') }}
                                    </flux:text>
                                </div>
                                @can('delete', $publicShareLink)
                                    @if ($publicShareLink->is_active)
                                        <form method="POST" action="{{ route('documents.share-links.destroy', [$document, $publicShareLink]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <flux:button type="submit" size="sm" variant="danger">{{ __('Revoke') }}</flux:button>
                                        </form>
                                    @endif
                                @endcan
                            </div>
                        </div>
                    @empty
                        <flux:text>{{ __('No public share links yet.') }}</flux:text>
                    @endforelse
                </div>
            </details>
        @endcan

        @can('managePermissions', $document)
            {{-- Per-document access overrides for internal users. --}}
            <details id="section-access" class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
                <summary class="cursor-pointer">
                <flux:heading>{{ __('Document Access') }}</flux:heading>
                </summary>
                <form method="POST" action="{{ route('documents.permissions.store', $document) }}" class="mt-4 grid gap-3 md:grid-cols-3">
                    @csrf
                    <div>
                        <label class="mb-2 block text-sm font-medium">{{ __('User') }}</label>
                        <select name="user_id" class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                            @foreach ($organizationUsers as $organizationUser)
                                <option value="{{ $organizationUser->id }}">{{ $organizationUser->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium">{{ __('Permission') }}</label>
                        <select name="permission" class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                            <option value="view">{{ __('View') }}</option>
                            <option value="review">{{ __('Review') }}</option>
                        </select>
                    </div>
                    <div class="md:self-end">
                        <flux:button type="submit">{{ __('Grant Access') }}</flux:button>
                    </div>
                </form>

                <div class="mt-4 max-h-[16rem] space-y-2 overflow-y-auto pr-1">
                    @forelse ($document->permissions as $documentPermission)
                        <div class="flex items-center justify-between rounded-md border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                            <flux:text>
                                {{ $documentPermission->user->name }} - {{ str($documentPermission->permission)->title() }}
                            </flux:text>
                            <form method="POST" action="{{ route('documents.permissions.destroy', [$document, $documentPermission]) }}">
                                @csrf
                                @method('DELETE')
                                <flux:button size="sm" variant="danger">{{ __('Revoke') }}</flux:button>
                            </form>
                        </div>
                    @empty
                        <flux:text>{{ __('No explicit permissions yet.') }}</flux:text>
                    @endforelse
                </div>
            </details>
        @endcan

        {{-- Chronological audit timeline and export actions. --}}
        <details id="section-audit" class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <summary class="cursor-pointer">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:heading>{{ __('Audit Timeline') }}</flux:heading>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('documents.pdf.export', $document) }}">
                        <flux:button size="sm">{{ __('Export PDF') }}</flux:button>
                    </a>
                    <a href="{{ route('documents.audit.export', [$document, 'format' => 'json']) }}">
                        <flux:button size="sm">{{ __('Export JSON') }}</flux:button>
                    </a>
                    <a href="{{ route('documents.audit.export', [$document, 'format' => 'csv']) }}">
                        <flux:button size="sm">{{ __('Export CSV') }}</flux:button>
                    </a>
                </div>
            </div>
            </summary>
            <div class="mt-4 max-h-[26rem] space-y-3 overflow-y-auto pr-1">
                @forelse ($auditLogs as $auditLog)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <flux:text>{{ $auditLog->action }}</flux:text>
                            <flux:text>{{ $auditLog->occurred_at->toDateTimeString() }}</flux:text>
                        </div>
                        <flux:text class="mt-1">
                            {{ __('Actor') }}: {{ $auditLog->actor?->name ?? __('System') }} |
                            {{ __('Target') }}: {{ $auditLog->target_type }}#{{ $auditLog->target_id }}
                        </flux:text>
                    </div>
                @empty
                    <flux:text>{{ __('No audit events yet.') }}</flux:text>
                @endforelse
            </div>
        </details>
    </div>
</x-layouts::app>
