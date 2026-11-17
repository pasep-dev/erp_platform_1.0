<?php

namespace App\Services\AssetManagement;

use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\AssetManagement\AssetAssignmentHistory;
use App\Models\Transaction\AssetManagement\AssetMaintenanceRecord;
use App\Models\User;
use App\Services\BranchAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetAssignmentService
{
    /**
     * @return array<string, mixed>
     */
    private function snapshot(Asset $asset): array
    {
        $asset->loadMissing([
            'custodian:id,name,email',
            'company:id,code,name',
            'department:id,code,name',
            'location:id,code,name',
            'branch:id,code,name',
        ]);

        return [
            'custodian_id' => $asset->custodian_id,
            'custodian_name' => $asset->custodian?->name,
            'custodian_email' => $asset->custodian?->email,
            'company' => $asset->company ? $asset->company->code.' · '.$asset->company->name : null,
            'department' => $asset->department ? $asset->department->code.' · '.$asset->department->name : null,
            'location' => $asset->location ? $asset->location->code.' · '.$asset->location->name : null,
            'branch' => $asset->branch ? $asset->branch->code.' · '.$asset->branch->name : null,
        ];
    }

    public function assign(Asset $asset, User $assignee, User $actor, string $condition, string $effectiveAt, ?string $remarks): AssetAssignmentHistory
    {
        return DB::transaction(function () use ($asset, $assignee, $actor, $condition, $effectiveAt, $remarks): AssetAssignmentHistory {
            $lockedAsset = Asset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();
            $activeAssignee = User::query()->whereKey($assignee->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedAsset->status !== Asset::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'assignee_id' => 'Aset tidak aktif dan tidak dapat ditugaskan.',
                ]);
            }

            if ($lockedAsset->maintenanceRecords()->where('status', AssetMaintenanceRecord::STATUS_IN_PROGRESS)->exists()) {
                throw ValidationException::withMessages([
                    'asset' => 'Aset sedang dalam pengerjaan maintenance dan belum dapat ditugaskan.',
                ]);
            }

            if (! $activeAssignee->is_active || ($activeAssignee->company_id !== null && $activeAssignee->company_id !== $lockedAsset->company_id)) {
                throw ValidationException::withMessages([
                    'assignee_id' => 'Penanggung jawab harus aktif dan berasal dari perusahaan aset.',
                ]);
            }

            if (! $activeAssignee->hasGlobalDepartmentAccess()
                && $activeAssignee->department_id !== $lockedAsset->department_id
                && ! $activeAssignee->additionalDepartments()->whereKey($lockedAsset->department_id)->exists()) {
                throw ValidationException::withMessages([
                    'assignee_id' => 'Penanggung jawab harus berasal dari department aset atau diberi akses ke department tersebut.',
                ]);
            }

            if (! app(BranchAccessService::class)->canAccess($activeAssignee, $lockedAsset->branch_id)) {
                throw ValidationException::withMessages([
                    'assignee_id' => 'Penanggung jawab harus memiliki akses ke branch aset.',
                ]);
            }

            if ((int) $lockedAsset->custodian_id === (int) $activeAssignee->id) {
                throw ValidationException::withMessages([
                    'assignee_id' => 'Aset sudah ditugaskan kepada pengguna tersebut.',
                ]);
            }

            $lockedAsset->load(['custodian', 'company', 'department', 'location', 'branch']);
            $fromSnapshot = $this->snapshot($lockedAsset);
            $fromCustodianId = $lockedAsset->custodian_id;
            $fromCondition = $lockedAsset->condition;

            $lockedAsset->custodian_id = $activeAssignee->id;
            $lockedAsset->condition = $condition;
            $lockedAsset->save();
            $lockedAsset->setRelation('custodian', $activeAssignee);

            return $lockedAsset->assignmentHistories()->create([
                'event_type' => $fromCustodianId === null ? 'ASSIGNED' : 'TRANSFERRED',
                'from_condition' => $fromCondition,
                'to_condition' => $condition,
                'from_custodian_id' => $fromCustodianId,
                'to_custodian_id' => $activeAssignee->id,
                'from_snapshot' => $fromSnapshot,
                'to_snapshot' => $this->snapshot($lockedAsset),
                'performed_by' => $actor->id,
                'effective_at' => $effectiveAt,
                'remarks' => $remarks,
            ]);
        }, 3);
    }

    public function returnAsset(Asset $asset, User $actor, string $condition, string $effectiveAt, ?string $remarks): AssetAssignmentHistory
    {
        return DB::transaction(function () use ($asset, $actor, $condition, $effectiveAt, $remarks): AssetAssignmentHistory {
            $lockedAsset = Asset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedAsset->custodian_id === null) {
                throw ValidationException::withMessages([
                    'asset' => 'Aset ini berstatus Available dan tidak memiliki penanggung jawab aktif.',
                ]);
            }

            $lockedAsset->load(['custodian', 'company', 'department', 'location', 'branch']);
            $fromSnapshot = $this->snapshot($lockedAsset);
            $fromCustodianId = $lockedAsset->custodian_id;
            $fromCondition = $lockedAsset->condition;

            $lockedAsset->custodian_id = null;
            $lockedAsset->condition = $condition;
            $lockedAsset->save();
            $lockedAsset->setRelation('custodian', null);

            return $lockedAsset->assignmentHistories()->create([
                'event_type' => 'RETURNED',
                'from_condition' => $fromCondition,
                'to_condition' => $condition,
                'from_custodian_id' => $fromCustodianId,
                'to_custodian_id' => null,
                'from_snapshot' => $fromSnapshot,
                'to_snapshot' => $this->snapshot($lockedAsset),
                'performed_by' => $actor->id,
                'effective_at' => $effectiveAt,
                'remarks' => $remarks,
            ]);
        }, 3);
    }
}
