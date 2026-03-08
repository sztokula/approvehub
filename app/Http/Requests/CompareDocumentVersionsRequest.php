<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Handles CompareDocumentVersionsRequest responsibilities for the ApproveHub domain.
 */
class CompareDocumentVersionsRequest extends FormRequest
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

        return $this->user()?->can('view', $document) ?? false;
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
            'from_version_id' => [
                'required',
                'integer',
                Rule::exists('document_versions', 'id')->where('document_id', $document?->id),
            ],
            'to_version_id' => [
                'required',
                'integer',
                'different:from_version_id',
                Rule::exists('document_versions', 'id')->where('document_id', $document?->id),
            ],
        ];
    }
}
