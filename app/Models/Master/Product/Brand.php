<?php

namespace App\Models\Master\Product;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'mst_product_brands';

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

    public function models(): HasMany
    {
        return $this->hasMany(Model::class, 'brand_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'mst_product_brand_categories', 'brand_id', 'category_id');
    }
}
