<?php

namespace App\Models\Transaction\Procurement;

use App\Models\Concerns\HasBranchAccessScope;
use App\Models\Concerns\HasDepartmentAccessScope;
use App\Models\Transaction\AssetManagement\Asset;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'goods_receipt_id',
    'purchase_order_item_id',
    'purchase_request_item_id',
    'quantity_received',
    'quantity_accepted',
    'quantity_rejected',
    'remarks',
])]
class GoodsReceiptItem extends Model
{
    use HasBranchAccessScope;
    use HasDepartmentAccessScope;
    use HasUlids;

    protected $table = 'goods_receipt_items';

    protected $fillable = [
        'goods_receipt_id',
        'purchase_order_item_id',
        'purchase_request_item_id',
        'quantity_received',
        'quantity_accepted',
        'quantity_rejected',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'quantity_received' => 'decimal:3',
            'quantity_accepted' => 'decimal:3',
            'quantity_rejected' => 'decimal:3',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    protected function departmentAccessRelation(): ?string
    {
        return 'goodsReceipt';
    }

    protected function branchAccessRelation(): ?string
    {
        return 'goodsReceipt.warehouseLocation';
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function purchaseRequestItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestItem::class, 'purchase_request_item_id');
    }

    public function registeredAssets(): HasMany
    {
        return $this->hasMany(Asset::class, 'goods_receipt_item_id');
    }
}
