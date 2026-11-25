<?php

namespace App\Models\Transaction\AssetManagement;

use App\Models\Concerns\HasBranchAccessScope;
use App\Models\Concerns\HasDepartmentAccessScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetIncidentEvent extends Model
{
    protected $table = 'trx_asset_incident_events';

    use HasBranchAccessScope;
    use HasDepartmentAccessScope;
    use HasUlids;

    protected $fillable = [
        'asset_incident_id',
        'event_type',
        'status_from',
        'status_to',
        'condition_from',
        'condition_to',
        'asset_status_from',
        'asset_status_to',
        'notes',
        'performed_by',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(AssetIncident::class, 'asset_incident_id');
    }

    protected function departmentAccessRelation(): ?string
    {
        return 'incident.asset';
    }

    protected function branchAccessRelation(): ?string
    {
        return 'incident.asset';
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
