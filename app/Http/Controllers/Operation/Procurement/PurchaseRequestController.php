<?php

namespace App\Http\Controllers\Operation\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operation\Procurement\PurchaseRequestRequest;
use App\Models\Master\Organization\Branch;
use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\CostCenter;
use App\Models\Master\Organization\Department;
use App\Models\Master\Procurement\UnitOfMeasure;
use App\Models\Operation\Procurement\PurchaseRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));

        $purchaseRequests = PurchaseRequest::query()
            ->with([
                'requestedBy',
                'company',
                'branch',
                'department',
                'costCenter',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('number', 'ILIKE', "%{$search}%")
                        ->orWhere('purpose', 'ILIKE', "%{$search}%")
                        ->orWhereHas('company', function ($query) use ($search) {
                            $query->where('code', 'ILIKE', "%{$search}%")
                                ->orWhere('name', 'ILIKE', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('request_date')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render(
            'Operational/Procurement/PurchaseRequest/Index',
            [
                'purchaseRequests' => $purchaseRequests,
                'filters' => [
                    'search' => $search,
                ],
            ],
        );
    }

    public function create(): Response
    {
        return Inertia::render(
            'Operational/Procurement/PurchaseRequest/Create',
            $this->formOptions(),
        );
    }

    public function store(
        PurchaseRequestRequest $request,
    ): RedirectResponse {
        $purchaseRequest = DB::transaction(
            function () use ($request): PurchaseRequest {
                $validated = $request->validated();

                $items = $validated['items'];

                unset($validated['items']);

                $validated['number'] = $this->generateNumber();
                $validated['requested_by'] = $request->user()->id;
                $validated['document_status'] = 'DRAFT';
                $validated['approval_status'] = 'NOT_REQUIRED';

                $purchaseRequest = PurchaseRequest::create(
                    $validated,
                );

                foreach ($items as $index => $item) {
                    $quantity = (float) $item['quantity'];

                    $unitPrice = isset(
                        $item['estimated_unit_price']
                    )
                        ? (float) $item['estimated_unit_price']
                        : null;

                    $purchaseRequest->items()->create([
                        'line_number' => $index + 1,
                        'item_description' => $item['item_description'],
                        'management_class' => $item['management_class'],
                        'quantity' => $quantity,
                        'uom_id' => $item['uom_id'],
                        'estimated_unit_price' => $unitPrice,
                        'estimated_total' => $unitPrice !== null
                            ? $quantity * $unitPrice
                            : null,
                        'specification' => $item['specification'] ?? null,
                        'notes' => $item['notes'] ?? null,
                    ]);
                }

                return $purchaseRequest;
            },
        );

        return redirect()
            ->route(
                'operational.procurement.purchase-request.index',
            )
            ->with(
                'success',
                "Purchase Request {$purchaseRequest->number} berhasil dibuat.",
            );
    }

    public function edit(
        PurchaseRequest $purchaseRequest,
    ): Response {
        $purchaseRequest->load([
            'items',
            'company',
            'branch',
            'department',
            'costCenter',
        ]);

        return Inertia::render(
            'Operational/Procurement/PurchaseRequest/Edit',
            [
                'purchaseRequest' => $purchaseRequest,
                ...$this->formOptions(),
            ],
        );
    }

    public function update(
        PurchaseRequestRequest $request,
        PurchaseRequest $purchaseRequest,
    ): RedirectResponse {
        DB::transaction(
            function () use ($request, $purchaseRequest): void {
                $validated = $request->validated();

                $items = $validated['items'];

                unset($validated['items']);

                $purchaseRequest->update($validated);

                $purchaseRequest->items()->delete();

                foreach ($items as $index => $item) {
                    $quantity = (float) $item['quantity'];

                    $unitPrice = isset(
                        $item['estimated_unit_price']
                    )
                        ? (float) $item['estimated_unit_price']
                        : null;

                    $purchaseRequest->items()->create([
                        'line_number' => $index + 1,
                        'item_description' => $item['item_description'],
                        'management_class' => $item['management_class'],
                        'quantity' => $quantity,
                        'uom_id' => $item['uom_id'],
                        'estimated_unit_price' => $unitPrice,
                        'estimated_total' => $unitPrice !== null
                            ? $quantity * $unitPrice
                            : null,
                        'specification' => $item['specification'] ?? null,
                        'notes' => $item['notes'] ?? null,
                    ]);
                }
            },
        );

        return redirect()
            ->route(
                'operational.procurement.purchase-request.index',
            )
            ->with(
                'success',
                "Purchase Request {$purchaseRequest->number} berhasil diperbarui.",
            );
    }

    public function destroy(
        PurchaseRequest $purchaseRequest,
    ): RedirectResponse {
        $purchaseRequest->delete();

        return redirect()
            ->route(
                'operational.procurement.purchase-request.index',
            )
            ->with(
                'success',
                "Purchase Request {$purchaseRequest->number} berhasil dihapus.",
            );
    }

    private function formOptions(): array
    {
        return [
            'companies' => Company::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get([
                    'id',
                    'code',
                    'name',
                ]),

            'branches' => Branch::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get([
                    'id',
                    'code',
                    'name',
                    'company_id',
                ]),

            'departments' => Department::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get([
                    'id',
                    'code',
                    'name',
                ]),

            'costCenters' => CostCenter::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get([
                    'id',
                    'code',
                    'name',
                ]),

            'unitsOfMeasure' => UnitOfMeasure::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get([
                    'id',
                    'code',
                    'name',
                ]),
        ];
    }

    private function generateNumber(): string
    {
        $prefix = 'PR-' . now()->format('Y') . '-';

        $lastNumber = PurchaseRequest::query()
            ->where('number', 'LIKE', $prefix . '%')
            ->orderByDesc('number')
            ->value('number');

        $nextSequence = $lastNumber !== null
            ? ((int) substr($lastNumber, -6)) + 1
            : 1;

        return $prefix . str_pad(
            (string) $nextSequence,
            6,
            '0',
            STR_PAD_LEFT,
        );
    }
}
