<x-layouts::app :title="__('Workflow Templates')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        @if (session('status'))
            <flux:callout variant="success" :heading="session('status')" />
        @endif

        @if ($errors->any())
            <flux:callout variant="danger" :heading="__('Please fix the form errors and try again.')">
                <ul class="mt-2 list-disc pl-5 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </flux:callout>
        @endif

        <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <flux:heading size="lg">{{ $organization->name }}</flux:heading>
            <flux:text class="mt-2">{{ __('Configure reusable multi-step approval paths by document type.') }}</flux:text>
        </div>

        <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <flux:heading>{{ __('Create Workflow Template') }}</flux:heading>
            <form method="POST" action="{{ route('organizations.workflow-templates.store', $organization) }}" class="mt-4 grid gap-4">
                @csrf

                <div class="grid gap-4 md:grid-cols-3">
                    <flux:input name="name" :label="__('Template Name')" :value="old('name')" required />
                    <div>
                        <label class="mb-2 block text-sm font-medium">{{ __('Document Type') }}</label>
                        <select name="document_type" class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                            @foreach (['general', 'contract', 'policy', 'request', 'offer', 'internal'] as $documentType)
                                <option value="{{ $documentType }}" @selected(old('document_type', 'general') === $documentType)>
                                    {{ str($documentType)->title() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <label class="mt-7 inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_default" value="1" @checked(old('is_default'))>
                        <span>{{ __('Set as default for this document type') }}</span>
                    </label>
                </div>

                @php($createSteps = old('steps', [
                    ['step_order' => 1, 'name' => 'Reviewer', 'assignee_type' => 'role', 'assignee_role' => 'reviewer', 'assignee_user_id' => null, 'fallback_user_id' => null, 'due_in_hours' => 24],
                    ['step_order' => 2, 'name' => 'Admin', 'assignee_type' => 'role', 'assignee_role' => 'admin', 'assignee_user_id' => null, 'fallback_user_id' => null, 'due_in_hours' => 24],
                ]))

                <div class="space-y-4">
                    @foreach ($createSteps as $index => $step)
                        <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="grid gap-4 md:grid-cols-6">
                                <flux:input
                                    :name="'steps['.$index.'][step_order]'"
                                    :label="__('Order')"
                                    type="number"
                                    min="1"
                                    :value="data_get($step, 'step_order')"
                                    required
                                />
                                <flux:input
                                    :name="'steps['.$index.'][name]'"
                                    :label="__('Step Name')"
                                    :value="data_get($step, 'name')"
                                    required
                                />
                                <div>
                                    <label class="mb-2 block text-sm font-medium">{{ __('Assignee Type') }}</label>
                                    <select name="steps[{{ $index }}][assignee_type]" class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                                        <option value="role" @selected(data_get($step, 'assignee_type') === 'role')>{{ __('Role') }}</option>
                                        <option value="user" @selected(data_get($step, 'assignee_type') === 'user')>{{ __('User') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium">{{ __('Role') }}</label>
                                    <select name="steps[{{ $index }}][assignee_role]" class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                                        <option value="">{{ __('Select role') }}</option>
                                        @foreach ($roleOptions as $roleOption)
                                            <option value="{{ $roleOption->value }}" @selected(data_get($step, 'assignee_role') === $roleOption->value)>
                                                {{ str($roleOption->value)->title() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium">{{ __('User') }}</label>
                                    <select name="steps[{{ $index }}][assignee_user_id]" class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                                        <option value="">{{ __('Select user') }}</option>
                                        @foreach ($organizationUsers as $organizationUser)
                                            <option value="{{ $organizationUser->id }}" @selected((int) data_get($step, 'assignee_user_id') === $organizationUser->id)>
                                                {{ $organizationUser->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <flux:input
                                    :name="'steps['.$index.'][due_in_hours]'"
                                    :label="__('Due (hours)')"
                                    type="number"
                                    min="1"
                                    :value="data_get($step, 'due_in_hours')"
                                />
                            </div>

                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-2 block text-sm font-medium">{{ __('Fallback User') }}</label>
                                    <select name="steps[{{ $index }}][fallback_user_id]" class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                                        <option value="">{{ __('No fallback') }}</option>
                                        @foreach ($organizationUsers as $organizationUser)
                                            <option value="{{ $organizationUser->id }}" @selected((int) data_get($step, 'fallback_user_id') === $organizationUser->id)>
                                                {{ $organizationUser->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div>
                    <flux:button type="submit">{{ __('Create Template') }}</flux:button>
                </div>
            </form>
        </div>

        <div class="rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
            <flux:heading>{{ __('Existing Templates') }}</flux:heading>
            <div class="mt-4 space-y-5">
                @forelse ($workflowTemplates as $workflowTemplate)
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <form method="POST" action="{{ route('organizations.workflow-templates.update', [$organization, $workflowTemplate]) }}" class="space-y-4">
                            @csrf
                            @method('PUT')

                            <div class="grid gap-4 md:grid-cols-3">
                                <flux:input
                                    name="name"
                                    :label="__('Template Name')"
                                    :value="$workflowTemplate->name"
                                    required
                                />
                                <div>
                                    <label class="mb-2 block text-sm font-medium">{{ __('Document Type') }}</label>
                                    <select name="document_type" class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                                        @foreach (['general', 'contract', 'policy', 'request', 'offer', 'internal'] as $documentType)
                                            <option value="{{ $documentType }}" @selected($workflowTemplate->document_type === $documentType)>
                                                {{ str($documentType)->title() }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <label class="mt-7 inline-flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="is_default" value="1" @checked($workflowTemplate->is_default)>
                                    <span>{{ __('Set as default for this document type') }}</span>
                                </label>
                            </div>

                            <div class="space-y-4">
                                @foreach ($workflowTemplate->steps->sortBy('step_order')->values() as $stepIndex => $step)
                                    <div class="rounded-md border border-zinc-200 p-4 dark:border-zinc-700">
                                        <div class="grid gap-4 md:grid-cols-6">
                                            <flux:input
                                                :name="'steps['.$stepIndex.'][step_order]'"
                                                :label="__('Order')"
                                                type="number"
                                                min="1"
                                                :value="$step->step_order"
                                                required
                                            />
                                            <flux:input
                                                :name="'steps['.$stepIndex.'][name]'"
                                                :label="__('Step Name')"
                                                :value="$step->name"
                                                required
                                            />
                                            <div>
                                                <label class="mb-2 block text-sm font-medium">{{ __('Assignee Type') }}</label>
                                                <select name="steps[{{ $stepIndex }}][assignee_type]" class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                                                    <option value="role" @selected($step->assignee_type->value === 'role')>{{ __('Role') }}</option>
                                                    <option value="user" @selected($step->assignee_type->value === 'user')>{{ __('User') }}</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="mb-2 block text-sm font-medium">{{ __('Role') }}</label>
                                                <select name="steps[{{ $stepIndex }}][assignee_role]" class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                                                    <option value="">{{ __('Select role') }}</option>
                                                    @foreach ($roleOptions as $roleOption)
                                                        <option value="{{ $roleOption->value }}" @selected($step->assignee_role === $roleOption->value)>
                                                            {{ str($roleOption->value)->title() }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="mb-2 block text-sm font-medium">{{ __('User') }}</label>
                                                <select name="steps[{{ $stepIndex }}][assignee_user_id]" class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                                                    <option value="">{{ __('Select user') }}</option>
                                                    @foreach ($organizationUsers as $organizationUser)
                                                        <option value="{{ $organizationUser->id }}" @selected($step->assignee_user_id === $organizationUser->id)>
                                                            {{ $organizationUser->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <flux:input
                                                :name="'steps['.$stepIndex.'][due_in_hours]'"
                                                :label="__('Due (hours)')"
                                                type="number"
                                                min="1"
                                                :value="$step->due_in_hours"
                                            />
                                        </div>

                                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                                            <div>
                                                <label class="mb-2 block text-sm font-medium">{{ __('Fallback User') }}</label>
                                                <select name="steps[{{ $stepIndex }}][fallback_user_id]" class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800">
                                                    <option value="">{{ __('No fallback') }}</option>
                                                    @foreach ($organizationUsers as $organizationUser)
                                                        <option value="{{ $organizationUser->id }}" @selected($step->fallback_user_id === $organizationUser->id)>
                                                            {{ $organizationUser->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <flux:button type="submit">{{ __('Update Template') }}</flux:button>
                        </form>
                        <form method="POST" action="{{ route('organizations.workflow-templates.destroy', [$organization, $workflowTemplate]) }}">
                            @csrf
                            @method('DELETE')
                            <flux:button type="submit" variant="danger">{{ __('Delete') }}</flux:button>
                        </form>
                            </div>
                    </div>
                @empty
                    <flux:text>{{ __('No workflow templates yet.') }}</flux:text>
                @endforelse
            </div>
        </div>
    </div>
</x-layouts::app>
