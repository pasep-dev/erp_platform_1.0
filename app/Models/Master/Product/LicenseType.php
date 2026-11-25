<?php

namespace App\Models\Master\Product;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LicenseType extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'mst_product_license_types';

    protected $fillable = [
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
}
