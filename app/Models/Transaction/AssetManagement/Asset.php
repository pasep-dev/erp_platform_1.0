<?php

namespace App\Models\Transaction\AssetManagement;

use App\Models\Concerns\HasBranchAccessScope;
use App\Models\Concerns\HasDepartmentAccessScope;
use App\Models\Master\Organization\Branch;
use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\Department;
use App\Models\Master\Organization\Location;
use App\Models\Master\Product\Brand;
use App\Models\Master\Product\Category;
use App\Models\Master\Product\Model as AssetModel;
use App\Models\Master\Product\Product;
use App\Models\Master\Product\Type;
use App\Models\Transaction\Procurement\GoodsReceiptItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'number',
    'registration_method',
    'status',
    'condition',
    'product_id',
    'category_id',
    'type_id',
    'brand_id',
    'model_id',
    'company_id',
    'department_id',
    'location_id',
    'branch_id',
    'custodian_id',
    'assignment_type',
    'goods_receipt_item_id',
    'source_unit_number',
    'serial_number',
    'specifications',
    'warranty_start_date',
    'warranty_end_date',
    'warranty_duration_months',
    'acquisition_date',
    'acquisition_cost',
    'remarks',
    'registered_by',
])]
class Asset extends Model
{
    use HasBranchAccessScope;
    use HasDepartmentAccessScope;
    use HasUlids;

    public const METHOD_PROCUREMENT = 'PROCUREMENT';

    public const METHOD_EXISTING = 'EXISTING';

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_MISSING = 'MISSING';

    public const STATUS_DISPOSAL_PENDING = 'DISPOSAL_PENDING';

    public const STATUS_DISPOSED = 'DISPOSED';

    public const ASSIGNMENT_USER = 'USER';

    public const ASSIGNMENT_LOCATION = 'LOCATION';

    public const CONDITION_OPTIONS = [
        'GOOD' => 'Baik',
        'FAIR' => 'Cukup',
        'NEEDS_REPAIR' => 'Perlu Perbaikan',
        'DAMAGED' => 'Rusak',
    ];

    protected $table = 'trx_assets';

    protected $fillable = [
        'number',
        'registration_method',
        'status',
        'condition',
        'product_id',
        'category_id',
        'type_id',
        'brand_id',
        'model_id',
        'company_id',
        'department_id',
        'location_id',
        'branch_id',
        'custodian_id',
        'assignment_type',
        'goods_receipt_item_id',
        'source_unit_number',
        'serial_number',
        'specifications',
        'warranty_start_date',
        'warranty_end_date',
        'warranty_duration_months',
        'acquisition_date',
        'acquisition_cost',
        'remarks',
        'registered_by',
    ];

    protected function casts(): array
    {
        return [
            'source_unit_number' => 'integer',
            'specifications' => 'array',
            'warranty_start_date' => 'date:Y-m-d',
            'warranty_end_date' => 'date:Y-m-d',
            'warranty_duration_months' => 'integer',
            'acquisition_date' => 'date:Y-m-d',
            'acquisition_cost' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }

    public function goodsReceiptItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptItem::class, 'goods_receipt_item_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function assignmentHistories(): HasMany
    {
        return $this->hasMany(AssetAssignmentHistory::class);
    }

    public function transferHistories(): HasMany
    {
        return $this->hasMany(AssetTransferHistory::class);
    }

    public function disposals(): HasMany
    {
        return $this->hasMany(AssetDisposal::class);
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(AssetMaintenanceRecord::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(AssetIncident::class);
    }

    public function auditItems(): HasMany
    {
        return $this->hasMany(AssetAuditItem::class);
    }

    public function latestMaintenanceRecord(): HasOne
    {
        return $this->hasOne(AssetMaintenanceRecord::class)->latestOfMany();
    }
}
