<?php

namespace App\Models\Transaction\Inventory;

use App\Models\Administrator\Workflow\ApprovalRequest;
use App\Models\Concerns\HasBranchAccessScope;
use App\Models\Concerns\HasDepartmentAccessScope;
use App\Models\Master\Organization\Branch;
use App\Models\Master\Organization\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryRequest extends Model
{
    protected $table = 'trx_inventory_requests';

    use HasBranchAccessScope;
    use HasDepartmentAccessScope;
    use HasUlids;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_PENDING_APPROVAL = 'PENDING_APPROVAL';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_PARTIALLY_FULFILLED = 'PARTIALLY_FULFILLED';

    public const STATUS_FULFILLED = 'FULFILLED';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'number',
        'department_id',
        'branch_id',
        'requested_by',
        'approval_request_id',
        'status',
        'requested_at',
        'required_at',
        'submitted_at',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'date:Y-m-d',
            'required_at' => 'date:Y-m-d',
            'submitted_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryRequestItem::class);
    }
}
