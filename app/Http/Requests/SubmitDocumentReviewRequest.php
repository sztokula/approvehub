<?php

namespace App\Http\Requests;

use App\Enums\ApprovalAssigneeType;
use App\Enums\UserRole;
use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Handles SubmitDocumentReviewRequest responsibilities for the ApproveHub domain.
 */
class SubmitDocumentReviewRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->filled('reviewers') && ! $this->has('steps') && ! $this->filled('template_id')) {
            $reviewerIds = collect(explode(',', (string) $this->input('reviewers')))
                ->map(fn ($id) => (int) trim($id))
                ->filter(fn ($id) => $id > 0)
                ->values()
                ->all();

            $this->merge([
                'steps' => [[
                    'name' => 'Reviewer',
                    'assignee_type' => ApprovalAssigneeType::User->value,
                    'assignees' => $reviewerIds,
                ]],
            ]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $document = $this->route('document');

        if (! $document instanceof Document) {
            return false;
        }

        return $this->user()?->can('submitForReview', $document) ?? false;
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
            'reviewers' => ['nullable', 'string'],
            'template_id' => [
                'nullable',
                'integer',
                Rule::exists('workflow_templates', 'id')->where('organization_id', $document?->organization_id),
            ],
            'steps' => ['nullable', 'array', 'min:1', 'required_without:template_id'],
            'steps.*.name' => ['required_with:steps', 'string', 'max:255'],
            'steps.*.assignee_type' => ['required_with:steps', Rule::enum(ApprovalAssigneeType::class)],
            'steps.*.assignee_role' => ['nullable', Rule::in(array_column(UserRole::cases(), 'value'))],
            'steps.*.assignees' => ['nullable', 'array'],
            'steps.*.assignees.*' => [
                'integer',
                Rule::exists('organization_user', 'user_id')->where('organization_id', $document?->organization_id),
            ],
            'steps.*.fallback_user_id' => [
                'nullable',
                'integer',
                Rule::exists('organization_user', 'user_id')->where('organization_id', $document?->organization_id),
            ],
            'steps.*.due_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->input('steps', []) as $index => $step) {
                $assigneeType = $step['assignee_type'] ?? null;
                $assignees = array_filter((array) ($step['assignees'] ?? []));

                if ($assigneeType === ApprovalAssigneeType::User->value && $assignees === []) {
                    $validator->errors()->add("steps.{$index}.assignees", 'At least one assignee is required when assignee type is user.');
                }

                if ($assigneeType === ApprovalAssigneeType::Role->value && empty($step['assignee_role'])) {
                    $validator->errors()->add("steps.{$index}.assignee_role", 'The assignee role field is required when assignee type is role.');
                }
            }
        });
    }
}
