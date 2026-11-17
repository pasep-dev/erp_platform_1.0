<?php

namespace App\Models\Administrator\Workflow;

use App\Models\Concerns\HasDepartmentAccessScope;
use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\Department;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApprovalWorkflow extends Model
{
    use HasDepartmentAccessScope;
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'approval_workflows';

    protected $fillable = [
        'module',
        'company_id',
        'department_id',
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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function levels(): HasMany
    {
        return $this->hasMany(ApprovalWorkflowLevel::class)
            ->orderBy('level');
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class);
    }
}
