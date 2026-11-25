<?php

namespace App\Models\Transaction\Inventory;

use App\Models\Concerns\HasBranchAccessScope;
use App\Models\Concerns\HasDepartmentAccessScope;
use App\Models\Master\Organization\Department;
use App\Models\Master\Organization\Location;
use App\Models\Master\Product\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InventoryStockMovement extends Model
{
    use HasBranchAccessScope;
    use HasDepartmentAccessScope;
    use HasUlids;

    protected $table = 'trx_inventory_stock_movements';

    protected $fillable = [
        'product_id',
        'location_id',
        'department_id',
        'movement_type',
        'direction',
        'quantity',
        'serial_number',
        'idempotency_key',
        'source_type',
        'source_id',
        'source_line_id',
        'inventory_document_item_id',
        'related_issue_item_id',
        'reference_number',
        'occurred_at',
        'performed_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Inventory stock movements are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Inventory stock movements are append-only.'));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function documentItem(): BelongsTo
    {
        return $this->belongsTo(InventoryDocumentItem::class, 'inventory_document_item_id');
    }

    public function relatedIssueItem(): BelongsTo
    {
        return $this->belongsTo(InventoryDocumentItem::class, 'related_issue_item_id');
    }
}
