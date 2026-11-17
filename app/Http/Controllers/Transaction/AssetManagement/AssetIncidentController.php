<?php

namespace App\Http\Controllers\Transaction\AssetManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\AssetManagement\DismissAssetIncidentRequest;
use App\Http\Requests\Transaction\AssetManagement\InvestigateAssetIncidentRequest;
use App\Http\Requests\Transaction\AssetManagement\ResolveAssetIncidentRequest;
use App\Http\Requests\Transaction\AssetManagement\StoreAssetIncidentRequest;
use App\Models\Transaction\AssetManagement\Asset;
use App\Models\Transaction\AssetManagement\AssetIncident;
use App\Services\AssetManagement\AssetIncidentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AssetIncidentController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('asset.view'), 403);

        $search = trim($request->string('search')->toString());
        $status = $request->string('status', 'ALL')->toString();
        $requestedPerPage = $request->integer('per_page', 15);
        $perPage = in_array($requestedPerPage, [5, 15, 25, 50, 100], true) ? $requestedPerPage : 15;

        $incidents = AssetIncident::query()
            ->with(['asset:id,number,product_id', 'asset.product:id,code,name', 'reportedBy:id,name'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('number', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%")
                        ->orWhereHas('asset', fn (Builder $asset) => $asset->where('number', 'ilike', "%{$search}%")
                            ->orWhereHas('product', fn (Builder $product) => $product->where('name', 'ilike', "%{$search}%")
                                ->orWhere('code', 'ilike', "%{$search}%")));
                });
            })
            ->when(in_array($status, AssetIncident::STATUSES, true), fn (Builder $query) => $query->where('status', $status))
            ->orderByRaw("CASE status WHEN 'REPORTED' THEN 1 WHEN 'INVESTIGATING' THEN 2 WHEN 'RESOLVED' THEN 3 ELSE 4 END")
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Transaction/AssetManagement/Incident/Index', [
            'incidents' => $incidents,
            'filters' => [
                'search' => $search,
                'status' => in_array($status, AssetIncident::STATUSES, true) ? $status : 'ALL',
            ],
            'counts' => [
                'reported' => AssetIncident::query()->where('status', AssetIncident::STATUS_REPORTED)->count(),
                'investigating' => AssetIncident::query()->where('status', AssetIncident::STATUS_INVESTIGATING)->count(),
                'resolved' => AssetIncident::query()->where('status', AssetIncident::STATUS_RESOLVED)->count(),
            ],
            'can_create' => $request->user()?->can('asset.create') === true,
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->can('asset.create'), 403);

        return Inertia::render('Transaction/AssetManagement/Incident/Create', [
            'assets' => Asset::query()
                ->whereIn('status', [Asset::STATUS_ACTIVE, Asset::STATUS_MISSING])
                ->with('product:id,code,name')
                ->orderBy('number')
                ->get(['id', 'number', 'serial_number', 'condition', 'status', 'product_id']),
            'incident_types' => [
                AssetIncident::TYPE_DAMAGED => 'Rusak',
                AssetIncident::TYPE_LOST => 'Hilang',
                AssetIncident::TYPE_STOLEN => 'Dicuri',
                AssetIncident::TYPE_OTHER => 'Lainnya',
            ],
            'severity_options' => ['LOW' => 'Rendah', 'NORMAL' => 'Normal', 'HIGH' => 'Tinggi', 'URGENT' => 'Urgent'],
            'condition_options' => Asset::CONDITION_OPTIONS,
        ]);
    }

    public function store(StoreAssetIncidentRequest $request, AssetIncidentService $service): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $evidencePath = $request->file('evidence')?->store('asset-incidents/evidence', 'local');
        if ($request->hasFile('evidence') && $evidencePath === false) {
            return back()->with('error', 'Bukti insiden gagal disimpan. Silakan coba kembali.');
        }

        try {
            $incident = $service->report($request->safe()->except('evidence'), $user, $evidencePath);
        } catch (Throwable $exception) {
            if ($evidencePath !== null) {
                Storage::disk('local')->delete($evidencePath);
            }

            throw $exception;
        }

        return to_route('transaction.asset-management.incidents.show', $incident)
            ->with('success', 'Laporan insiden berhasil dicatat. Status aset belum diubah sampai hasil investigasi dikonfirmasi.');
    }

    public function show(Request $request, AssetIncident $incident): Response
    {
        abort_unless($request->user()?->can('asset.view'), 403);

        $incident->load([
            'asset:id,number,serial_number,status,condition,product_id,company_id,department_id,location_id,custodian_id',
            'asset.product:id,code,name',
            'asset.company:id,code,name',
            'asset.department:id,code,name',
            'asset.location:id,code,name',
            'asset.custodian:id,name,email',
            'reportedBy:id,name',
            'resolvedBy:id,name',
            'events' => fn (HasMany $query) => $query->with('performedBy:id,name')->orderBy('created_at'),
        ]);
        $relatedIncidents = $incident->asset->incidents()
            ->with('reportedBy:id,name')
            ->where('id', '!=', $incident->getKey())
            ->orderByDesc('occurred_at')
            ->limit(10)
            ->get();

        return Inertia::render('Transaction/AssetManagement/Incident/Show', [
            'incident' => $incident,
            'related_incidents' => $relatedIncidents,
            'can_create' => $request->user()?->can('asset.create') === true,
            'can_investigate' => $request->user()?->can('asset.update') === true && $incident->status === AssetIncident::STATUS_REPORTED,
            'can_resolve' => $request->user()?->can('asset.update') === true && $incident->status === AssetIncident::STATUS_INVESTIGATING,
            'can_dismiss' => $request->user()?->can('asset.update') === true && in_array($incident->status, [AssetIncident::STATUS_REPORTED, AssetIncident::STATUS_INVESTIGATING], true),
            'condition_options' => Asset::CONDITION_OPTIONS,
            'resolution_options' => match ($incident->incident_type) {
                AssetIncident::TYPE_DAMAGED => [AssetIncident::RESOLUTION_REPAIRED => 'Sudah diperbaiki', AssetIncident::RESOLUTION_MONITOR => 'Dipantau', AssetIncident::RESOLUTION_NO_ISSUE => 'Tidak ditemukan masalah'],
                AssetIncident::TYPE_LOST, AssetIncident::TYPE_STOLEN => [AssetIncident::RESOLUTION_MISSING_CONFIRMED => 'Dikonfirmasi hilang', AssetIncident::RESOLUTION_RECOVERED => 'Aset ditemukan kembali', AssetIncident::RESOLUTION_NO_ISSUE => 'Tidak ditemukan masalah'],
                default => [AssetIncident::RESOLUTION_MONITOR => 'Dipantau', AssetIncident::RESOLUTION_NO_ISSUE => 'Tidak ditemukan masalah'],
            },
        ]);
    }

    public function investigate(InvestigateAssetIncidentRequest $request, AssetIncident $incident, AssetIncidentService $service): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);
        $service->startInvestigation($incident, $request->validated('investigation_findings'), $user);

        return back()->with('success', 'Investigasi dimulai dan catatannya tersimpan pada riwayat insiden.');
    }

    public function resolve(ResolveAssetIncidentRequest $request, AssetIncident $incident, AssetIncidentService $service): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);
        $service->resolve($incident, $request->validated(), $user);

        return back()->with('success', 'Insiden selesai. Perubahan status atau kondisi aset telah dicatat pada riwayat.');
    }

    public function dismiss(DismissAssetIncidentRequest $request, AssetIncident $incident, AssetIncidentService $service): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);
        $service->dismiss($incident, $request->validated('dismissal_reason'), $user);

        return back()->with('success', 'Laporan ditutup sebagai tidak valid tanpa mengubah status atau kondisi aset.');
    }

    public function downloadEvidence(Request $request, AssetIncident $incident): StreamedResponse
    {
        abort_unless($request->user()?->can('asset.view'), 403);
        abort_if($incident->evidence_path === null || ! Storage::disk('local')->exists($incident->evidence_path), 404);

        return Storage::disk('local')->download($incident->evidence_path);
    }
}
