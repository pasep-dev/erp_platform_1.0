<?php

namespace App\Services\AssetManagement;

use App\Enums\NumberingModuleCode;
use App\Models\Master\Organization\Branch;
use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\AssetManagement\AssetMaintenanceRecord;
use App\Models\Transaction\AssetManagement\AssetTransferHistory;
use App\Services\BranchAccessService;
use App\Services\DepartmentAccessService;
use App\Services\NumberingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetTransferService
{
    public function __construct(
        private readonly DepartmentAccessService $departmentAccess,
        private readonly BranchAccessService $branchAccess,
        private readonly BranchWarehouseResolver $warehouseResolver,
        private readonly NumberingService $numberingService,
    ) {}

    /**
     * @param  array{branch_id: string, effective_at: string, reason: string}  $data
     */
    public function transfer(Asset $asset, array $data, User $actor): AssetTransferHistory
    {
        return DB::transaction(function () use ($asset, $data, $actor): AssetTransferHistory {
            $lockedAsset = Asset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedAsset->status !== Asset::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'asset_id' => 'Hanya aset aktif yang dapat dipindahkan.',
                ]);
            }

            if ($lockedAsset->maintenanceRecords()->whereIn('status', [
                AssetMaintenanceRecord::STATUS_REQUESTED,
                AssetMaintenanceRecord::STATUS_WAITING_APPROVAL,
                AssetMaintenanceRecord::STATUS_WAITING_PARTS,
                AssetMaintenanceRecord::STATUS_READY_TO_START,
                AssetMaintenanceRecord::STATUS_IN_PROGRESS,
            ])->exists()) {
                throw ValidationException::withMessages([
                    'asset_id' => 'Aset memiliki maintenance yang belum selesai dan belum dapat dipindahkan.',
                ]);
            }

            $branch = Branch::query()
                ->whereKey($data['branch_id'])
                ->where('company_id', $lockedAsset->company_id)
                ->where('is_active', true)
                ->first();

            if ($branch === null) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Branch tujuan harus aktif dan berada di perusahaan aset.',
                ]);
            }

            $this->departmentAccess->authorize($actor, $lockedAsset->department_id);
            $this->branchAccess->authorize($actor, $lockedAsset->branch_id);
            $this->branchAccess->authorize($actor, (string) $branch->id);

            if ($lockedAsset->branch_id === $branch->id) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Pilih branch yang berbeda dari branch aset saat ini.',
                ]);
            }

            $warehouse = $this->warehouseResolver->resolve($branch);

            $lockedAsset->load(['company:id,code,name', 'department:id,code,name', 'location:id,code,name', 'branch:id,code,name', 'type:id,code', 'category:id,code', 'product:id,code', 'custodian:id,name,email']);
            $warehouse->loadMissing(['company:id,code,name', 'branch:id,code,name']);
            $fromSnapshot = $this->snapshot($lockedAsset);

            $number = $this->numberingService->generate(
                NumberingModuleCode::AssetTransfer,
                [
                    'company' => $lockedAsset->company?->code,
                    'department' => $lockedAsset->department?->code,
                    'branch' => $branch->code,
                    'location' => $warehouse->code,
                    'asset_type' => $lockedAsset->type?->code,
                    'category' => $lockedAsset->category?->code,
                    'product' => $lockedAsset->product?->code,
                    'source_company' => $lockedAsset->company?->code,
                    'source_department' => $lockedAsset->department?->code,
                    'source_branch' => $lockedAsset->branch?->code,
                    'source_location' => $lockedAsset->location?->code,
                    'target_company' => $lockedAsset->company?->code,
                    'target_department' => $lockedAsset->department?->code,
                    'target_branch' => $branch->code,
                    'target_location' => $warehouse->code,
                    'document_type' => 'ASSET_TRANSFER',
                ],
            );

            $lockedAsset->fill([
                'location_id' => $warehouse->id,
                'branch_id' => $branch->id,
                'custodian_id' => null,
                'assignment_type' => Asset::ASSIGNMENT_LOCATION,
            ])->save();
            $lockedAsset->setRelation('location', $warehouse);
            $lockedAsset->setRelation('branch', $branch);

            return $lockedAsset->transferHistories()->create([
                'number' => $number,
                'from_snapshot' => $fromSnapshot,
                'to_snapshot' => $this->snapshot($lockedAsset),
                'effective_at' => $data['effective_at'],
                'reason' => $data['reason'],
                'performed_by' => $actor->id,
            ]);
        }, 3);
    }

    /**
     * @return array<string, int|string|null>
     */
    private function snapshot(Asset $asset): array
    {
        return [
            'company_id' => $asset->company_id,
            'company' => $asset->company?->code.' · '.$asset->company?->name,
            'department_id' => $asset->department_id,
            'department' => $asset->department?->code.' · '.$asset->department?->name,
            'branch_id' => $asset->branch_id,
            'branch' => $asset->branch?->code.' · '.$asset->branch?->name,
            'location_id' => $asset->location_id,
            'location' => $asset->location?->code.' · '.$asset->location?->name,
            'custodian_id' => $asset->custodian_id,
            'custodian' => $asset->custodian?->name,
        ];
    }
}
