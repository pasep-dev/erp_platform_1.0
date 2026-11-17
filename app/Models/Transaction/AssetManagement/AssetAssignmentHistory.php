<?php

namespace App\Models\Transaction\AssetManagement;

use App\Models\Concerns\HasBranchAccessScope;
use App\Models\Concerns\HasDepartmentAccessScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetAssignmentHistory extends Model
{
    use HasBranchAccessScope;
    use HasDepartmentAccessScope;
    use HasUlids;

    protected $fillable = [
        'asset_id',
        'event_type',
        'from_condition',
        'to_condition',
        'from_custodian_id',
        'to_custodian_id',
        'from_snapshot',
        'to_snapshot',
        'performed_by',
        'effective_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'from_snapshot' => 'array',
            'to_snapshot' => 'array',
            'effective_at' => 'date:Y-m-d',
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

    public function fromCustodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_custodian_id');
    }

    public function toCustodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_custodian_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
