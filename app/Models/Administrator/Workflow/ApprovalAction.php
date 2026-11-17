<?php

namespace App\Models\Administrator\Workflow;

use App\Enums\Administrator\Workflow\ApprovalAction as ApprovalActionType;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalAction extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'approval_actions';

    protected $fillable = [
        'approval_request_id',
        'approval_request_level_id',
        'user_id',
        'action',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'action' => ApprovalActionType::class,
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(
            ApprovalRequest::class,
            'approval_request_id'
        );
    }

    public function requestLevel(): BelongsTo
    {
        return $this->belongsTo(
            ApprovalRequestLevel::class,
            'approval_request_level_id'
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
