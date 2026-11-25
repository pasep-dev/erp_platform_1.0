<?php

namespace App\Models\Transaction\Procurement;

use App\Models\Concerns\HasBranchAccessScope;
use App\Models\Concerns\HasDepartmentAccessScope;
use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\Department;
use App\Models\Master\Organization\Location;
use App\Models\Master\Procurement\Vendor;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number',
    'source_type',
    'purchase_order_id',
    'purchase_request_id',
    'company_id',
    'department_id',
    'warehouse_location_id',
    'storage_location_id',
    'vendor_id',
    'received_at',
    'delivery_note_number',
    'remarks',
    'status',
    'created_by',
    'posted_at',
    'posted_by',
])]
class GoodsReceipt extends Model
{
    use HasBranchAccessScope;
    use HasDepartmentAccessScope;
    use HasUlids;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_POSTED = 'POSTED';

    public const SOURCE_PURCHASE_ORDER = 'PURCHASE_ORDER';

    public const SOURCE_PURCHASE_REQUEST = 'PURCHASE_REQUEST';

    protected $table = 'trx_goods_receipts';

    protected $fillable = [
        'number',
        'source_type',
        'purchase_order_id',
        'purchase_request_id',
        'company_id',
        'department_id',
        'warehouse_location_id',
        'storage_location_id',
        'vendor_id',
        'received_at',
        'delivery_note_number',
        'remarks',
        'status',
        'created_by',
        'posted_at',
        'posted_by',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'date:Y-m-d',
            'posted_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
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

    public function warehouseLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'warehouse_location_id');
    }

    public function storageLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'storage_location_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class, 'goods_receipt_id');
    }
}
