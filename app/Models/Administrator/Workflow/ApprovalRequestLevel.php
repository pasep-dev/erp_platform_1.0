<?php

namespace App\Models\Administrator\Workflow;

use App\Enums\Administrator\Workflow\ApprovalStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalRequestLevel extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'approval_request_levels';

    protected $fillable = [
        'approval_request_id',
        'level',
        'approver_user_id',
        'status',
        'acted_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'status' => ApprovalStatus::class,
            'acted_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(
            ApprovalRequest::class,
            'approval_request_id'
        );
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'approver_user_id'
        );
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class)
            ->latest();
    }
}
