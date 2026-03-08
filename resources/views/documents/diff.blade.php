<x-layouts::app :title="__('Version Diff')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <flux:heading size="xl">{{ __('Version Diff') }}</flux:heading>
                    <flux:text class="mt-2">
                        {{ __('Document') }}: {{ $document->title }}
                    </flux:text>
                </div>
                <a href="{{ route('documents.show', $document) }}">
                    <flux:button>{{ __('Back to document') }}</flux:button>
                </a>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <flux:heading>{{ __('Compare Versions') }}</flux:heading>
            <form method="GET" action="{{ route('documents.versions.diff', $document) }}" class="mt-4 grid gap-4 md:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium">{{ __('From Version') }}</label>
                    <select name="from_version_id" class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                        @foreach ($document->versions->sortByDesc('version_number') as $version)
                            <option value="{{ $version->id }}" @selected($fromVersion->id === $version->id)>
                                v{{ $version->version_number }} - {{ $version->title_snapshot }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium">{{ __('To Version') }}</label>
                    <select name="to_version_id" class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                        @foreach ($document->versions->sortByDesc('version_number') as $version)
                            <option value="{{ $version->id }}" @selected($toVersion->id === $version->id)>
                                v{{ $version->version_number }} - {{ $version->title_snapshot }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="self-end">
                    <flux:button type="submit">{{ __('Refresh Diff') }}</flux:button>
                </div>
            </form>
        </div>

        <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <div class="flex flex-wrap items-center gap-4">
                <flux:badge>{{ __('From') }} v{{ $diff['from'] }}</flux:badge>
                <flux:badge>{{ __('To') }} v{{ $diff['to'] }}</flux:badge>
                <flux:text>{{ __('Added') }}: {{ $diff['summary']['added'] }}</flux:text>
                <flux:text>{{ __('Removed') }}: {{ $diff['summary']['removed'] }}</flux:text>
                <flux:text>{{ __('Unchanged') }}: {{ $diff['summary']['unchanged'] }}</flux:text>
            </div>

            <div class="mt-4 space-y-2">
                @foreach ($diff['lines'] as $line)
                    @if ($line['type'] === 'unchanged')
                        <div class="rounded-md border border-zinc-200 bg-zinc-100 p-2 text-xs dark:border-zinc-700 dark:bg-zinc-800">
                            {{ $line['left'] }}
                        </div>
                    @elseif ($line['type'] === 'removed')
                        <div class="rounded-md border border-red-300 bg-red-50 p-2 text-xs dark:border-red-700 dark:bg-red-950">
                            - {{ $line['left'] }}
                        </div>
                    @else
                        <div class="rounded-md border border-green-300 bg-green-50 p-2 text-xs dark:border-green-700 dark:bg-green-950">
                            + {{ $line['right'] }}
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <flux:heading>{{ __('Metadata Diff') }}</flux:heading>
            <div class="mt-4 space-y-2">
                @forelse ($diff['metadata'] as $metadataLine)
                    <div class="rounded-md border p-3 text-xs {{ $metadataLine['changed'] ? 'border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-950' : 'border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800' }}">
                        <div class="font-semibold">{{ $metadataLine['key'] }}</div>
                        <div class="mt-1">
                            <span class="font-medium">{{ __('From') }}:</span>
                            {{ json_encode($metadataLine['from'], JSON_UNESCAPED_UNICODE) }}
                        </div>
                        <div>
                            <span class="font-medium">{{ __('To') }}:</span>
                            {{ json_encode($metadataLine['to'], JSON_UNESCAPED_UNICODE) }}
                        </div>
                    </div>
                @empty
                    <flux:text>{{ __('No metadata captured in compared versions.') }}</flux:text>
                @endforelse
            </div>
        </div>
    </div>
</x-layouts::app>
