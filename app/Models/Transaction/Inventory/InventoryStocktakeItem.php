<?php

namespace App\Models\Transaction\Inventory;

use App\Models\Concerns\HasBranchAccessScope;
use App\Models\Concerns\HasDepartmentAccessScope;
use App\Models\Master\Product\Product;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStocktakeItem extends Model
{
    use HasBranchAccessScope;
    use HasDepartmentAccessScope;
    use HasUlids;

    protected $table = 'inventory_stocktake_items';

    protected $fillable = [
        'inventory_stocktake_id', 'product_id', 'snapshot_quantity', 'snapshot_serial_numbers',
        'counted_quantity', 'counted_serial_numbers', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_quantity' => 'decimal:3',
            'snapshot_serial_numbers' => 'array',
            'counted_quantity' => 'decimal:3',
            'counted_serial_numbers' => 'array',
        ];
    }

    public function stocktake(): BelongsTo
    {
        return $this->belongsTo(InventoryStocktake::class, 'inventory_stocktake_id');
    }

    protected function departmentAccessRelation(): ?string
    {
        return 'stocktake';
    }

    protected function branchAccessRelation(): ?string
    {
        return 'stocktake.location';
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
