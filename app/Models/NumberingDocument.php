<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NumberingDocument extends Model
{
    use HasUlids;

    protected $fillable = [
        'code',
        'name',
        'pattern',
        'sequence_length',
        'reset_period',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sequence_length' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function sequences(): HasMany
    {
        return $this->hasMany(NumberingSequence::class);
    }
}
