<x-layouts::app :title="$title">
    <div class="mx-auto flex h-full w-full max-w-5xl flex-1 flex-col gap-6">
        <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <flux:heading size="xl">{{ $title }}</flux:heading>
            <flux:text class="mt-2">
                {{ __('Project reference page:') }} <code>{{ $slug }}</code>
            </flux:text>
        </div>

        <article class="markdown-preview rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            {!! $html !!}
        </article>
    </div>
</x-layouts::app>
