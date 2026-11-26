<?php

namespace App\Services\AssetManagement;

use App\Models\Master\Organization\Branch;
use App\Models\Master\Organization\Location;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BranchWarehouseResolver
{
    /**
     * @param  Collection<int, Branch>  $branches
     * @return Collection<int, array{id: string, company_id: string, code: string, name: string, warehouse_location_id: ?string, warehouse_code: ?string, warehouse_name: ?string, warehouse_count: int}>
     */
    public function options(Collection $branches): Collection
    {
        $warehousesByBranch = Location::query()
            ->withoutGlobalScope('branch_access')
            ->whereIn('branch_id', $branches->pluck('id'))
            ->where('is_active', true)
            ->whereRaw('UPPER(location_type) = ?', ['WAREHOUSE'])
            ->orderBy('code')
            ->get(['id', 'branch_id', 'code', 'name'])
            ->groupBy('branch_id');

        return $branches->map(function (Branch $branch) use ($warehousesByBranch): array {
            $warehouses = $warehousesByBranch->get($branch->id, collect());
            $warehouse = $warehouses->count() === 1 ? $warehouses->first() : null;

            return [
                'id' => (string) $branch->id,
                'company_id' => (string) $branch->company_id,
                'code' => $branch->code,
                'name' => $branch->name,
                'warehouse_location_id' => $warehouse === null ? null : (string) $warehouse->id,
                'warehouse_code' => $warehouse?->code,
                'warehouse_name' => $warehouse?->name,
                'warehouse_count' => $warehouses->count(),
            ];
        })->values();
    }

    public function resolve(Branch $branch): Location
    {
        $warehouses = Location::query()
            ->withoutGlobalScope('branch_access')
            ->with('company:id,code,name', 'branch:id,code,name')
            ->where('company_id', $branch->company_id)
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->whereRaw('UPPER(location_type) = ?', ['WAREHOUSE'])
            ->orderBy('code')
            ->get();

        if ($warehouses->count() !== 1) {
            $message = $warehouses->isEmpty()
                ? 'Branch tujuan belum memiliki location aktif dengan type Warehouse.'
                : 'Branch tujuan memiliki lebih dari satu location aktif dengan type Warehouse. Pastikan hanya ada satu warehouse default.';

            throw ValidationException::withMessages(['branch_id' => $message]);
        }

        return $warehouses->first();
    }
}
