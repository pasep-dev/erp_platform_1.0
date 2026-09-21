<?php

namespace App\Models\Master\Procurement;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Master\Procurement\VendorType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Master\Procurement\ProcurementChannel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Vendor extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'mst_vendors';

    protected $fillable = [
        'vendor_type_id',
        'code',
        'name',
        'tax_number',
        'email',
        'phone',
        'address',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function vendorType(): BelongsTo
    {
        return $this->belongsTo(VendorType::class, 'vendor_type_id');
    }

    public function procurementChannels(): BelongsToMany
    {
        return $this->belongsToMany(
            ProcurementChannel::class,
            'mst_vendor_channels',
            'vendor_id',
            'procurement_channel_id'
        )->withTimestamps();
    }
}
