<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * Handles StoreAttachmentRequest responsibilities for the ApproveHub domain.
 */
class StoreAttachmentRequest extends FormRequest
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
        $document = $this->route('document');

        return [
            'version_id' => [
                'nullable',
                'integer',
                Rule::exists('document_versions', 'id')->where('document_id', $document?->id),
            ],
            'file' => ['required', File::types(['pdf', 'doc', 'docx', 'txt', 'png', 'jpg', 'jpeg'])->max(10240)],
        ];
    }
}
