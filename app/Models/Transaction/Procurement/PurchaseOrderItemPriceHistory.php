<?php

namespace App\Models\Transaction\Procurement;

use App\Models\Concerns\HasBranchAccessScope;
use App\Models\Concerns\HasDepartmentAccessScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_order_item_id',
    'previous_unit_price',
    'new_unit_price',
    'reason',
    'changed_by',
])]
class PurchaseOrderItemPriceHistory extends Model
{
    use HasBranchAccessScope;
    use HasDepartmentAccessScope;
    use HasUlids;

    protected $table = 'purchase_order_item_price_histories';

    protected $fillable = [
        'purchase_order_item_id',
        'previous_unit_price',
        'new_unit_price',
        'reason',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'previous_unit_price' => 'decimal:2',
            'new_unit_price' => 'decimal:2',
        ];
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    protected function departmentAccessRelation(): ?string
    {
        return 'purchaseOrderItem.purchaseOrder';
    }

    protected function branchAccessRelation(): ?string
    {
        return 'purchaseOrderItem';
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
