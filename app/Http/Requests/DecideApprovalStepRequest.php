<?php

namespace App\Http\Requests;

use App\Models\ApprovalStep;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Handles DecideApprovalStepRequest responsibilities for the ApproveHub domain.
 */
class DecideApprovalStepRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $step = $this->route('step');

        if (! $step instanceof ApprovalStep) {
            return false;
        }

        return $this->user()?->can('approve', $step) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
