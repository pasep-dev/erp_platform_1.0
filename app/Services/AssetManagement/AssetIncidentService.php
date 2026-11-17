<?php

namespace App\Services\AssetManagement;

use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\AssetManagement\AssetIncident;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AssetIncidentService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function report(array $data, User $actor, ?string $evidencePath): AssetIncident
    {
        return DB::transaction(function () use ($data, $actor, $evidencePath): AssetIncident {
            $asset = Asset::query()->whereKey($data['asset_id'])->lockForUpdate()->firstOrFail();

            if (! in_array($asset->status, [Asset::STATUS_ACTIVE, Asset::STATUS_MISSING], true)) {
                throw ValidationException::withMessages([
                    'asset_id' => 'Aset dengan status ini tidak dapat dilaporkan untuk insiden.',
                ]);
            }

            $incident = AssetIncident::query()->create([
                ...$data,
                'number' => 'INC-'.now()->format('Ymd').'-'.Str::upper(Str::substr((string) Str::ulid(), -8)),
                'status' => AssetIncident::STATUS_REPORTED,
                'condition_before' => $asset->condition,
                'asset_status_before' => $asset->status,
                'evidence_path' => $evidencePath,
                'reported_by' => $actor->id,
            ]);

            $incident->events()->create([
                'event_type' => 'REPORTED',
                'status_to' => AssetIncident::STATUS_REPORTED,
                'condition_from' => $asset->condition,
                'condition_to' => $asset->condition,
                'asset_status_from' => $asset->status,
                'asset_status_to' => $asset->status,
                'notes' => $data['description'],
                'performed_by' => $actor->id,
            ]);

            return $incident;
        }, 3);
    }

    public function startInvestigation(AssetIncident $incident, string $findings, User $actor): AssetIncident
    {
        return DB::transaction(function () use ($incident, $findings, $actor): AssetIncident {
            $lockedIncident = $this->lockIncident($incident);
            $this->ensureStatus($lockedIncident, AssetIncident::STATUS_REPORTED);
            $asset = Asset::query()->whereKey($lockedIncident->asset_id)->lockForUpdate()->firstOrFail();

            $lockedIncident->update([
                'status' => AssetIncident::STATUS_INVESTIGATING,
                'investigation_findings' => $findings,
            ]);
            $lockedIncident->events()->create([
                'event_type' => 'INVESTIGATION_STARTED',
                'status_from' => AssetIncident::STATUS_REPORTED,
                'status_to' => AssetIncident::STATUS_INVESTIGATING,
                'condition_from' => $asset->condition,
                'condition_to' => $asset->condition,
                'asset_status_from' => $asset->status,
                'asset_status_to' => $asset->status,
                'notes' => $findings,
                'performed_by' => $actor->id,
            ]);

            return $lockedIncident->refresh();
        }, 3);
    }

    /**
     * @param  array{resolution_type: string, resolution_notes: string, condition_after?: string|null}  $data
     */
    public function resolve(AssetIncident $incident, array $data, User $actor): AssetIncident
    {
        return DB::transaction(function () use ($incident, $data, $actor): AssetIncident {
            $lockedIncident = $this->lockIncident($incident);
            $this->ensureStatus($lockedIncident, AssetIncident::STATUS_INVESTIGATING);
            $asset = Asset::query()->whereKey($lockedIncident->asset_id)->lockForUpdate()->firstOrFail();
            $conditionBefore = $asset->condition;
            $assetStatusBefore = $asset->status;

            if ($lockedIncident->incident_type === AssetIncident::TYPE_DAMAGED) {
                $asset->condition = $data['condition_after'];
            } elseif (in_array($lockedIncident->incident_type, [AssetIncident::TYPE_LOST, AssetIncident::TYPE_STOLEN], true)) {
                if ($data['resolution_type'] === AssetIncident::RESOLUTION_MISSING_CONFIRMED) {
                    $asset->status = Asset::STATUS_MISSING;
                } elseif ($data['resolution_type'] === AssetIncident::RESOLUTION_RECOVERED) {
                    $asset->status = Asset::STATUS_ACTIVE;
                }
            }

            $asset->save();
            $lockedIncident->update([
                'status' => AssetIncident::STATUS_RESOLVED,
                'resolution_type' => $data['resolution_type'],
                'resolution_notes' => $data['resolution_notes'],
                'condition_after' => $asset->condition,
                'asset_status_after' => $asset->status,
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
            ]);
            $lockedIncident->events()->create([
                'event_type' => 'RESOLVED',
                'status_from' => AssetIncident::STATUS_INVESTIGATING,
                'status_to' => AssetIncident::STATUS_RESOLVED,
                'condition_from' => $conditionBefore,
                'condition_to' => $asset->condition,
                'asset_status_from' => $assetStatusBefore,
                'asset_status_to' => $asset->status,
                'notes' => $data['resolution_notes'],
                'performed_by' => $actor->id,
            ]);

            return $lockedIncident->refresh();
        }, 3);
    }

    public function dismiss(AssetIncident $incident, string $reason, User $actor): AssetIncident
    {
        return DB::transaction(function () use ($incident, $reason, $actor): AssetIncident {
            $lockedIncident = $this->lockIncident($incident);

            if (! in_array($lockedIncident->status, [AssetIncident::STATUS_REPORTED, AssetIncident::STATUS_INVESTIGATING], true)) {
                throw ValidationException::withMessages([
                    'incident' => 'Hanya laporan yang belum diselesaikan yang dapat ditutup sebagai tidak valid.',
                ]);
            }

            $previousStatus = $lockedIncident->status;
            $asset = Asset::query()->whereKey($lockedIncident->asset_id)->lockForUpdate()->firstOrFail();
            $lockedIncident->update([
                'status' => AssetIncident::STATUS_DISMISSED,
                'resolution_type' => AssetIncident::RESOLUTION_NO_ISSUE,
                'resolution_notes' => $reason,
                'condition_after' => $asset->condition,
                'asset_status_after' => $asset->status,
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
            ]);
            $lockedIncident->events()->create([
                'event_type' => 'DISMISSED',
                'status_from' => $previousStatus,
                'status_to' => AssetIncident::STATUS_DISMISSED,
                'condition_from' => $asset->condition,
                'condition_to' => $asset->condition,
                'asset_status_from' => $asset->status,
                'asset_status_to' => $asset->status,
                'notes' => $reason,
                'performed_by' => $actor->id,
            ]);

            return $lockedIncident->refresh();
        }, 3);
    }

    private function lockIncident(AssetIncident $incident): AssetIncident
    {
        return AssetIncident::query()->whereKey($incident->getKey())->lockForUpdate()->firstOrFail();
    }

    private function ensureStatus(AssetIncident $incident, string $status): void
    {
        if ($incident->status !== $status) {
            throw ValidationException::withMessages([
                'incident' => 'Status laporan sudah berubah. Muat ulang halaman dan periksa kembali.',
            ]);
        }
    }
}
