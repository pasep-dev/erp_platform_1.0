<?php

namespace App\Models\Master\Organization;

use App\Models\Master\Product\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'mst_org_departments';

    protected $fillable = [
        'company_id',
        'code',
        'name',
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

    public function additionalUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_department_access')
            ->withTimestamps();
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'mst_product_departments')
            ->withTimestamps();
    }
}
