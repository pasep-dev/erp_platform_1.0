<?php

namespace App\Services\AssetManagement;

use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\AssetManagement\AssetAuditItem;
use App\Models\Transaction\AssetManagement\AssetAuditSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AssetAuditService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createSession(array $data, User $actor): AssetAuditSession
    {
        return DB::transaction(function () use ($data, $actor): AssetAuditSession {
            $assetQuery = $this->scopedAssets($data);
            $assetCount = (clone $assetQuery)->count();

            if ($assetCount === 0) {
                throw ValidationException::withMessages([
                    'scope' => 'Tidak ada aset aktif yang sesuai dengan cakupan audit ini.',
                ]);
            }

            $scopeSnapshot = [
                'company' => $this->optionName('mst_org_companies', $data['company_id'] ?? null),
                'department' => $this->optionName('mst_org_departments', $data['department_id'] ?? null),
                'location' => $this->optionName('mst_org_locations', $data['location_id'] ?? null),
                'category' => $this->optionName('mst_asset_categories', $data['category_id'] ?? null),
            ];

            $session = AssetAuditSession::query()->create([
                'number' => 'AUD-'.now()->format('Ymd').'-'.Str::upper(Str::substr((string) Str::ulid(), -8)),
                'company_id' => $data['company_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'location_id' => $data['location_id'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'scope_snapshot' => $scopeSnapshot,
                'status' => AssetAuditSession::STATUS_IN_PROGRESS,
                'started_at' => now(),
                'remarks' => $data['remarks'] ?? null,
                'created_by' => $actor->id,
            ]);

            $assetQuery
                ->with([
                    'product:id,code,name',
                    'company:id,code,name',
                    'department:id,code,name',
                    'location:id,code,name',
                    'branch:id,code,name',
                    'custodian:id,name,email',
                ])
                ->chunkById(300, function (Collection $assets) use ($session): void {
                    $items = $assets->map(fn (Asset $asset): array => [
                        'asset_id' => $asset->id,
                        'expected_snapshot' => [
                            'number' => $asset->number,
                            'serial_number' => $asset->serial_number,
                            'product' => $asset->product?->name,
                            'product_code' => $asset->product?->code,
                            'company' => $asset->company?->name,
                            'department' => $asset->department?->name,
                            'location_id' => $asset->location_id,
                            'location' => $asset->location?->name,
                            'branch' => $asset->branch?->name,
                            'custodian' => $asset->custodian?->name,
                            'condition' => $asset->condition,
                        ],
                        'result' => AssetAuditItem::RESULT_PENDING,
                    ])->all();

                    $session->items()->createMany($items);
                }, 'assets.id', 'id');

            return $session;
        }, 3);
    }

    public function recordScan(AssetAuditSession $session, string $assetNumber, User $actor): AssetAuditItem
    {
        $this->ensureOpen($session);

        $item = $session->items()
            ->whereHas('asset', fn (Builder $query) => $query->where('number', $assetNumber))
            ->first();

        if ($item === null) {
            throw ValidationException::withMessages([
                'asset_number' => 'QR aset ini tidak termasuk dalam cakupan audit.',
            ]);
        }

        if ($item->result !== AssetAuditItem::RESULT_PENDING) {
            throw ValidationException::withMessages([
                'asset_number' => 'Aset ini sudah tercatat dalam sesi audit ini.',
            ]);
        }

        $item->update([
            'result' => AssetAuditItem::RESULT_FOUND,
            'observed_location_id' => $session->location_id,
            'observed_condition' => $item->expected_snapshot['condition'] ?? null,
            'checked_by' => $actor->id,
            'checked_at' => now(),
        ]);

        return $item->refresh()->load('asset:id,number');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateFinding(AssetAuditSession $session, AssetAuditItem $item, array $data, User $actor): AssetAuditItem
    {
        $this->ensureOpen($session);
        $this->ensureBelongsToSession($session, $item);

        if ($item->resolved_at !== null) {
            throw ValidationException::withMessages([
                'item' => 'Hasil yang sudah ditinjau tidak dapat diubah.',
            ]);
        }

        $item->update([
            ...$data,
            'observed_location_id' => $data['result'] === AssetAuditItem::RESULT_MOVED
                ? $data['observed_location_id']
                : null,
            'observed_condition' => $data['result'] === AssetAuditItem::RESULT_DAMAGED
                ? $data['observed_condition']
                : null,
            'checked_by' => $actor->id,
            'checked_at' => now(),
            'resolution_remarks' => null,
            'resolved_by' => null,
            'resolved_at' => null,
        ]);

        return $item->refresh();
    }

    public function resolveFinding(AssetAuditSession $session, AssetAuditItem $item, string $remarks, User $actor): AssetAuditItem
    {
        return DB::transaction(function () use ($session, $item, $remarks, $actor): AssetAuditItem {
            $lockedSession = AssetAuditSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            $this->ensureOpen($lockedSession);
            $lockedItem = $lockedSession->items()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedItem->resolved_at !== null || in_array($lockedItem->result, [AssetAuditItem::RESULT_FOUND, AssetAuditItem::RESULT_PENDING], true)) {
                throw ValidationException::withMessages([
                    'item' => 'Resolusi hanya dapat dicatat satu kali untuk selisih audit yang belum ditinjau.',
                ]);
            }

            $asset = Asset::query()->whereKey($lockedItem->asset_id)->lockForUpdate()->firstOrFail();

            if ($lockedItem->result === AssetAuditItem::RESULT_MOVED) {
                $asset->location_id = $lockedItem->observed_location_id;
            } elseif ($lockedItem->result === AssetAuditItem::RESULT_DAMAGED) {
                $asset->condition = $lockedItem->observed_condition;
            } elseif ($lockedItem->result === AssetAuditItem::RESULT_MISSING) {
                $asset->status = Asset::STATUS_MISSING;
            }

            $asset->save();
            $lockedItem->update([
                'resolution_remarks' => $remarks,
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
            ]);

            return $lockedItem->refresh();
        }, 3);
    }

    public function complete(AssetAuditSession $session, User $actor): AssetAuditSession
    {
        return DB::transaction(function () use ($session, $actor): AssetAuditSession {
            $lockedSession = AssetAuditSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            $this->ensureOpen($lockedSession);

            if ($lockedSession->items()->where('result', AssetAuditItem::RESULT_PENDING)->exists()) {
                throw ValidationException::withMessages([
                    'session' => 'Periksa semua aset atau tandai sebagai tidak ditemukan sebelum menyelesaikan audit.',
                ]);
            }

            if ($lockedSession->items()
                ->whereNotIn('result', [AssetAuditItem::RESULT_FOUND, AssetAuditItem::RESULT_PENDING])
                ->whereNull('resolved_at')
                ->exists()) {
                throw ValidationException::withMessages([
                    'session' => 'Tinjau dan catat resolusi untuk semua selisih sebelum menyelesaikan audit.',
                ]);
            }

            $lockedSession->update([
                'status' => AssetAuditSession::STATUS_COMPLETED,
                'completed_at' => now(),
                'completed_by' => $actor->id,
            ]);

            return $lockedSession->refresh();
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return Builder<Asset>
     */
    private function scopedAssets(array $scope): Builder
    {
        return Asset::query()
            ->where('status', Asset::STATUS_ACTIVE)
            ->when($scope['company_id'] ?? null, fn (Builder $query, string $id) => $query->where('company_id', $id))
            ->when($scope['department_id'] ?? null, fn (Builder $query, string $id) => $query->where('department_id', $id))
            ->when($scope['location_id'] ?? null, fn (Builder $query, string $id) => $query->where('location_id', $id))
            ->when($scope['category_id'] ?? null, fn (Builder $query, string $id) => $query->where('category_id', $id));
    }

    private function optionName(string $table, ?string $id): ?string
    {
        return $id === null ? null : DB::table($table)->where('id', $id)->value('name');
    }

    private function ensureOpen(AssetAuditSession $session): void
    {
        if ($session->status !== AssetAuditSession::STATUS_IN_PROGRESS) {
            throw ValidationException::withMessages([
                'session' => 'Sesi audit yang sudah selesai tidak dapat diubah.',
            ]);
        }
    }

    private function ensureBelongsToSession(AssetAuditSession $session, AssetAuditItem $item): void
    {
        if ($item->asset_audit_session_id !== $session->id) {
            abort(404);
        }
    }
}
