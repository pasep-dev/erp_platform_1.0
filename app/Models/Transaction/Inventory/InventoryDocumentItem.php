<?php

namespace App\Models\Transaction\Inventory;

use App\Models\Concerns\HasBranchAccessScope;
use App\Models\Concerns\HasDepartmentAccessScope;
use App\Models\Master\Organization\Location;
use App\Models\Master\Product\Product;
use App\Models\Transaction\AssetManagement\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryDocumentItem extends Model
{
    use HasBranchAccessScope;
    use HasDepartmentAccessScope;
    use HasUlids;

    protected $table = 'trx_inventory_document_items';

    protected $fillable = [
        'inventory_document_id',
        'product_id',
        'quantity',
        'serial_numbers',
        'party_type',
        'party_user_id',
        'party_asset_id',
        'party_location_id',
        'related_issue_item_id',
        'inventory_request_item_id',
        'inventory_stocktake_item_id',
        'specification_changes',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'serial_numbers' => 'array',
            'specification_changes' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(InventoryDocument::class, 'inventory_document_id');
    }

    protected function departmentAccessRelation(): ?string
    {
        return 'document';
    }

    protected function branchAccessRelation(): ?string
    {
        return 'document';
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function partyUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'party_user_id');
    }

    public function partyAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'party_asset_id');
    }

    public function partyLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'party_location_id');
    }

    public function relatedIssueItem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_issue_item_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryStockMovement::class, 'inventory_document_item_id');
    }
}
