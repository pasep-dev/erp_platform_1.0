<?php

namespace App\Models\Transaction\AssetManagement;

use App\Models\Concerns\HasBranchAccessScope;
use App\Models\Concerns\HasDepartmentAccessScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetIncident extends Model
{
    protected $table = 'trx_asset_incidents';

    use HasBranchAccessScope;
    use HasDepartmentAccessScope;
    use HasUlids;

    public const TYPE_DAMAGED = 'DAMAGED';

    public const TYPE_LOST = 'LOST';

    public const TYPE_STOLEN = 'STOLEN';

    public const TYPE_OTHER = 'OTHER';

    public const TYPES = [self::TYPE_DAMAGED, self::TYPE_LOST, self::TYPE_STOLEN, self::TYPE_OTHER];

    public const STATUS_REPORTED = 'REPORTED';

    public const STATUS_INVESTIGATING = 'INVESTIGATING';

    public const STATUS_RESOLVED = 'RESOLVED';

    public const STATUS_DISMISSED = 'DISMISSED';

    public const STATUSES = [self::STATUS_REPORTED, self::STATUS_INVESTIGATING, self::STATUS_RESOLVED, self::STATUS_DISMISSED];

    public const RESOLUTION_REPAIRED = 'REPAIRED';

    public const RESOLUTION_MONITOR = 'MONITOR';

    public const RESOLUTION_MISSING_CONFIRMED = 'MISSING_CONFIRMED';

    public const RESOLUTION_RECOVERED = 'RECOVERED';

    public const RESOLUTION_NO_ISSUE = 'NO_ISSUE';

    protected $fillable = [
        'number',
        'asset_id',
        'incident_type',
        'severity',
        'status',
        'occurred_at',
        'reported_condition',
        'description',
        'involved_parties',
        'investigation_findings',
        'resolution_type',
        'resolution_notes',
        'condition_before',
        'condition_after',
        'asset_status_before',
        'asset_status_after',
        'evidence_path',
        'reported_by',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'resolved_at' => 'datetime',
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

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(AssetIncidentEvent::class, 'asset_incident_id');
    }
}
