<?php

namespace App\Models\Administrator\Workflow;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalWorkflowLevel extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'approval_workflow_levels';

    protected $fillable = [
        'approval_workflow_id',
        'level',
        'approver_user_id',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(
            ApprovalWorkflow::class,
            'approval_workflow_id'
        );
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'approver_user_id'
        );
    }
}
