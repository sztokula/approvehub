<?php

namespace App\Models;

use App\Enums\ApprovalAssigneeType;
use App\Enums\ApprovalStepStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Handles ApprovalStep responsibilities for the ApproveHub domain.
 */
class ApprovalStep extends Model
{
    /** @use HasFactory<\Database\Factories\ApprovalStepFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workflow_id',
        'step_order',
        'name',
        'assignee_type',
        'assignee_role',
        'fallback_user_id',
        'status',
        'activated_at',
        'decided_at',
        'decision_note',
        'due_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assignee_type' => ApprovalAssigneeType::class,
            'status' => ApprovalStepStatus::class,
            'activated_at' => 'datetime',
            'decided_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ApprovalWorkflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class);
    }

    /**
     * @return HasMany<ApprovalStepAssignee, $this>
     */
    public function assignees(): HasMany
    {
        return $this->hasMany(ApprovalStepAssignee::class, 'step_id');
    }

    /**
     * @return HasMany<ApprovalDecision, $this>
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(ApprovalDecision::class, 'step_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function fallbackUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fallback_user_id');
    }
}
