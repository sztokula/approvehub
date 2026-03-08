<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Handles ApprovalStepAssignee responsibilities for the ApproveHub domain.
 */
class ApprovalStepAssignee extends Model
{
    /** @use HasFactory<\Database\Factories\ApprovalStepAssigneeFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'step_id',
        'user_id',
        'is_required',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ApprovalStep, $this>
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(ApprovalStep::class, 'step_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
