<x-layouts::app :title="__('Organization Members')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        @if (session('status'))
            <flux:callout variant="success" :heading="session('status')" />
        @endif

        <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <flux:heading size="lg">{{ $organization->name }}</flux:heading>
            <flux:text class="mt-2">{{ __('Manage organization roles for existing members.') }}</flux:text>
        </div>

        <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <div class="space-y-4">
                @foreach ($members as $member)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <flux:heading>{{ $member->user->name }}</flux:heading>
                                <flux:text>{{ $member->user->email }}</flux:text>
                            </div>

                            <form method="POST" action="{{ route('organizations.members.update', [$organization, $member]) }}" class="flex items-center gap-2">
                                @csrf
                                @method('PUT')
                                <select name="role_id" class="rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}" @selected($member->role_id === $role->id)>
                                            {{ str($role->name->value)->title() }}
                                        </option>
                                    @endforeach
                                </select>
                                <flux:button type="submit">{{ __('Update Role') }}</flux:button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts::app>
