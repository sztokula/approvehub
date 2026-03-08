<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $document->title }} - {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <main class="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
            <section class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <p class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Public read-only document') }}</p>
                <h1 class="mt-2 text-2xl font-semibold">{{ $document->title }}</h1>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $document->description }}</p>
                <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-300">
                    {{ __('Owner') }}: {{ $document->owner->name }} |
                    {{ __('Version') }}: v{{ $currentVersion?->version_number ?? 0 }} |
                    {{ __('Shared by token') }}
                </p>
            </section>

            <section class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold">{{ __('Current Content Snapshot') }}</h2>
                @if ($currentVersion !== null)
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                        {{ __('Snapshot title') }}: {{ $currentVersion->title_snapshot }} |
                        {{ __('Created') }}: {{ $currentVersion->created_at->toDateTimeString() }}
                    </p>
                    <pre class="mt-4 overflow-x-auto rounded-md bg-zinc-100 p-4 text-sm dark:bg-zinc-800">{{ $currentVersion->content_snapshot }}</pre>
                @else
                    <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">{{ __('No current version available.') }}</p>
                @endif
            </section>
        </main>
    </body>
</html>
