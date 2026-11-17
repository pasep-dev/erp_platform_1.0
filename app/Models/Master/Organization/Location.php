<?php

namespace App\Models\Master\Organization;

use App\Models\Concerns\HasBranchAccessScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Location extends Model
{
    use HasBranchAccessScope;
    use HasUlids;
    use SoftDeletes;

    protected $table = 'mst_org_locations';

    protected $fillable = [
        'company_id',
        'branch_id',
        'code',
        'name',
        'parent_id',
        'location_type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
