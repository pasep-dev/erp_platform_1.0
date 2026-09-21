<?php

namespace App\Models\Operation\Procurement;

use App\Models\Master\Procurement\UnitOfMeasure;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestItem extends Model
{
    use HasUlids;

    protected $table = 'opt_purchase_request_items';

    protected $fillable = [
        'purchase_request_id',
        'line_number',
        'item_description',
        'management_class',
        'quantity',
        'uom_id',
        'estimated_unit_price',
        'estimated_total',
        'specification',
        'notes',
    ];

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(
            PurchaseRequest::class,
            'purchase_request_id'
        );
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(
            UnitOfMeasure::class,
            'uom_id'
        );
    }

    protected function casts(): array
    {
        return [
            'line_number' => 'integer',
            'quantity' => 'decimal:4',
            'estimated_unit_price' => 'decimal:2',
            'estimated_total' => 'decimal:2',
        ];
    }
}
