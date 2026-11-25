<?php

namespace App\Models;

use App\Enums\NumberingModuleCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NumberingModule extends Model
{
    /**
     * @return array<int, array{code: string, name: string, category: string}>
     */
    public static function availableModules(): array
    {
        return array_map(
            static fn (NumberingModuleCode $module): array => $module->moduleDetails(),
            NumberingModuleCode::cases(),
        );
    }

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'category',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function numberingDocuments(): HasMany
    {
        return $this->hasMany(NumberingDocument::class, 'code', 'code');
    }
}
