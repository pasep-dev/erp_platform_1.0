<?php

namespace App\Services\AssetManagement;

use App\Enums\NumberingModuleCode;
use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\AssetManagement\AssetMaintenanceRecord;
use App\Models\User;
use App\Services\NumberingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetMaintenanceService
{
    public function __construct(private readonly NumberingService $numberingService) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): AssetMaintenanceRecord
    {
        return DB::transaction(function () use ($data, $actor): AssetMaintenanceRecord {
            $asset = Asset::query()->whereKey($data['asset_id'])->lockForUpdate()->firstOrFail();
            $asset->load(['company:id,code', 'department:id,code', 'branch:id,code', 'location:id,code', 'type:id,code', 'category:id,code', 'product:id,code']);
            $components = $data['components'] ?? [];
            unset($data['components']);

            if (($data['execution_mode'] ?? null) === AssetMaintenanceRecord::EXECUTION_INTERNAL) {
                $data['estimated_cost'] = $components === []
                    ? ($data['estimated_cost'] ?? 0)
                    : $this->componentEstimate($components);
                $data['vendor_id'] = null;
            } else {
                $components = [];
            }

            if ($asset->status !== Asset::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'asset_id' => 'Hanya aset aktif yang dapat dibuatkan permintaan maintenance.',
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
                    'asset_id' => 'Aset ini masih memiliki request maintenance aktif.',
                ]);
            }

            $asset->condition = $data['reported_condition'];
            $asset->save();

            $maintenance = AssetMaintenanceRecord::query()->create([
                ...$data,
                'number' => $this->numberingService->generate(
                    NumberingModuleCode::AssetMaintenance,
                    [
                        'company' => $asset->company?->code,
                        'department' => $asset->department?->code,
                        'branch' => $asset->branch?->code,
                        'location' => $asset->location?->code,
                        'asset_type' => $asset->type?->code,
                        'category' => $asset->category?->code,
                        'product' => $asset->product?->code,
                        'document_type' => 'ASSET_MAINTENANCE',
                    ],
                ),
                'status' => AssetMaintenanceRecord::STATUS_REQUESTED,
                'requested_by' => $actor->id,
            ]);

            $this->syncComponents($maintenance, $components);

            return $maintenance;
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateRequest(AssetMaintenanceRecord $maintenance, array $data): AssetMaintenanceRecord
    {
        return DB::transaction(function () use ($maintenance, $data): AssetMaintenanceRecord {
            $lockedMaintenance = AssetMaintenanceRecord::query()
                ->whereKey($maintenance->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedMaintenance->status !== AssetMaintenanceRecord::STATUS_REQUESTED) {
                throw ValidationException::withMessages([
                    'maintenance' => 'Permintaan hanya dapat diubah sebelum diajukan.',
                ]);
            }

            $components = $data['components'] ?? [];
            unset($data['components']);

            if (($data['execution_mode'] ?? null) === AssetMaintenanceRecord::EXECUTION_INTERNAL) {
                $data['estimated_cost'] = $components === []
                    ? ($data['estimated_cost'] ?? 0)
                    : $this->componentEstimate($components);
                $data['vendor_id'] = null;
            } else {
                $components = [];
            }

            $lockedMaintenance->fill($data)->save();
            $this->syncComponents($lockedMaintenance, $components);
            $asset = Asset::query()->whereKey($lockedMaintenance->asset_id)->lockForUpdate()->firstOrFail();
            $asset->condition = $data['reported_condition'];
            $asset->save();

            return $lockedMaintenance;
        }, 3);
    }

    public function submitForApproval(AssetMaintenanceRecord $maintenance): AssetMaintenanceRecord
    {
        return DB::transaction(function () use ($maintenance): AssetMaintenanceRecord {
            $lockedMaintenance = AssetMaintenanceRecord::query()
                ->whereKey($maintenance->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedMaintenance->status !== AssetMaintenanceRecord::STATUS_REQUESTED) {
                throw ValidationException::withMessages([
                    'maintenance' => 'Hanya request pemeriksaan yang dapat diajukan.',
                ]);
            }

            if ($lockedMaintenance->estimated_cost === null) {
                throw ValidationException::withMessages([
                    'estimated_cost' => 'Estimasi biaya wajib dicatat sebelum pengajuan.',
                ]);
            }

            if (blank($lockedMaintenance->inspection_result)) {
                throw ValidationException::withMessages([
                    'inspection_result' => 'Tanggapan dan rencana perbaikan wajib dicatat sebelum pengajuan.',
                ]);
            }

            $lockedMaintenance->update([
                'status' => AssetMaintenanceRecord::STATUS_WAITING_APPROVAL,
                'submitted_at' => now(),
            ]);

            return $lockedMaintenance->refresh();
        }, 3);
    }

    public function markApproved(
        AssetMaintenanceRecord $maintenance,
        User $actor,
        ?string $documentPath,
        ?string $remarks,
    ): AssetMaintenanceRecord {
        return DB::transaction(function () use ($maintenance, $actor, $documentPath, $remarks): AssetMaintenanceRecord {
            $lockedMaintenance = AssetMaintenanceRecord::query()
                ->whereKey($maintenance->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedMaintenance->status !== AssetMaintenanceRecord::STATUS_WAITING_APPROVAL) {
                throw ValidationException::withMessages([
                    'maintenance' => 'Hanya pengajuan yang menunggu keputusan yang dapat disetujui.',
                ]);
            }

            $lockedMaintenance->update([
                'status' => AssetMaintenanceRecord::STATUS_IN_PROGRESS,
                'started_at' => today()->toDateString(),
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'approval_document_path' => $documentPath,
                'approval_remarks' => $remarks,
            ]);

            return $lockedMaintenance->refresh();
        }, 3);
    }

    public function markRejected(AssetMaintenanceRecord $maintenance, User $actor, string $reason): AssetMaintenanceRecord
    {
        return DB::transaction(function () use ($maintenance, $actor, $reason): AssetMaintenanceRecord {
            $lockedMaintenance = AssetMaintenanceRecord::query()
                ->whereKey($maintenance->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedMaintenance->status !== AssetMaintenanceRecord::STATUS_WAITING_APPROVAL) {
                throw ValidationException::withMessages([
                    'maintenance' => 'Hanya pengajuan yang menunggu keputusan yang dapat ditolak.',
                ]);
            }

            $lockedMaintenance->update([
                'status' => AssetMaintenanceRecord::STATUS_CANCELLED,
                'cancellation_reason' => $reason,
                'rejected_by' => $actor->id,
                'rejected_at' => now(),
            ]);

            return $lockedMaintenance->refresh();
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function complete(AssetMaintenanceRecord $maintenance, array $data, User $actor): AssetMaintenanceRecord
    {
        return DB::transaction(function () use ($maintenance, $data, $actor): AssetMaintenanceRecord {
            $lockedMaintenance = AssetMaintenanceRecord::query()
                ->whereKey($maintenance->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedMaintenance->status, [
                AssetMaintenanceRecord::STATUS_IN_PROGRESS,
                AssetMaintenanceRecord::STATUS_WAITING_PARTS,
                AssetMaintenanceRecord::STATUS_READY_TO_START,
            ], true)) {
                throw ValidationException::withMessages([
                    'maintenance' => 'Hanya maintenance yang sedang ditangani yang dapat diselesaikan.',
                ]);
            }

            if (CarbonImmutable::parse($data['completed_at'])->lt(CarbonImmutable::parse($lockedMaintenance->started_at ?? $lockedMaintenance->submitted_at ?? $lockedMaintenance->requested_at))) {
                throw ValidationException::withMessages([
                    'completed_at' => 'Tanggal selesai tidak boleh sebelum pengerjaan dimulai.',
                ]);
            }

            $lockedMaintenance->update([
                ...$data,
                'status' => AssetMaintenanceRecord::STATUS_COMPLETED,
                'completed_by' => $actor->id,
            ]);

            $asset = Asset::query()->whereKey($lockedMaintenance->asset_id)->lockForUpdate()->firstOrFail();
            $asset->condition = $data['completed_condition'];
            $asset->save();

            return $lockedMaintenance->refresh();
        }, 3);
    }

    public function cancel(AssetMaintenanceRecord $maintenance, string $reason, User $actor): AssetMaintenanceRecord
    {
        return DB::transaction(function () use ($maintenance, $reason, $actor): AssetMaintenanceRecord {
            $lockedMaintenance = AssetMaintenanceRecord::query()
                ->whereKey($maintenance->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedMaintenance->status !== AssetMaintenanceRecord::STATUS_REQUESTED) {
                throw ValidationException::withMessages([
                    'maintenance' => 'Hanya permintaan yang belum diajukan yang dapat dibatalkan.',
                ]);
            }

            $lockedMaintenance->update([
                'status' => AssetMaintenanceRecord::STATUS_CANCELLED,
                'cancellation_reason' => $reason,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
            ]);

            return $lockedMaintenance->refresh();
        }, 3);
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     */
    private function componentEstimate(array $components): float
    {
        $total = 0.0;

        foreach ($components as $component) {
            $total += (float) $component['quantity'] * (float) $component['estimated_unit_cost'];
        }

        return round($total, 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     */
    private function syncComponents(AssetMaintenanceRecord $maintenance, array $components): void
    {
        $maintenance->components()->get()->each->delete();

        if ($components !== []) {
            $maintenance->components()->createMany($components);
        }
    }
}
