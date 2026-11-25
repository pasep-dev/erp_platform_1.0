<?php

namespace App\Models\Transaction\AssetManagement;

use App\Models\Concerns\HasBranchAccessScope;
use App\Models\Concerns\HasDepartmentAccessScope;
use App\Models\Master\Procurement\Vendor;
use App\Models\Transaction\Procurement\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetMaintenanceRecord extends Model
{
    protected $table = 'trx_asset_maintenance_records';

    use HasBranchAccessScope;
    use HasDepartmentAccessScope;
    use HasUlids;

    public const STATUS_REQUESTED = 'REQUESTED';

    public const STATUS_WAITING_APPROVAL = 'WAITING_APPROVAL';

    public const STATUS_WAITING_PARTS = 'WAITING_PARTS';

    public const STATUS_READY_TO_START = 'READY_TO_START';

    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';

    public const STATUS_COMPLETED = 'COMPLETED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const TYPE_PREVENTIVE = 'PREVENTIVE';

    public const TYPE_CORRECTIVE = 'CORRECTIVE';

    public const EXECUTION_INTERNAL = 'INTERNAL';

    public const EXECUTION_VENDOR = 'VENDOR';

    public const PRIORITY_OPTIONS = [
        'LOW' => 'Rendah',
        'NORMAL' => 'Normal',
        'HIGH' => 'Tinggi',
        'URGENT' => 'Urgent',
    ];

    protected $fillable = [
        'number',
        'asset_id',
        'maintenance_type',
        'execution_mode',
        'requires_spare_parts',
        'priority',
        'status',
        'requested_at',
        'submitted_at',
        'approved_by',
        'approved_at',
        'approval_document_path',
        'approval_remarks',
        'rejected_by',
        'rejected_at',
        'cancelled_by',
        'cancelled_at',
        'issue_description',
        'reported_condition',
        'inspection_result',
        'vendor_id',
        'estimated_cost',
        'scheduled_at',
        'started_at',
        'completed_at',
        'work_summary',
        'completed_condition',
        'actual_cost',
        'next_service_date',
        'cancellation_reason',
        'requested_by',
        'completed_by',
    ];

    protected function casts(): array
    {
        return [
            'requires_spare_parts' => 'boolean',
            'requested_at' => 'date:Y-m-d',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'scheduled_at' => 'date:Y-m-d',
            'started_at' => 'date:Y-m-d',
            'completed_at' => 'date:Y-m-d',
            'next_service_date' => 'date:Y-m-d',
            'estimated_cost' => 'decimal:2',
            'actual_cost' => 'decimal:2',
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

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class, 'asset_maintenance_record_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(AssetMaintenanceComponent::class, 'asset_maintenance_record_id');
    }
}
