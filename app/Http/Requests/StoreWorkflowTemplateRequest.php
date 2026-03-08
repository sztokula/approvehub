<?php

namespace App\Http\Requests;

use App\Enums\ApprovalAssigneeType;
use App\Enums\UserRole;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Handles StoreWorkflowTemplateRequest responsibilities for the ApproveHub domain.
 */
class StoreWorkflowTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $organization = $this->route('organization');

        if (! $organization instanceof Organization) {
            return false;
        }

        return $this->user()?->can('manageWorkflowTemplates', $organization) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = $this->route('organization');
        $organizationId = $organization instanceof Organization ? $organization->id : 0;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('workflow_templates', 'name')->where(
                    fn ($query) => $query->where('organization_id', $organizationId)
                ),
            ],
            'document_type' => ['required', 'string', 'max:100'],
            'is_default' => ['nullable', 'boolean'],
            'steps' => ['required', 'array', 'min:1', 'max:10'],
            'steps.*.step_order' => ['required', 'integer', 'min:1', 'distinct'],
            'steps.*.name' => ['required', 'string', 'max:255'],
            'steps.*.assignee_type' => ['required', Rule::enum(ApprovalAssigneeType::class)],
            'steps.*.assignee_role' => [
                'nullable',
                Rule::in(array_column(UserRole::cases(), 'value')),
            ],
            'steps.*.assignee_user_id' => [
                'nullable',
                Rule::exists('organization_user', 'user_id')->where(
                    fn ($query) => $query->where('organization_id', $organizationId)
                ),
            ],
            'steps.*.fallback_user_id' => [
                'nullable',
                Rule::exists('organization_user', 'user_id')->where(
                    fn ($query) => $query->where('organization_id', $organizationId)
                ),
            ],
            'steps.*.due_in_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_default' => filter_var($this->input('is_default', false), FILTER_VALIDATE_BOOLEAN),
            'steps' => collect($this->input('steps', []))
                ->map(function ($step): array {
                    $step = is_array($step) ? $step : [];

                    return [
                        'step_order' => isset($step['step_order']) ? (int) $step['step_order'] : null,
                        'name' => $step['name'] ?? null,
                        'assignee_type' => $step['assignee_type'] ?? null,
                        'assignee_role' => $step['assignee_role'] ?: null,
                        'assignee_user_id' => ($step['assignee_user_id'] ?? '') !== '' ? (int) $step['assignee_user_id'] : null,
                        'fallback_user_id' => ($step['fallback_user_id'] ?? '') !== '' ? (int) $step['fallback_user_id'] : null,
                        'due_in_hours' => ($step['due_in_hours'] ?? '') !== '' ? (int) $step['due_in_hours'] : null,
                    ];
                })
                ->values()
                ->all(),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->input('steps', []) as $index => $step) {
                $assigneeType = $step['assignee_type'] ?? null;

                if ($assigneeType === ApprovalAssigneeType::Role->value && empty($step['assignee_role'])) {
                    $validator->errors()->add("steps.{$index}.assignee_role", 'The assignee role field is required when assignee type is role.');
                }

                if ($assigneeType === ApprovalAssigneeType::User->value && empty($step['assignee_user_id'])) {
                    $validator->errors()->add("steps.{$index}.assignee_user_id", 'The assignee user field is required when assignee type is user.');
                }
            }
        });
    }
}
