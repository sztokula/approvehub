<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Handles StoreDocumentVersionRequest responsibilities for the ApproveHub domain.
 */
class StoreDocumentVersionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $document = $this->route('document');

        if (! $document instanceof Document) {
            return false;
        }

        return $this->user()?->can('update', $document) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title_snapshot' => ['required', 'string', 'max:255'],
            'content_snapshot' => ['required', 'string'],
            'meta_snapshot' => ['nullable', 'array'],
        ];
    }
}
