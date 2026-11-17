<?php

namespace App\Models\Transaction\Procurement;

use App\Models\Concerns\HasBranchAccessScope;
use App\Models\Concerns\HasDepartmentAccessScope;
use App\Models\Master\Procurement\ServiceType;
use App\Models\Master\Procurement\UnitOfMeasure;
use App\Models\Master\Procurement\Vendor;
use App\Models\Master\Product\Product;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'purchase_request_id',
    'branch_id',
    'item_type',
    'product_id',
    'service_type_id',
    'asset_id',
    'description',
    'quantity',
    'uom_id',
    'estimated_unit_price',
    'vendor_id',
    'vendor_pic',
    'vendor_contact',
    'vendor_url',
    'requires_po',
    'tax_included',
    'required_date',
    'delivery_address',
    'remarks',
])]
class PurchaseRequestItem extends Model
{
    use HasBranchAccessScope;
    use HasDepartmentAccessScope;
    use HasFactory;
    use HasUlids;

    protected $table = 'purchase_request_items';

    protected $fillable = [
        'purchase_request_id',
        'branch_id',
        'item_type',
        'product_id',
        'service_type_id',
        'asset_id',
        'description',
        'quantity',
        'uom_id',
        'estimated_unit_price',
        'vendor_id',
        'vendor_pic',
        'vendor_contact',
        'vendor_url',
        'requires_po',
        'tax_included',
        'required_date',
        'delivery_address',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'estimated_unit_price' => 'decimal:2',
            'requires_po' => 'boolean',
            'tax_included' => 'boolean',
            'required_date' => 'date:Y-m-d',
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(
            PurchaseRequest::class,
            'purchase_request_id'
        );
    }

    protected function departmentAccessRelation(): ?string
    {
        return 'purchaseRequest';
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class,
            'product_id'
        );
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(
            ServiceType::class,
            'service_type_id'
        );
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(
            Vendor::class,
            'vendor_id'
        );
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(
            UnitOfMeasure::class,
            'uom_id'
        );
    }

    public function goodsReceiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class, 'purchase_request_item_id');
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_request_item_id');
    }
}
