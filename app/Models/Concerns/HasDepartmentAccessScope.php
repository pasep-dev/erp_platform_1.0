<?php

namespace App\Models\Concerns;

use App\Services\DepartmentAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

trait HasDepartmentAccessScope
{
    protected static function bootHasDepartmentAccessScope(): void
    {
        static::addGlobalScope('department_access', function (Builder $query): void {
            $user = Auth::user();
            if ($user === null || $user->hasGlobalDepartmentAccess()) {
                return;
            }

            $departmentIds = app(DepartmentAccessService::class)->departmentIds($user);
            $model = $query->getModel();
            $table = $model->getTable();

            if (Schema::hasColumn($table, 'department_id')) {
                $query->whereIn($model->qualifyColumn('department_id'), $departmentIds);

                return;
            }

            $relation = $model->departmentAccessRelation();
            if ($relation !== null) {
                $query->whereHas($relation, function (Builder $relatedQuery) use ($departmentIds): void {
                    $relatedModel = $relatedQuery->getModel();
                    $relatedTable = $relatedModel->getTable();

                    if (Schema::hasColumn($relatedTable, 'department_id')) {
                        $relatedQuery->whereIn($relatedModel->qualifyColumn('department_id'), $departmentIds);
                    } elseif (method_exists($relatedModel, 'asset')) {
                        $relatedQuery->whereHas('asset', fn (Builder $assetQuery) => $assetQuery
                            ->whereIn('trx_assets.department_id', $departmentIds));
                    }
                });
            }
        });
    }

    protected function departmentAccessRelation(): ?string
    {
        return null;
    }
}
