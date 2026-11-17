<?php

namespace App\Models\Administrator\Workflow;

use App\Enums\Administrator\Workflow\ApprovalStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalRequest extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'approval_requests';

    protected $hidden = [
        'manual_approval_document_path',
    ];

    protected $fillable = [
        'module',
        'document_type',
        'document_id',
        'approval_workflow_id',
        'status',
        'current_level',
        'submitted_by',
        'submitted_at',
        'completed_at',
        'manual_approval_document_path',
        'manual_approval_remarks',
        'manual_approved_by',
        'manual_approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'current_level' => 'integer',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'manual_approved_at' => 'datetime',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(
            ApprovalWorkflow::class,
            'approval_workflow_id'
        );
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'submitted_by'
        );
    }

    public function manualApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manual_approved_by');
    }

    public function levels(): HasMany
    {
        return $this->hasMany(ApprovalRequestLevel::class)
            ->orderBy('level');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class)
            ->latest();
    }

    public function currentLevel(): ?ApprovalRequestLevel
    {
        if (! $this->current_level) {
            return null;
        }

        return $this->levels
            ->firstWhere('level', $this->current_level);
    }
}
