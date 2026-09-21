<?php

namespace App\Models\Master\Procurement;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentTerm extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'mst_payment_terms';

    protected $fillable = [
        'code',
        'name',
        'due_days',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'due_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
