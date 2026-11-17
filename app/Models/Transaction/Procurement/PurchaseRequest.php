<?php

namespace App\Models\Transaction\Procurement;

use App\Models\Concerns\HasBranchAccessScope;
use App\Models\Concerns\HasDepartmentAccessScope;
use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\Department;
use App\Models\Transaction\AssetManagement\AssetMaintenanceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number',
    'request_date',
    'requester_id',
    'asset_maintenance_record_id',
    'company_id',
    'department_id',
    'purpose',
    'remarks',
    'status',
    'submitted_at',
    'approved_at',
    'rejected_at',
])]
class PurchaseRequest extends Model
{
    use HasBranchAccessScope;
    use HasDepartmentAccessScope;
    use HasFactory;
    use HasUlids;

    protected $table = 'purchase_requests';

    protected $fillable = [
        'number',
        'request_date',
        'requester_id',
        'asset_maintenance_record_id',
        'company_id',
        'department_id',
        'purpose',
        'remarks',
        'status',
        'submitted_at',
        'approved_at',
        'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'request_date' => 'date:Y-m-d',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    protected function branchAccessRelation(): ?string
    {
        return 'items';
    }

    protected function branchAccessRelationRequiresAll(): bool
    {
        return true;
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'requester_id'
        );
    }

    public function assetMaintenanceRecord(): BelongsTo
    {
        return $this->belongsTo(AssetMaintenanceRecord::class, 'asset_maintenance_record_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class,
            'company_id'
        );
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(
            Department::class,
            'department_id'
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            PurchaseRequestItem::class,
            'purchase_request_id'
        );
    }
}
