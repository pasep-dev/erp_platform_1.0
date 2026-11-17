<?php

namespace App\Models\Transaction\AssetManagement;

use App\Models\Concerns\HasBranchAccessScope;
use App\Models\Concerns\HasDepartmentAccessScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDisposal extends Model
{
    use HasBranchAccessScope;
    use HasDepartmentAccessScope;
    use HasUlids;

    public const TYPE_SOLD = 'SOLD';

    public const TYPE_DONATED = 'DONATED';

    public const TYPE_WRITTEN_OFF = 'WRITTEN_OFF';

    public const TYPES = [self::TYPE_SOLD, self::TYPE_DONATED, self::TYPE_WRITTEN_OFF];

    public const STATUS_PENDING_APPROVAL = 'PENDING_APPROVAL';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUSES = [self::STATUS_PENDING_APPROVAL, self::STATUS_APPROVED, self::STATUS_REJECTED];

    protected $fillable = [
        'number',
        'asset_id',
        'disposal_type',
        'status',
        'disposal_date',
        'amount',
        'reason',
        'asset_snapshot',
        'evidence_path',
        'requested_by',
        'decided_by',
        'decision_notes',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'disposal_date' => 'date:Y-m-d',
            'amount' => 'decimal:2',
            'asset_snapshot' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    protected function departmentAccessRelation(): ?string
    {
        return 'asset';
    }

    protected function branchAccessRelation(): ?string
    {
        return 'asset';
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
