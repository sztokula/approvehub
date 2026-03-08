<x-layouts::app :title="__('Documents')">
    <div x-data="{ showCreate: false, showFilters: true }" class="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-6">
        {{-- Global feedback banner for successful actions. --}}
        @if (session('status'))
            <flux:callout variant="success" :heading="session('status')" />
        @endif

        {{-- Draft creation area with quick onboarding form. --}}
        <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <flux:heading size="lg">{{ __('Create Document') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('Start a new draft and immediately version it.') }}</flux:text>
                </div>
                <flux:button type="button" x-on:click="showCreate = !showCreate" variant="ghost">
                    <span x-text="showCreate ? '{{ __('Hide Form') }}' : '{{ __('New Document') }}'"></span>
                </flux:button>
            </div>

            <form x-show="showCreate" method="POST" action="{{ route('documents.store') }}" class="mt-6 grid gap-4 md:grid-cols-2">
                @csrf

                <input type="hidden" name="meta_snapshot[editor]" value="markdown">

                <div class="md:col-span-1">
                    <flux:input name="title" :label="__('Title')" :value="old('title')" required />
                </div>

                <div class="md:col-span-1">
                    <label class="mb-2 block text-sm font-medium">{{ __('Organization') }}</label>
                    <select name="organization_id" class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                        @foreach ($organizations as $organization)
                            <option value="{{ $organization->id }}" @selected((int) old('organization_id') === $organization->id)>
                                {{ $organization->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-2">
                    <flux:input name="description" :label="__('Description')" :value="old('description')" />
                </div>

                <div class="md:col-span-1">
                    <label class="mb-2 block text-sm font-medium">{{ __('Document Type') }}</label>
                    <select name="document_type" class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                        @foreach (['general', 'contract', 'policy', 'request', 'offer', 'internal'] as $documentType)
                            <option value="{{ $documentType }}" @selected(old('document_type', 'general') === $documentType)>
                                {{ str($documentType)->title() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-1">
                    <label class="mb-2 block text-sm font-medium">{{ __('Visibility') }}</label>
                    <select name="visibility" class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                        <option value="private" @selected(old('visibility', 'private') === 'private')>{{ __('Private') }}</option>
                        <option value="organization" @selected(old('visibility') === 'organization')>{{ __('Organization') }}</option>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <x-markdown-editor
                        name="content"
                        :label="__('Content')"
                        :value="old('content')"
                        :rows="10"
                        :required="true"
                        :placeholder="__('Write the document body here...')"
                    />
                </div>

                <div class="md:col-span-2">
                    <flux:button type="submit" variant="primary">{{ __('Create Document') }}</flux:button>
                </div>
            </form>
        </div>

        {{-- Search and discovery panel for existing documents. --}}
        <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <flux:heading size="lg">{{ __('Document List') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('Filter by state and open details to review history and approvals.') }}</flux:text>
                </div>
                <flux:button type="button" x-on:click="showFilters = !showFilters" variant="ghost">
                    <span x-text="showFilters ? '{{ __('Hide Filters') }}' : '{{ __('Show Filters') }}'"></span>
                </flux:button>
                <form x-show="showFilters" method="GET" action="{{ route('documents.index') }}" class="flex flex-wrap items-center gap-2">
                    <select name="status" class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach ($statusOptions as $status)
                            <option value="{{ $status->value }}" @selected($statusFilter === $status->value)>
                                {{ str($status->value)->replace('_', ' ')->title() }}
                            </option>
                        @endforeach
                    </select>
                    <select name="owner_id" class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                        <option value="">{{ __('Any owner') }}</option>
                        @foreach ($organizationUsers as $organizationUser)
                            <option value="{{ $organizationUser->id }}" @selected($ownerFilter === $organizationUser->id)>
                                {{ $organizationUser->name }}
                            </option>
                        @endforeach
                    </select>
                    <select name="reviewer_id" class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                        <option value="">{{ __('Any reviewer') }}</option>
                        @foreach ($organizationUsers as $organizationUser)
                            <option value="{{ $organizationUser->id }}" @selected($reviewerFilter === $organizationUser->id)>
                                {{ $organizationUser->name }}
                            </option>
                        @endforeach
                    </select>
                    <input
                        type="date"
                        name="updated_from"
                        value="{{ $updatedFromFilter }}"
                        class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800"
                    >
                    <input
                        type="date"
                        name="updated_to"
                        value="{{ $updatedToFilter }}"
                        class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800"
                    >
                    <flux:button type="submit">{{ __('Filter') }}</flux:button>
                </form>
            </div>

            <div class="mt-6 space-y-3">
                @forelse ($documents as $document)
                    <a
                        href="{{ route('documents.show', $document) }}"
                        class="block rounded-lg border border-zinc-200 p-4 transition hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500"
                    >
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <flux:heading>{{ $document->title }}</flux:heading>
                                <flux:text class="mt-1">
                                    {{ __('Owner') }}: {{ $document->owner->name }} |
                                    {{ __('Type') }}: {{ str($document->document_type)->title() }} |
                                    {{ __('Version') }}: v{{ $document->currentVersion?->version_number ?? 0 }}
                                </flux:text>
                            </div>
                            <flux:badge>{{ str($document->status->value)->replace('_', ' ')->title() }}</flux:badge>
                        </div>
                    </a>
                @empty
                    <flux:text>{{ __('No documents yet.') }}</flux:text>
                @endforelse
            </div>

            <div class="mt-6">
                {{ $documents->withQueryString()->links() }}
            </div>
        </div>
    </div>
</x-layouts::app>
