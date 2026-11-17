<?php

namespace App\Models\Master\Procurement;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code',
    'name',
    'requires_asset',
    'is_active',
])]
#[Hidden([])]
class ServiceType extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;

    protected $table = 'mst_service_types';

    protected $fillable = [
        'code',
        'name',
        'requires_asset',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_asset' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
