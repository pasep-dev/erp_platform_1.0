<?php

namespace App\Services\AssetManagement;

use App\Enums\NumberingModuleCode;
use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\Department;
use App\Models\Master\Organization\Location;
use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\AssetManagement\AssetMaintenanceRecord;
use App\Models\Transaction\AssetManagement\AssetTransferHistory;
use App\Models\User;
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
        private readonly NumberingService $numberingService,
    ) {}

    /**
     * @param  array{company_id: string, department_id: string, location_id: string, effective_at: string, reason: string}  $data
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

            $company = Company::query()
                ->whereKey($data['company_id'])
                ->where('is_active', true)
                ->firstOrFail();
            $department = Department::query()
                ->whereKey($data['department_id'])
                ->where('company_id', $company->id)
                ->where('is_active', true)
                ->first();
            $location = Location::query()
                ->whereKey($data['location_id'])
                ->where('company_id', $company->id)
                ->where('is_active', true)
                ->first();

            if ($department === null || $location === null) {
                throw ValidationException::withMessages([
                    'company_id' => 'Departemen dan lokasi harus aktif serta berada di perusahaan tujuan.',
                ]);
            }

            $this->departmentAccess->authorize($actor, $department->id);
            $this->branchAccess->authorize($actor, $lockedAsset->branch_id);
            $this->branchAccess->authorize($actor, $location->branch_id);

            if (! $lockedAsset->product?->departments()->whereKey($department->id)->exists()) {
                throw ValidationException::withMessages([
                    'department_id' => 'Produk aset belum ditautkan ke department tujuan.',
                ]);
            }

            if ($lockedAsset->custodian_id !== null) {
                $custodian = User::query()->whereKey($lockedAsset->custodian_id)->first();
                if ($custodian !== null && ! $this->branchAccess->canAccess($custodian, $location->branch_id)) {
                    throw ValidationException::withMessages([
                        'location_id' => 'Penanggung jawab aset tidak memiliki akses ke branch tujuan.',
                    ]);
                }
            }

            if (
                $lockedAsset->company_id === $company->id
                && $lockedAsset->department_id === $department->id
                && $lockedAsset->location_id === $location->id
                && $lockedAsset->branch_id === $location->branch_id
            ) {
                throw ValidationException::withMessages([
                    'location_id' => 'Penempatan tujuan sama dengan penempatan aset saat ini.',
                ]);
            }

            if ($lockedAsset->company_id !== $company->id && $lockedAsset->custodian_id !== null) {
                $custodian = User::query()->whereKey($lockedAsset->custodian_id)->first();

                if ($custodian?->company_id !== null && $custodian->company_id !== $company->id) {
                    throw ValidationException::withMessages([
                        'asset_id' => 'Kembalikan aset dari penanggung jawab saat ini sebelum memindahkannya ke perusahaan lain.',
                    ]);
                }
            }

            $lockedAsset->load(['company:id,code,name', 'department:id,code,name', 'location:id,code,name', 'branch:id,code,name', 'type:id,code', 'category:id,code', 'product:id,code', 'custodian:id,name,email']);
            $location->loadMissing(['company:id,code,name', 'branch:id,code,name']);
            $department->loadMissing('company:id,code,name');
            $fromSnapshot = $this->snapshot($lockedAsset);

            $number = $this->numberingService->generate(
                NumberingModuleCode::AssetTransfer,
                [
                    'company' => $company->code,
                    'department' => $department->code,
                    'branch' => $location->branch?->code,
                    'location' => $location->code,
                    'asset_type' => $lockedAsset->type?->code,
                    'category' => $lockedAsset->category?->code,
                    'product' => $lockedAsset->product?->code,
                    'source_company' => $lockedAsset->company?->code,
                    'source_department' => $lockedAsset->department?->code,
                    'source_branch' => $lockedAsset->branch?->code,
                    'source_location' => $lockedAsset->location?->code,
                    'target_company' => $company->code,
                    'target_department' => $department->code,
                    'target_branch' => $location->branch?->code,
                    'target_location' => $location->code,
                    'document_type' => 'ASSET_TRANSFER',
                ],
            );

            $lockedAsset->fill([
                'company_id' => $company->id,
                'department_id' => $department->id,
                'location_id' => $location->id,
                'branch_id' => $location->branch_id,
            ])->save();
            $lockedAsset->setRelation('company', $company);
            $lockedAsset->setRelation('department', $department);
            $lockedAsset->setRelation('location', $location);
            $lockedAsset->setRelation('branch', $location->branch);

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
