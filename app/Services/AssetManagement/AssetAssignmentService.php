<?php

namespace App\Services\AssetManagement;

use App\Enums\NumberingModuleCode;
use App\Models\Master\Organization\Location;
use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\AssetManagement\AssetAssignmentHistory;
use App\Models\Transaction\AssetManagement\AssetMaintenanceRecord;
use App\Models\User;
use App\Services\BranchAccessService;
use App\Services\NumberingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetAssignmentService
{
    public function __construct(private readonly NumberingService $numberingService) {}

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
            'assignment_type' => $asset->assignment_type,
            'assignment_target' => $asset->assignment_type === Asset::ASSIGNMENT_USER
                ? $asset->custodian?->name
                : ($asset->location ? $asset->location->code.' · '.$asset->location->name : null),
            'custodian_id' => $asset->custodian_id,
            'custodian_name' => $asset->custodian?->name,
            'custodian_email' => $asset->custodian?->email,
            'company' => $asset->company ? $asset->company->code.' · '.$asset->company->name : null,
            'department' => $asset->department ? $asset->department->code.' · '.$asset->department->name : null,
            'location' => $asset->location ? $asset->location->code.' · '.$asset->location->name : null,
            'branch' => $asset->branch ? $asset->branch->code.' · '.$asset->branch->name : null,
        ];
    }

    public function assign(
        Asset $asset,
        string $assignmentType,
        ?User $assignee,
        ?Location $location,
        User $actor,
        string $condition,
        string $effectiveAt,
        ?string $remarks,
    ): AssetAssignmentHistory {
        return DB::transaction(function () use ($asset, $assignmentType, $assignee, $location, $actor, $condition, $effectiveAt, $remarks): AssetAssignmentHistory {
            $lockedAsset = Asset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedAsset->status !== Asset::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'assignment_target' => 'Aset tidak aktif dan tidak dapat ditugaskan.',
                ]);
            }

            if ($lockedAsset->maintenanceRecords()->where('status', AssetMaintenanceRecord::STATUS_IN_PROGRESS)->exists()) {
                throw ValidationException::withMessages([
                    'asset' => 'Aset sedang dalam pengerjaan maintenance dan belum dapat ditugaskan.',
                ]);
            }

            $activeAssignee = null;
            $targetLocation = null;

            if ($assignmentType === Asset::ASSIGNMENT_USER) {
                if ($assignee === null) {
                    throw ValidationException::withMessages(['assignee_id' => 'Pilih pengguna penanggung jawab.']);
                }

                $activeAssignee = User::query()->whereKey($assignee->getKey())->lockForUpdate()->firstOrFail();
                $this->assertAssigneeCanReceive($lockedAsset, $activeAssignee);

                if ((int) $lockedAsset->custodian_id === (int) $activeAssignee->id) {
                    throw ValidationException::withMessages([
                        'assignee_id' => 'Aset sudah ditugaskan kepada pengguna tersebut.',
                    ]);
                }
            } elseif ($assignmentType === Asset::ASSIGNMENT_LOCATION) {
                if ($location === null) {
                    throw ValidationException::withMessages(['location_id' => 'Pilih ruangan penempatan aset.']);
                }

                $targetLocation = Location::query()
                    ->withoutGlobalScope('branch_access')
                    ->whereKey($location->getKey())
                    ->where('company_id', $lockedAsset->company_id)
                    ->where(function ($query) use ($lockedAsset): void {
                        $query->whereNull('branch_id')
                            ->orWhere('branch_id', $lockedAsset->branch_id);
                    })
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if ($targetLocation === null) {
                    throw ValidationException::withMessages([
                        'location_id' => 'Ruangan harus aktif dan berada pada perusahaan serta branch aset.',
                    ]);
                }

                app(BranchAccessService::class)->authorize($actor, $targetLocation->branch_id ?? $lockedAsset->branch_id);

                if ($lockedAsset->assignment_type === Asset::ASSIGNMENT_LOCATION
                    && $lockedAsset->location_id === $targetLocation->id) {
                    throw ValidationException::withMessages([
                        'location_id' => 'Aset sudah ditempatkan di ruangan tersebut.',
                    ]);
                }
            } else {
                throw ValidationException::withMessages(['assignment_target' => 'Pilih jenis penugasan yang valid.']);
            }

            $lockedAsset->load([
                'custodian',
                'company',
                'department',
                'location',
                'branch',
                'type',
                'category',
                'product',
            ]);
            $fromSnapshot = $this->snapshot($lockedAsset);
            $fromCustodianId = $lockedAsset->custodian_id;
            $fromAssignmentType = $lockedAsset->assignment_type;
            $fromCondition = $lockedAsset->condition;

            $lockedAsset->assignment_type = $assignmentType;
            $lockedAsset->custodian_id = $activeAssignee?->id;
            if ($targetLocation !== null) {
                $lockedAsset->location_id = $targetLocation->id;
                $lockedAsset->branch_id = $targetLocation->branch_id ?? $lockedAsset->branch_id;
            }
            $lockedAsset->condition = $condition;
            $lockedAsset->save();
            $lockedAsset->setRelation('custodian', $activeAssignee);
            if ($targetLocation !== null) {
                $lockedAsset->setRelation('location', $targetLocation);
            }

            $eventType = match ($assignmentType) {
                Asset::ASSIGNMENT_LOCATION => $fromAssignmentType === Asset::ASSIGNMENT_LOCATION ? 'LOCATION_TRANSFERRED' : 'PLACED',
                default => $fromAssignmentType === Asset::ASSIGNMENT_USER ? 'TRANSFERRED' : 'ASSIGNED',
            };

            return $lockedAsset->assignmentHistories()->create([
                'number' => $this->numberingService->generate(
                    NumberingModuleCode::AssetAssignment,
                    [
                        'company' => $lockedAsset->company?->code,
                        'department' => $lockedAsset->department?->code,
                        'branch' => $lockedAsset->branch?->code,
                        'location' => $lockedAsset->location?->code,
                        'asset_type' => $lockedAsset->type?->code,
                        'category' => $lockedAsset->category?->code,
                        'product' => $lockedAsset->product?->code,
                        'document_type' => 'ASSET_ASSIGNMENT',
                        'event_type' => $eventType,
                    ],
                ),
                'event_type' => $eventType,
                'from_condition' => $fromCondition,
                'to_condition' => $condition,
                'from_custodian_id' => $fromCustodianId,
                'to_custodian_id' => $activeAssignee?->id,
                'from_snapshot' => $fromSnapshot,
                'to_snapshot' => $this->snapshot($lockedAsset),
                'performed_by' => $actor->id,
                'effective_at' => $effectiveAt,
                'remarks' => $remarks,
            ]);
        }, 3);
    }

    private function assertAssigneeCanReceive(Asset $asset, User $assignee): void
    {
        if (! $assignee->is_active || ($assignee->company_id !== null && $assignee->company_id !== $asset->company_id)) {
            throw ValidationException::withMessages([
                'assignee_id' => 'Penanggung jawab harus aktif dan berasal dari perusahaan aset.',
            ]);
        }

        if (! $assignee->hasGlobalDepartmentAccess()
            && $assignee->department_id !== $asset->department_id
            && ! $assignee->additionalDepartments()->whereKey($asset->department_id)->exists()) {
            throw ValidationException::withMessages([
                'assignee_id' => 'Penanggung jawab harus berasal dari department aset atau diberi akses ke department tersebut.',
            ]);
        }

        if (! app(BranchAccessService::class)->canAccess($assignee, $asset->branch_id)) {
            throw ValidationException::withMessages([
                'assignee_id' => 'Penanggung jawab harus memiliki akses ke branch aset.',
            ]);
        }
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

            $lockedAsset->load([
                'custodian',
                'company',
                'department',
                'location',
                'branch',
                'type',
                'category',
                'product',
            ]);
            $fromSnapshot = $this->snapshot($lockedAsset);
            $fromCustodianId = $lockedAsset->custodian_id;
            $fromCondition = $lockedAsset->condition;

            $lockedAsset->custodian_id = null;
            $lockedAsset->assignment_type = Asset::ASSIGNMENT_LOCATION;
            $lockedAsset->condition = $condition;
            $lockedAsset->save();
            $lockedAsset->setRelation('custodian', null);

            return $lockedAsset->assignmentHistories()->create([
                'number' => $this->numberingService->generate(
                    NumberingModuleCode::AssetAssignment,
                    [
                        'company' => $lockedAsset->company?->code,
                        'department' => $lockedAsset->department?->code,
                        'branch' => $lockedAsset->branch?->code,
                        'location' => $lockedAsset->location?->code,
                        'asset_type' => $lockedAsset->type?->code,
                        'category' => $lockedAsset->category?->code,
                        'product' => $lockedAsset->product?->code,
                        'document_type' => 'ASSET_ASSIGNMENT',
                        'event_type' => 'RETURNED',
                    ],
                ),
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
