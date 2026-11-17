<?php

namespace App\Models\Transaction\Inventory;

use App\Models\Concerns\HasBranchAccessScope;
use App\Models\Concerns\HasDepartmentAccessScope;
use App\Models\Master\Organization\Department;
use App\Models\Master\Organization\Location;
use App\Models\Master\Product\Product;
use App\Models\Transaction\Procurement\GoodsReceiptItem;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReceiptIntegration extends Model
{
    use HasBranchAccessScope;
    use HasDepartmentAccessScope;
    use HasUlids;

    public const STATUS_PENDING_WAREHOUSE = 'PENDING_WAREHOUSE';

    public const STATUS_PENDING_SERIALS = 'PENDING_SERIALS';

    public const STATUS_PROCESSED = 'PROCESSED';

    public const STATUS_FAILED = 'FAILED';

    protected $table = 'inventory_receipt_integrations';

    protected $fillable = [
        'goods_receipt_item_id',
        'product_id',
        'department_id',
        'location_id',
        'accepted_quantity',
        'status',
        'failure_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_quantity' => 'decimal:3',
            'processed_at' => 'datetime',
        ];
    }

    public function goodsReceiptItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
