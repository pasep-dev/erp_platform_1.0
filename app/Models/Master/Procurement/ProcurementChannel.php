<?php

namespace App\Models\Master\Procurement;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcurementChannel extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'mst_procurement_channels';

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

    public function vendors(): BelongsToMany
    {
        return $this->belongsToMany(
            Vendor::class,
            'mst_vendor_channels',
            'procurement_channel_id',
            'vendor_id'
        )->withTimestamps();
    }
}
