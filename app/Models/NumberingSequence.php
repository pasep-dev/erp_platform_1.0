<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NumberingSequence extends Model
{
    use HasUlids;

    protected $fillable = [
        'numbering_document_id',
        'scope_key',
        'period_key',
        'current_value',
    ];

    protected function casts(): array
    {
        return [
            'current_value' => 'integer',
        ];
    }

    public function numberingDocument(): BelongsTo
    {
        return $this->belongsTo(
            NumberingDocument::class,
            'numbering_document_id',
        );
    }
}
