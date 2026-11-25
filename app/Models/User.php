<?php

namespace App\Models;

use App\Models\Master\Organization\Branch;
use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\Department;
use App\Models\Master\Organization\Position;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'company_id',
        'branch_id',
        'department_id',
        'position_id',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function hasGlobalDepartmentAccess(): bool
    {
        return $this->hasRole(['Administrator', 'Owner']);
    }

    public function isAdministrator(): bool
    {
        return $this->hasRole('Administrator');
    }

    public function hasGlobalBranchAccess(): bool
    {
        return $this->hasRole(['Administrator', 'Owner']);
    }

    public function additionalDepartments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'user_department_access')
            ->withTimestamps();
    }

    public function additionalBranches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'user_branch_access')
            ->withTimestamps();
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }
}
