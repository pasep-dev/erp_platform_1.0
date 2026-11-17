<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NumberingDocument extends Model
{
    use HasUlids;

    public const CODE_VENDOR = 'VENDOR';

    public const CODE_VENDOR_TYPE = 'VENDOR_TYPE';

    public const CODE_PRODUCT = 'PRODUCT';

    public const CODE_ASSET = 'ASSET';

    public const CODE_ASSET_MAINTENANCE = 'ASSET_MAINTENANCE';

    public const CODE_PURCHASE_REQUEST = 'PURCHASE_REQUEST';

    public const CODE_PURCHASE_ORDER = 'PURCHASE_ORDER';

    public const CODE_GOODS_RECEIPT = 'GOODS_RECEIPT';

    public const SUPPORTED_PLACEHOLDERS = [
        'YYYY',
        'YY',
        'MM',
        'DD',
        'SEQ',
        'COMPANY',
        'ASSET_TYPE',
        'BRANCH',
        'DOCUMENT_TYPE',
        'DEPARTMENT',
    ];

    public const SCOPE_PLACEHOLDERS = [
        'COMPANY',
        'ASSET_TYPE',
        'BRANCH',
        'DOCUMENT_TYPE',
        'DEPARTMENT',
    ];

    public const GENERATED_MASTER_CODES = [
        self::CODE_VENDOR,
        self::CODE_VENDOR_TYPE,
        self::CODE_PRODUCT,
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
