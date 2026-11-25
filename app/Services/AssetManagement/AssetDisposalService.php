<?php

namespace App\Services\AssetManagement;

use App\Enums\NumberingModuleCode;
use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\AssetManagement\AssetDisposal;
use App\Models\Transaction\AssetManagement\AssetIncident;
use App\Models\Transaction\AssetManagement\AssetMaintenanceRecord;
use App\Models\User;
use App\Services\NumberingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetDisposalService
{
    public function __construct(private readonly NumberingService $numberingService) {}

    /**
     * @param  array{asset_id: string, disposal_type: string, disposal_date: string, amount?: string|float|int|null, reason: string}  $data
     */
    public function requestDisposal(array $data, User $actor, ?string $evidencePath): AssetDisposal
    {
        return DB::transaction(function () use ($data, $actor, $evidencePath): AssetDisposal {
            $asset = Asset::query()->whereKey($data['asset_id'])->lockForUpdate()->firstOrFail();

            if ($asset->status !== Asset::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'asset_id' => 'Hanya aset aktif yang dapat diajukan untuk pelepasan.',
                ]);
            }

            if ($asset->custodian_id !== null) {
                throw ValidationException::withMessages([
                    'asset_id' => 'Kembalikan aset dari penanggung jawab sebelum mengajukan pelepasan.',
                ]);
            }

            if ($asset->maintenanceRecords()->whereIn('status', [
                AssetMaintenanceRecord::STATUS_REQUESTED,
                AssetMaintenanceRecord::STATUS_WAITING_APPROVAL,
                AssetMaintenanceRecord::STATUS_WAITING_PARTS,
                AssetMaintenanceRecord::STATUS_READY_TO_START,
                AssetMaintenanceRecord::STATUS_IN_PROGRESS,
            ])->exists()) {
                throw ValidationException::withMessages([
                    'asset_id' => 'Selesaikan atau batalkan maintenance yang masih aktif sebelum mengajukan pelepasan.',
                ]);
            }

            if ($asset->incidents()->whereIn('status', [
                AssetIncident::STATUS_REPORTED,
                AssetIncident::STATUS_INVESTIGATING,
            ])->exists()) {
                throw ValidationException::withMessages([
                    'asset_id' => 'Selesaikan investigasi insiden yang masih terbuka sebelum mengajukan pelepasan.',
                ]);
            }

            $asset->load([
                'product:id,code,name',
                'company:id,code,name',
                'department:id,code,name',
                'branch:id,code,name',
                'location:id,code,name',
                'type:id,code',
                'category:id,code',
                'product:id,code',
                'custodian:id,name,email',
            ]);

            $snapshot = [
                'number' => $asset->number,
                'product_code' => $asset->product?->code,
                'product_name' => $asset->product?->name,
                'serial_number' => $asset->serial_number,
                'condition' => $asset->condition,
                'status' => $asset->status,
                'company' => $asset->company?->code.' · '.$asset->company?->name,
                'department' => $asset->department?->code.' · '.$asset->department?->name,
                'branch' => $asset->branch?->code.' · '.$asset->branch?->name,
                'location' => $asset->location?->code.' · '.$asset->location?->name,
                'custodian' => $asset->custodian?->name,
                'acquisition_date' => $asset->acquisition_date?->toDateString(),
                'acquisition_cost' => $asset->acquisition_cost,
            ];

            $disposal = $asset->disposals()->create([
                ...$data,
                'number' => $this->numberingService->generate(
                    NumberingModuleCode::AssetDisposal,
                    [
                        'company' => $asset->company?->code,
                        'department' => $asset->department?->code,
                        'branch' => $asset->branch?->code,
                        'location' => $asset->location?->code,
                        'asset_type' => $asset->type?->code,
                        'category' => $asset->category?->code,
                        'product' => $asset->product?->code,
                        'document_type' => 'ASSET_DISPOSAL',
                        'event_type' => $data['disposal_type'],
                    ],
                ),
                'status' => AssetDisposal::STATUS_PENDING_APPROVAL,
                'asset_snapshot' => $snapshot,
                'evidence_path' => $evidencePath,
                'requested_by' => $actor->id,
            ]);

            $asset->update(['status' => Asset::STATUS_DISPOSAL_PENDING]);

            return $disposal;
        }, 3);
    }

    public function approve(AssetDisposal $disposal, User $actor, ?string $notes): AssetDisposal
    {
        return $this->decide($disposal, $actor, AssetDisposal::STATUS_APPROVED, $notes);
    }

    public function reject(AssetDisposal $disposal, User $actor, string $notes): AssetDisposal
    {
        return $this->decide($disposal, $actor, AssetDisposal::STATUS_REJECTED, $notes);
    }

    private function decide(AssetDisposal $disposal, User $actor, string $status, ?string $notes): AssetDisposal
    {
        return DB::transaction(function () use ($disposal, $actor, $status, $notes): AssetDisposal {
            $lockedDisposal = AssetDisposal::query()->whereKey($disposal->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedDisposal->status !== AssetDisposal::STATUS_PENDING_APPROVAL) {
                throw ValidationException::withMessages([
                    'disposal' => 'Pengajuan ini sudah diproses. Muat ulang halaman untuk melihat status terbaru.',
                ]);
            }

            $asset = Asset::query()->whereKey($lockedDisposal->asset_id)->lockForUpdate()->firstOrFail();

            if ($asset->status !== Asset::STATUS_DISPOSAL_PENDING) {
                throw ValidationException::withMessages([
                    'disposal' => 'Status aset tidak sesuai dengan pengajuan pelepasan ini.',
                ]);
            }

            $asset->update([
                'status' => $status === AssetDisposal::STATUS_APPROVED
                    ? Asset::STATUS_DISPOSED
                    : Asset::STATUS_ACTIVE,
            ]);
            $lockedDisposal->update([
                'status' => $status,
                'decision_notes' => $notes,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ]);

            return $lockedDisposal->refresh();
        }, 3);
    }
}
