<?php

namespace App\Http\Controllers\Transaction\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\Procurement\CancelPurchaseOrderRequest;
use App\Http\Requests\Transaction\Procurement\UpdatePurchaseOrderPriceRequest;
use App\Models\Transaction\Procurement\PurchaseOrder;
use App\Services\Transaction\Procurement\PurchaseOrderManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderManagementService $purchaseOrderManagementService,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('purchase-order.view'), 403);

        $requestedPerPage = $request->integer('per_page', 15);
        $perPage = in_array($requestedPerPage, [5, 15, 25, 50, 100], true) ? $requestedPerPage : 15;

        $purchaseOrders = PurchaseOrder::query()
            ->with(['purchaseRequest:id,number,purpose', 'vendor:id,code,name', 'department:id,name'])
            ->withCount('items')
            ->withCount([
                'items as missing_prices_count' => fn ($query) => $query->whereNull('unit_price'),
            ])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->trim()->toString();
                $query->where(function ($query) use ($search): void {
                    $query->where('number', 'ilike', "%{$search}%")
                        ->orWhereHas('purchaseRequest', fn ($prQuery) => $prQuery->where('number', 'ilike', "%{$search}%"))
                        ->orWhereHas('vendor', fn ($vendorQuery) => $vendorQuery->where('name', 'ilike', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $purchaseOrders->getCollection()->transform(function (PurchaseOrder $purchaseOrder) use ($request): PurchaseOrder {
            $purchaseOrder->setAttribute('can_edit_prices', $purchaseOrder->status === 'DRAFT' && $request->user()?->can('purchase-order.update'));
            $purchaseOrder->setAttribute('can_submit', $purchaseOrder->status === 'DRAFT'
                && $purchaseOrder->items_count > 0
                && $purchaseOrder->missing_prices_count === 0
                && $request->user()?->can('purchase-order.submit'));
            $purchaseOrder->setAttribute('can_cancel', in_array($purchaseOrder->status, ['DRAFT', 'SUBMITTED'], true) && $request->user()?->can('purchase-order.cancel'));

            return $purchaseOrder;
        });

        return Inertia::render('Transaction/Procurement/PurchaseOrder/Index', [
            'purchaseOrders' => $purchaseOrders,
            'filters' => $request->only('search', 'status'),
        ]);
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        abort_unless($request->user()?->can('purchase-order.view'), 403);

        $purchaseOrder->load([
            'purchaseRequest:id,number,purpose,request_date',
            'company:id,name,code',
            'department:id,name,code',
            'vendor:id,code,name,address,email,phone',
            'submittedBy:id,name',
            'cancelledBy:id,name',
            'items.product:id,code,name',
            'items.serviceType:id,code,name',
            'items.uom:id,code,name',
            'items.priceHistories.changedBy:id,name',
        ]);

        $purchaseOrder->setAttribute('can_edit_prices', $purchaseOrder->status === 'DRAFT' && $request->user()?->can('purchase-order.update'));
        $purchaseOrder->setAttribute('can_submit', $purchaseOrder->status === 'DRAFT'
            && $purchaseOrder->items->isNotEmpty()
            && $purchaseOrder->items->every(fn ($item): bool => $item->unit_price !== null)
            && $request->user()?->can('purchase-order.submit'));
        $purchaseOrder->setAttribute('can_cancel', in_array($purchaseOrder->status, ['DRAFT', 'SUBMITTED'], true) && $request->user()?->can('purchase-order.cancel'));

        return Inertia::render('Transaction/Procurement/PurchaseOrder/Show', [
            'purchaseOrder' => $purchaseOrder,
        ]);
    }

    public function edit(Request $request, PurchaseOrder $purchaseOrder): Response
    {
        abort_unless($request->user()?->can('purchase-order.update'), 403);
        abort_unless($purchaseOrder->status === 'DRAFT', 403);

        $purchaseOrder->load([
            'purchaseRequest:id,number',
            'vendor:id,code,name',
            'items.product:id,code,name',
            'items.serviceType:id,code,name',
            'items.uom:id,code,name',
        ]);

        return Inertia::render('Transaction/Procurement/PurchaseOrder/Edit', [
            'purchaseOrder' => $purchaseOrder,
        ]);
    }

    public function update(UpdatePurchaseOrderPriceRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->purchaseOrderManagementService->updatePrices(
            $purchaseOrder,
            $request->user(),
            $request->validated('items'),
        );

        return redirect()->route('transaction.procurement.purchase-orders.show', $purchaseOrder)
            ->with('success', 'Harga PO berhasil diperbarui dan perubahan tersimpan di histori.');
    }

    public function submit(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($request->user()?->can('purchase-order.submit'), 403);

        try {
            $this->purchaseOrderManagementService->submit($purchaseOrder, $request->user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('transaction.procurement.purchase-orders.show', $purchaseOrder)
            ->with('success', 'PO berhasil dikirim dan masuk ke antrian Goods Receipt.');
    }

    public function cancel(CancelPurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        try {
            $this->purchaseOrderManagementService->cancel(
                $purchaseOrder,
                $request->user(),
                $request->validated('reason'),
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('transaction.procurement.purchase-orders.index')
            ->with('success', 'PO berhasil dibatalkan. Status Purchase Request tetap Approved.');
    }
}
