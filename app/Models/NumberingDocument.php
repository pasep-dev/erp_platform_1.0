<?php

namespace App\Models;

use App\Enums\NumberingModuleCode;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NumberingDocument extends Model
{
    use HasUlids;

    public const CODE_VENDOR = NumberingModuleCode::Vendor->value;

    public const CODE_VENDOR_TYPE = NumberingModuleCode::VendorType->value;

    public const CODE_PRODUCT = NumberingModuleCode::Product->value;

    public const CODE_PRODUCT_TYPE = NumberingModuleCode::ProductType->value;

    public const CODE_PRODUCT_BRAND = NumberingModuleCode::ProductBrand->value;

    public const CODE_ASSET = NumberingModuleCode::Asset->value;

    public const CODE_ASSET_MAINTENANCE = NumberingModuleCode::AssetMaintenance->value;

    public const CODE_PURCHASE_REQUEST = NumberingModuleCode::PurchaseRequest->value;

    public const CODE_PURCHASE_ORDER = NumberingModuleCode::PurchaseOrder->value;

    public const CODE_GOODS_RECEIPT = NumberingModuleCode::GoodsReceipt->value;

    public const SUPPORTED_PLACEHOLDERS = [
        'YYYY',
        'YY',
        'MM',
        'DD',
        'SEQ',
        'COMPANY',
        'SOURCE_COMPANY',
        'TARGET_COMPANY',
        'ASSET_TYPE',
        'CATEGORY',
        'PRODUCT',
        'VENDOR',
        'BRANCH',
        'SOURCE_BRANCH',
        'TARGET_BRANCH',
        'DOCUMENT_TYPE',
        'EVENT_TYPE',
        'DEPARTMENT',
        'SOURCE_DEPARTMENT',
        'TARGET_DEPARTMENT',
        'LOCATION',
        'SOURCE_LOCATION',
        'TARGET_LOCATION',
        'DIRECTION',
    ];

    public const SCOPE_PLACEHOLDERS = [
        'COMPANY',
        'SOURCE_COMPANY',
        'TARGET_COMPANY',
        'ASSET_TYPE',
        'CATEGORY',
        'PRODUCT',
        'VENDOR',
        'BRANCH',
        'SOURCE_BRANCH',
        'TARGET_BRANCH',
        'DOCUMENT_TYPE',
        'EVENT_TYPE',
        'DEPARTMENT',
        'SOURCE_DEPARTMENT',
        'TARGET_DEPARTMENT',
        'LOCATION',
        'SOURCE_LOCATION',
        'TARGET_LOCATION',
        'DIRECTION',
    ];

    public const GENERATED_MASTER_CODES = [
        self::CODE_VENDOR,
        self::CODE_VENDOR_TYPE,
        self::CODE_PRODUCT,
        self::CODE_PRODUCT_TYPE,
        self::CODE_PRODUCT_BRAND,
    ];

    protected $fillable = [
        'code',
        'name',
        'pattern',
        'sequence_length',
        'sequence_start',
        'reset_period',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sequence_length' => 'integer',
            'sequence_start' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function sequences(): HasMany
    {
        return $this->hasMany(NumberingSequence::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(NumberingModule::class, 'code', 'code');
    }
}
