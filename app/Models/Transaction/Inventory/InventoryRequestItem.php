<?php

namespace App\Models\Transaction\Inventory;

use App\Models\Concerns\HasBranchAccessScope;
use App\Models\Concerns\HasDepartmentAccessScope;
use App\Models\Master\Product\Product;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryRequestItem extends Model
{
    use HasBranchAccessScope;
    use HasDepartmentAccessScope;
    use HasUlids;

    protected $fillable = [
        'inventory_request_id',
        'product_id',
        'requested_quantity',
        'fulfilled_quantity',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'requested_quantity' => 'decimal:3',
            'fulfilled_quantity' => 'decimal:3',
        ];
    }

    public function inventoryRequest(): BelongsTo
    {
        return $this->belongsTo(InventoryRequest::class);
    }

    protected function departmentAccessRelation(): ?string
    {
        return 'inventoryRequest';
    }

    protected function branchAccessRelation(): ?string
    {
        return 'inventoryRequest';
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
