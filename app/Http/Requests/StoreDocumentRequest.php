<?php

namespace App\Http\Requests;

use App\Enums\DocumentVisibility;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Handles StoreDocumentRequest responsibilities for the ApproveHub domain.
 */
class StoreDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $organizationId = (int) $this->input('organization_id');

        if ($user === null || $organizationId === 0) {
            return false;
        }

        return $user->hasOrganizationRole($organizationId, UserRole::Admin, UserRole::Editor);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'document_type' => ['required', 'string', 'max:100'],
            'content' => ['required', 'string'],
            'visibility' => ['required', Rule::enum(DocumentVisibility::class)],
            'meta_snapshot' => ['nullable', 'array'],
        ];
    }
}
