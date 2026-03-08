<x-layouts::app :title="__('Dashboard')">
    <div class="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-6 rounded-xl">
        {{-- High-level status counters for the current organization scope. --}}
        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <flux:text>{{ __('Draft') }}</flux:text>
                <flux:heading size="xl" class="mt-2">{{ $draftCount }}</flux:heading>
            </div>
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <flux:text>{{ __('In Review') }}</flux:text>
                <flux:heading size="xl" class="mt-2">{{ $inReviewCount }}</flux:heading>
            </div>
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <flux:text>{{ __('Approved') }}</flux:text>
                <flux:heading size="xl" class="mt-2">{{ $approvedCount }}</flux:heading>
            </div>
            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <flux:text>{{ __('Rejected') }}</flux:text>
                <flux:heading size="xl" class="mt-2">{{ $rejectedCount }}</flux:heading>
            </div>
        </div>

        {{-- Personalized queue of approval tasks assigned to the current user. --}}
        <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <div class="flex items-center justify-between gap-4">
                <flux:heading size="lg">{{ __('My Pending Approvals') }}</flux:heading>
                <a href="{{ route('documents.index') }}">
                    <flux:button>{{ __('Open Documents') }}</flux:button>
                </a>
            </div>

            <div class="mt-4 space-y-3">
                @forelse ($pendingForMe as $step)
                    <a
                        href="{{ route('documents.show', $step->workflow->documentVersion->document) }}"
                        class="block rounded-lg border border-zinc-200 p-4 transition hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500"
                    >
                        <flux:text>
                            {{ $step->workflow->documentVersion->document->title }} - {{ __('Step') }} {{ $step->step_order }} ({{ $step->name }})
                        </flux:text>
                    </a>
                @empty
                    <flux:text>{{ __('No active approval tasks assigned to you.') }}</flux:text>
                @endforelse
            </div>
        </div>

        {{-- Operational quality metrics (SLA + reviewer throughput). --}}
        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
                <flux:heading size="lg">{{ __('SLA Overview') }}</flux:heading>
                <div class="mt-4 space-y-2">
                    <flux:text>{{ __('Active steps with deadline') }}: {{ $activeDueStepsCount }}</flux:text>
                    <flux:text>{{ __('Overdue steps') }}: {{ $overdueStepsCount }}</flux:text>
                    <flux:text>
                        {{ __('SLA breach rate') }}:
                        {{ $activeDueStepsCount > 0 ? number_format(($overdueStepsCount / $activeDueStepsCount) * 100, 1) : '0.0' }}%
                    </flux:text>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
                <flux:heading size="lg">{{ __('Reviewer Throughput (30d)') }}</flux:heading>
                <div class="mt-4 space-y-2">
                    @forelse ($reviewerThroughput as $throughput)
                        <div class="flex items-center justify-between rounded-md border border-zinc-200 px-3 py-2 dark:border-zinc-700">
                            <flux:text>{{ $throughput->actor?->name ?? __('Unknown') }}</flux:text>
                            <flux:badge>{{ $throughput->total_decisions }}</flux:badge>
                        </div>
                    @empty
                        <flux:text>{{ __('No reviewer decisions in the last 30 days.') }}</flux:text>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
