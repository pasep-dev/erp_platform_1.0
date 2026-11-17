<?php

namespace App\Models\Master\Product;

use App\Models\Concerns\HasBranchAccessScope;
use App\Models\Master\Organization\Branch;
use App\Models\Master\Organization\Department;
use App\Models\Master\Procurement\UnitOfMeasure;
use App\Models\Master\Product\Model as AssetModel;
use App\Models\Transaction\Inventory\InventoryStockMovement;
use App\Services\DepartmentAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends EloquentModel
{
    use HasBranchAccessScope;
    use HasUlids;
    use SoftDeletes;

    protected $table = 'mst_products';

    protected static function booted(): void
    {
        static::addGlobalScope('department_access', function (Builder $query): void {
            $user = auth()->user();
            if ($user === null || $user->hasGlobalDepartmentAccess()) {
                return;
            }

            $departmentIds = app(DepartmentAccessService::class)->departmentIds($user);
            $query->whereHas('departments', fn (Builder $departments) => $departments
                ->whereIn('mst_org_departments.id', $departmentIds));
        });
    }

    protected $fillable = [
        'category_id',
        'type_id',
        'brand_id',
        'model_id',
        'group',
        'inventory_tracking_mode',
        'uom_id',
        'base_uom_id',
        'code',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(Type::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(AssetModel::class, 'model_id');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function baseUnitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'base_uom_id');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryStockMovement::class, 'product_id');
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'product_department')
            ->withTimestamps();
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'product_branch')->withTimestamps();
    }

    protected function branchAccessRelation(): ?string
    {
        return 'branches';
    }
}
