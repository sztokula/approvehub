@props([
    'name',
    'label' => 'Content',
    'value' => '',
    'required' => false,
    'placeholder' => '',
])

@php
    $fieldId = 'editor_'.str_replace(['.', '[', ']'], '_', $name).'_'.uniqid();
@endphp

<div class="space-y-2">
    <label for="{{ $fieldId }}" class="block text-sm font-medium">{{ __($label) }}</label>

    <input
        id="{{ $fieldId }}"
        type="hidden"
        name="{{ $name }}"
        value="{{ old($name, $value) }}"
        @if ($required) required @endif
    >

    <trix-editor
        input="{{ $fieldId }}"
        class="trix-content min-h-56 rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100"
        placeholder="{{ $placeholder }}"
    ></trix-editor>
</div>
