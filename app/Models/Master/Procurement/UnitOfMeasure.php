<?php

namespace App\Models\Master\Procurement;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitOfMeasure extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'mst_prc_uom';

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
