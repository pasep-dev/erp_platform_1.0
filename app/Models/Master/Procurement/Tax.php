<?php

namespace App\Models\Master\Procurement;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tax extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'mst_taxes';

    protected $fillable = [
        'code',
        'name',
        'rate',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }
}
