<?php

namespace App\Models\Transaction\Procurement;

use App\Models\Concerns\HasBranchAccessScope;
use App\Models\Concerns\HasDepartmentAccessScope;
use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\Department;
use App\Models\Master\Procurement\Vendor;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number',
    'purchase_request_id',
    'company_id',
    'department_id',
    'vendor_id',
    'status',
    'submitted_at',
    'submitted_by',
    'cancelled_at',
    'cancelled_by',
    'cancellation_reason',
])]
class PurchaseOrder extends Model
{
    use HasBranchAccessScope;
    use HasDepartmentAccessScope;
    use HasUlids;

    protected $table = 'trx_purchase_orders';

    protected $fillable = [
        'number',
        'purchase_request_id',
        'company_id',
        'department_id',
        'vendor_id',
        'status',
        'submitted_at',
        'submitted_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
