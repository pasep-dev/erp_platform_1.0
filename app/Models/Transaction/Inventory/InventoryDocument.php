<?php

namespace App\Models\Transaction\Inventory;

use App\Models\Administrator\Workflow\ApprovalRequest;
use App\Models\Concerns\HasBranchAccessScope;
use App\Models\Concerns\HasDepartmentAccessScope;
use App\Models\Master\Organization\Department;
use App\Models\Master\Organization\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryDocument extends Model
{
    use HasBranchAccessScope;
    use HasDepartmentAccessScope;
    use HasUlids;

    public const TYPE_ISSUE = 'ISSUE';

    public const TYPE_TRANSFER = 'TRANSFER';

    public const TYPE_RETURN = 'RETURN';

    public const TYPE_ADJUSTMENT = 'ADJUSTMENT';

    public const TYPE_OPENING_BALANCE = 'OPENING_BALANCE';

    public const STATUS_POSTED = 'POSTED';

    public const STATUS_PENDING_APPROVAL = 'PENDING_APPROVAL';

    public const STATUS_REJECTED = 'REJECTED';

    protected $table = 'inventory_documents';

    protected $fillable = [
        'number',
        'department_id',
        'document_type',
        'status',
        'source_location_id',
        'target_location_id',
        'adjustment_direction',
        'occurred_at',
        'remarks',
        'performed_by',
        'approval_request_id',
        'posted_by',
        'submitted_at',
        'posted_at',
        'approval_mode',
        'manual_approval_document_path',
        'manual_approval_remarks',
        'manual_approved_by',
        'manual_approved_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'submitted_at' => 'datetime',
            'posted_at' => 'datetime',
            'manual_approved_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'source_location_id');
    }

    public function targetLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'target_location_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function manualApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manual_approved_by');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryDocumentItem::class, 'inventory_document_id');
    }
}
