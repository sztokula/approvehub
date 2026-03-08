<?php

namespace App\Models;

use App\Enums\ApprovalAssigneeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Handles WorkflowTemplateStep responsibilities for the ApproveHub domain.
 */
class WorkflowTemplateStep extends Model
{
    /** @use HasFactory<\Database\Factories\WorkflowTemplateStepFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workflow_template_id',
        'step_order',
        'name',
        'assignee_type',
        'assignee_role',
        'assignee_user_id',
        'fallback_user_id',
        'due_in_hours',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assignee_type' => ApprovalAssigneeType::class,
            'due_in_hours' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<WorkflowTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class, 'workflow_template_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assigneeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function fallbackUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fallback_user_id');
    }
}
