<?php

namespace App\Models\Concerns;

use App\Services\BranchAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

trait HasBranchAccessScope
{
    protected static function bootHasBranchAccessScope(): void
    {
        static::addGlobalScope('branch_access', function (Builder $query): void {
            $user = Auth::user();
            if ($user === null || $user->hasGlobalBranchAccess()) {
                return;
            }

            $branchIds = app(BranchAccessService::class)->branchIds($user);
            if ($branchIds === []) {
                $query->whereRaw('1 = 0');

                return;
            }

            $model = $query->getModel();
            if ($model->getTable() === 'mst_org_branches') {
                $query->whereIn($model->qualifyColumn($model->getKeyName()), $branchIds);

                return;
            }

            $table = $model->getTable();
            if (Schema::hasColumn($table, 'branch_id')) {
                $query->where(function (Builder $branchQuery) use ($model, $branchIds, $table): void {
                    $branchQuery->whereIn($model->qualifyColumn('branch_id'), $branchIds);

                    if (Schema::hasColumn($table, 'location_id') && method_exists($model, 'location')) {
                        $branchQuery->orWhere(function (Builder $fallbackQuery) use ($model, $branchIds): void {
                            $fallbackQuery->whereNull($model->qualifyColumn('branch_id'))
                                ->whereHas('location', fn (Builder $locationQuery) => $locationQuery->whereIn('branch_id', $branchIds));
                        });
                    }
                });

                return;
            }

            if (Schema::hasColumn($table, 'location_id') && method_exists($model, 'location')) {
                $query->whereHas('location', fn (Builder $locationQuery) => $locationQuery->whereIn('branch_id', $branchIds));

                return;
            }

            foreach ([
                ['source_location_id', 'sourceLocation'],
                ['target_location_id', 'targetLocation'],
                ['warehouse_location_id', 'warehouseLocation'],
                ['storage_location_id', 'storageLocation'],
            ] as [$foreignKey, $relation]) {
                if (! Schema::hasColumn($table, $foreignKey) || ! method_exists($model, $relation)) {
                    continue;
                }

                $query->where(function (Builder $locationQuery) use ($model, $foreignKey, $relation, $branchIds): void {
                    $locationQuery->whereNull($model->qualifyColumn($foreignKey))
                        ->orWhereHas($relation, fn (Builder $relatedQuery) => $relatedQuery->whereIn('branch_id', $branchIds));
                });
            }

            $relation = method_exists($model, 'branchAccessRelation') ? $model->branchAccessRelation() : null;
            if ($relation !== null) {
                if ($relation === 'document' && method_exists($model, 'document')) {
                    $query->whereHas('document', function (Builder $documentQuery) use ($branchIds): void {
                        $document = $documentQuery->getModel();
                        $hasLocation = false;

                        foreach ([['source_location_id', 'sourceLocation'], ['target_location_id', 'targetLocation']] as [$foreignKey, $locationRelation]) {
                            if (! Schema::hasColumn($document->getTable(), $foreignKey) || ! method_exists($document, $locationRelation)) {
                                continue;
                            }

                            $hasLocation = true;
                            $documentQuery->where(function (Builder $locationQuery) use ($document, $foreignKey, $locationRelation, $branchIds): void {
                                $locationQuery->whereNull($document->qualifyColumn($foreignKey))
                                    ->orWhereHas($locationRelation, fn (Builder $branchQuery) => $branchQuery->whereIn('branch_id', $branchIds));
                            });
                        }

                        if ($hasLocation) {
                            $documentQuery->where(function (Builder $locationQuery) use ($document): void {
                                $locationQuery->whereNotNull($document->qualifyColumn('source_location_id'))
                                    ->orWhereNotNull($document->qualifyColumn('target_location_id'));
                            });
                        }
                    });

                    return;
                }

                if (method_exists($model, 'branchAccessRelationRequiresAll') && $model->branchAccessRelationRequiresAll()) {
                    $query->whereHas($relation, fn (Builder $relatedQuery) => $relatedQuery
                        ->withoutGlobalScope('department_access')
                        ->whereIn('branch_id', $branchIds))
                        ->whereDoesntHave($relation, fn (Builder $relatedQuery) => $relatedQuery
                            ->withoutGlobalScope('branch_access')
                            ->withoutGlobalScope('department_access')
                            ->where(function (Builder $unauthorizedQuery) use ($branchIds): void {
                                $unauthorizedQuery->whereNull('branch_id')
                                    ->orWhereNotIn('branch_id', $branchIds);
                            }));
                } else {
                    $query->whereHas($relation, fn (Builder $relatedQuery) => $relatedQuery
                        ->withoutGlobalScope('department_access')
                        ->whereIn('branch_id', $branchIds));
                }
            }
        });
    }

    protected function branchAccessRelation(): ?string
    {
        return null;
    }

    protected function branchAccessRelationRequiresAll(): bool
    {
        return false;
    }
}
