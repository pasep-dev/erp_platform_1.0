<?php

namespace App\Models\Transaction\Procurement;

use App\Models\Concerns\HasBranchAccessScope;
use App\Models\Concerns\HasDepartmentAccessScope;
use App\Models\Master\Procurement\ServiceType;
use App\Models\Master\Procurement\UnitOfMeasure;
use App\Models\Master\Product\Product;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'purchase_order_id',
    'purchase_request_item_id',
    'item_type',
    'product_id',
    'service_type_id',
    'asset_id',
    'description',
    'quantity',
    'uom_id',
    'pr_estimated_unit_price',
    'unit_price',
    'tax_included',
    'required_date',
    'delivery_address',
    'vendor_pic',
    'vendor_contact',
    'vendor_url',
    'remarks',
])]
class PurchaseOrderItem extends Model
{
    use HasBranchAccessScope;
    use HasDepartmentAccessScope;
    use HasUlids;

    protected $table = 'purchase_order_items';

    protected $fillable = [
        'purchase_order_id',
        'branch_id',
        'purchase_request_item_id',
        'item_type',
        'product_id',
        'service_type_id',
        'asset_id',
        'description',
        'quantity',
        'uom_id',
        'pr_estimated_unit_price',
        'unit_price',
        'tax_included',
        'required_date',
        'delivery_address',
        'vendor_pic',
        'vendor_contact',
        'vendor_url',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'pr_estimated_unit_price' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'tax_included' => 'boolean',
            'required_date' => 'date:Y-m-d',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    protected function departmentAccessRelation(): ?string
    {
        return 'purchaseOrder';
    }

    public function purchaseRequestItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestItem::class, 'purchase_request_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class, 'service_type_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(PurchaseOrderItemPriceHistory::class, 'purchase_order_item_id')
            ->latest();
    }

    public function goodsReceiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class, 'purchase_order_item_id');
    }
}
