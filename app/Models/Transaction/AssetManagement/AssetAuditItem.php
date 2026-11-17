<?php

namespace App\Models\Transaction\AssetManagement;

use App\Models\Concerns\HasBranchAccessScope;
use App\Models\Concerns\HasDepartmentAccessScope;
use App\Models\Master\Organization\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetAuditItem extends Model
{
    use HasBranchAccessScope;
    use HasDepartmentAccessScope;
    use HasUlids;

    public const RESULT_PENDING = 'PENDING';

    public const RESULT_FOUND = 'FOUND';

    public const RESULT_MOVED = 'MOVED';

    public const RESULT_DAMAGED = 'DAMAGED';

    public const RESULT_MISSING = 'MISSING';

    public const RESULTS = [
        self::RESULT_PENDING,
        self::RESULT_FOUND,
        self::RESULT_MOVED,
        self::RESULT_DAMAGED,
        self::RESULT_MISSING,
    ];

    protected $fillable = [
        'asset_audit_session_id',
        'asset_id',
        'expected_snapshot',
        'result',
        'observed_location_id',
        'observed_condition',
        'remarks',
        'checked_by',
        'checked_at',
        'resolution_remarks',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'expected_snapshot' => 'array',
            'checked_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AssetAuditSession::class, 'asset_audit_session_id');
    }

    protected function departmentAccessRelation(): ?string
    {
        return 'session';
    }

    protected function branchAccessRelation(): ?string
    {
        return 'session.location';
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function observedLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'observed_location_id');
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
