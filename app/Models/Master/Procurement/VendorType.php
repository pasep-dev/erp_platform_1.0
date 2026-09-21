<?php

namespace App\Models\Master\Procurement;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorType extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'mst_vendor_types';

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

    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class, 'vendor_type_id');
    }
}
