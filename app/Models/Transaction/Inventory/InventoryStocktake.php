<?php

namespace App\Models\Transaction\Inventory;

use App\Models\Concerns\HasBranchAccessScope;
use App\Models\Concerns\HasDepartmentAccessScope;
use App\Models\Master\Organization\Department;
use App\Models\Master\Organization\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryStocktake extends Model
{
    use HasBranchAccessScope;
    use HasDepartmentAccessScope;
    use HasUlids;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_COUNTED = 'COUNTED';

    public const STATUS_POSTED = 'POSTED';

    protected $table = 'inventory_stocktakes';

    protected $fillable = ['number', 'department_id', 'location_id', 'initiated_by', 'status', 'counted_at', 'posted_at', 'remarks'];

    protected function casts(): array
    {
        return ['counted_at' => 'datetime', 'posted_at' => 'datetime'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryStocktakeItem::class, 'inventory_stocktake_id');
    }
}
