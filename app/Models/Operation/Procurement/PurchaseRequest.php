<?php

namespace App\Models\Operation\Procurement;

use App\Models\Master\Organization\Branch;
use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\CostCenter;
use App\Models\Master\Organization\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRequest extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'opt_purchase_request';

    protected $fillable = [
        'number',
        'request_date',
        'requested_by',
        'company_id',
        'branch_id',
        'department_id',
        'cost_center_id',
        'required_date',
        'purpose',
        'description',
        'document_status',
        'approval_status',
    ];

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'cost_center_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            PurchaseRequestItem::class,
            'purchase_request_id'
        )->orderBy('line_number');
    }

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'required_date' => 'date',
        ];
    }
}
