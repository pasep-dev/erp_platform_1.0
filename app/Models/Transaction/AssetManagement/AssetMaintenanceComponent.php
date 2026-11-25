<?php

namespace App\Models\Transaction\AssetManagement;

use App\Models\Concerns\HasBranchAccessScope;
use App\Models\Concerns\HasDepartmentAccessScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMaintenanceComponent extends Model
{
    protected $table = 'trx_asset_maintenance_components';

    use HasBranchAccessScope;
    use HasDepartmentAccessScope;
    use HasUlids;

    protected $fillable = [
        'asset_maintenance_record_id',
        'component_name',
        'quantity',
        'estimated_unit_cost',
        'needed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'estimated_unit_cost' => 'decimal:2',
            'needed_at' => 'date:Y-m-d',
        ];
    }

    public function maintenanceRecord(): BelongsTo
    {
        return $this->belongsTo(AssetMaintenanceRecord::class, 'asset_maintenance_record_id');
    }

    protected function departmentAccessRelation(): ?string
    {
        return 'maintenanceRecord.asset';
    }

    protected function branchAccessRelation(): ?string
    {
        return 'maintenanceRecord.asset';
    }
}
