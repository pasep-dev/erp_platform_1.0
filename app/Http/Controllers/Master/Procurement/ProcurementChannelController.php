<?php

namespace App\Http\Controllers\Master\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Procurement\ProcurementChannelRequest;
use App\Models\Master\Procurement\ProcurementChannel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementChannelController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));

        $procurementChannels = ProcurementChannel::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('code', 'ILIKE', "%{$search}%")
                        ->orWhere('name', 'ILIKE', "%{$search}%")
                        ->orWhere('description', 'ILIKE', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render(
            'Master/Procurement/ProcurementChannels/Index',
            [
                'procurementChannels' => $procurementChannels,
                'filters' => [
                    'search' => $search,
                ],
            ]
        );
    }

    public function create(): Response
    {
        return Inertia::render(
            'Master/Procurement/ProcurementChannels/Create'
        );
    }

    public function store(
        ProcurementChannelRequest $request
    ): RedirectResponse {
        ProcurementChannel::create($request->validated());

        return redirect()
            ->route('master.procurement.procurement-channels.index')
            ->with(
                'success',
                'Procurement channel berhasil ditambahkan.'
            );
    }

    public function edit(
        ProcurementChannel $procurementChannel
    ): Response {
        return Inertia::render(
            'Master/Procurement/ProcurementChannels/Edit',
            [
                'procurementChannel' => $procurementChannel,
            ]
        );
    }

    public function update(
        ProcurementChannelRequest $request,
        ProcurementChannel $procurementChannel
    ): RedirectResponse {
        $procurementChannel->update($request->validated());

        return redirect()
            ->route('master.procurement.procurement-channels.index')
            ->with(
                'success',
                'Procurement channel berhasil diperbarui.'
            );
    }

    public function destroy(
        ProcurementChannel $procurementChannel
    ): RedirectResponse {
        $procurementChannel->delete();

        return redirect()
            ->route('master.procurement.procurement-channels.index')
            ->with(
                'success',
                'Procurement channel berhasil dihapus.'
            );
    }
}
