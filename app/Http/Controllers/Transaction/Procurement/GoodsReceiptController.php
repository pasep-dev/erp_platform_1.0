<?php

namespace App\Http\Controllers\Transaction\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\Procurement\StoreGoodsReceiptRequest;
use App\Http\Requests\Transaction\Procurement\UpdateGoodsReceiptRequest;
use App\Models\Master\Organization\Branch;
use App\Models\Master\Organization\Location;
use App\Models\Transaction\Procurement\GoodsReceipt;
use App\Models\Transaction\Procurement\GoodsReceiptItem;
use App\Services\Transaction\Procurement\GoodsReceiptManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class GoodsReceiptController extends Controller
{
    public function __construct(private readonly GoodsReceiptManagementService $goodsReceiptManagementService) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('good-receipt.view'), 403);

        $user = $request->user();

        return Inertia::render('Transaction/Procurement/GoodReceive/Index', [
            'queue' => $this->goodsReceiptManagementService->availableSources(),
            'permissions' => [
                'create' => $user?->can('good-receipt.create') === true,
                'update' => $user?->can('good-receipt.update') === true,
                'post' => $user?->can('good-receipt.submit') === true,
                'delete' => $user?->can('good-receipt.delete') === true,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->can('good-receipt.create'), 403);

        $sourceParameters = Validator::make($request->query(), [
            'source_type' => ['required', Rule::in([
                GoodsReceipt::SOURCE_PURCHASE_ORDER,
                GoodsReceipt::SOURCE_PURCHASE_REQUEST,
            ])],
            'source_id' => ['required', 'string'],
            'vendor_id' => ['nullable', 'string'],
        ])->validate();

        $sourceDetails = $this->goodsReceiptManagementService->sourceDetails(
            $sourceParameters['source_type'],
            $sourceParameters['source_id'],
            $sourceParameters['vendor_id'] ?? null,
        );

        return Inertia::render('Transaction/Procurement/GoodReceive/Create', [
            'source' => $sourceDetails['source'],
            ...$this->destinationOptions($sourceDetails['source']['company_id']),
            'items' => array_map(fn (array $item): array => [
                ...$item,
                'quantity_received' => null,
                'quantity_accepted' => null,
                'quantity_rejected' => 0,
                'remarks' => '',
            ], $sourceDetails['items']),
        ]);
    }

    public function store(StoreGoodsReceiptRequest $request): RedirectResponse
    {
        $goodsReceipt = $this->goodsReceiptManagementService->createDraft(
            $request->user(),
            $request->validated(),
        );

        return redirect()->route('transaction.procurement.goods-receipts.show', $goodsReceipt)
            ->with('success', 'Draft Goods Receipt berhasil dibuat. Periksa datanya lalu posting penerimaan.');
    }

    public function show(Request $request, GoodsReceipt $goodsReceipt): Response
    {
        abort_unless($request->user()?->can('good-receipt.view'), 403);

        $goodsReceipt->load([
            'purchaseOrder.purchaseRequest:id,number',
            'purchaseRequest:id,number',
            'company:id,name,code',
            'department:id,name,code',
            'warehouseLocation:id,code,name,branch_id',
            'storageLocation:id,code,name,parent_id',
            'vendor:id,code,name,address,email,phone',
            'createdBy:id,name',
            'postedBy:id,name',
            'items.purchaseOrderItem.product:id,code,name',
            'items.purchaseOrderItem.serviceType:id,code,name',
            'items.purchaseOrderItem.uom:id,code,name',
            'items.purchaseRequestItem.product:id,code,name',
            'items.purchaseRequestItem.serviceType:id,code,name',
            'items.purchaseRequestItem.uom:id,code,name',
        ]);
        $goodsReceipt->setAttribute('can_edit', $goodsReceipt->status === GoodsReceipt::STATUS_DRAFT
            && $request->user()?->can('good-receipt.update'));
        $goodsReceipt->setAttribute('can_post', $goodsReceipt->status === GoodsReceipt::STATUS_DRAFT
            && $request->user()?->can('good-receipt.submit'));
        $goodsReceipt->setAttribute('can_delete', $goodsReceipt->status === GoodsReceipt::STATUS_DRAFT
            && $request->user()?->can('good-receipt.delete'));

        return Inertia::render('Transaction/Procurement/GoodReceive/Show', [
            'goodsReceipt' => $goodsReceipt,
        ]);
    }

    public function edit(Request $request, GoodsReceipt $goodsReceipt): Response
    {
        abort_unless($request->user()?->can('good-receipt.update'), 403);
        abort_unless($goodsReceipt->status === GoodsReceipt::STATUS_DRAFT, 403);

        $sourceId = $goodsReceipt->purchase_order_id ?? $goodsReceipt->purchase_request_id;
        $sourceDetails = $this->goodsReceiptManagementService->sourceDetails(
            $goodsReceipt->source_type,
            $sourceId,
            $goodsReceipt->source_type === GoodsReceipt::SOURCE_PURCHASE_REQUEST ? $goodsReceipt->vendor_id : null,
        );
        $goodsReceipt->load('items');
        $existingItems = $goodsReceipt->items->keyBy(function (GoodsReceiptItem $item) use ($goodsReceipt): string {
            return (string) ($goodsReceipt->source_type === GoodsReceipt::SOURCE_PURCHASE_ORDER
                ? $item->purchase_order_item_id
                : $item->purchase_request_item_id);
        });

        $items = array_map(function (array $item) use ($existingItems): array {
            $existing = $existingItems->get($item['source_item_id']);

            return [
                ...$item,
                'quantity_received' => $existing?->quantity_received,
                'quantity_accepted' => $existing?->quantity_accepted,
                'quantity_rejected' => $existing?->quantity_rejected ?? 0,
                'remarks' => $existing?->remarks ?? '',
            ];
        }, $sourceDetails['items']);

        return Inertia::render('Transaction/Procurement/GoodReceive/Edit', [
            'goodsReceipt' => $goodsReceipt,
            'source' => $sourceDetails['source'],
            ...$this->destinationOptions($goodsReceipt->company_id),
            'items' => $items,
        ]);
    }

    public function update(UpdateGoodsReceiptRequest $request, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $this->goodsReceiptManagementService->updateDraft($goodsReceipt, $request->validated());

        return redirect()->route('transaction.procurement.goods-receipts.show', $goodsReceipt)
            ->with('success', 'Draft Goods Receipt berhasil diperbarui.');
    }

    public function submit(Request $request, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        abort_unless($request->user()?->can('good-receipt.submit'), 403);

        try {
            $this->goodsReceiptManagementService->post($goodsReceipt, $request->user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('transaction.procurement.goods-receipts.show', $goodsReceipt)
            ->with('success', 'Goods Receipt berhasil diposting dan saldo penerimaan telah diperbarui.');
    }

    public function destroy(Request $request, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        abort_unless($request->user()?->can('good-receipt.delete'), 403);
        abort_unless($goodsReceipt->status === GoodsReceipt::STATUS_DRAFT, 403);

        $this->goodsReceiptManagementService->deleteDraft($goodsReceipt);

        return redirect()->route('transaction.procurement.goods-receipts.index')
            ->with('success', 'Draft Goods Receipt berhasil dihapus.');
    }

    /** @return array{branches: Collection, warehouses: Collection, storageLocations: Collection} */
    private function destinationOptions(string $companyId): array
    {
        $branches = Branch::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $locations = Location::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'branch_id', 'parent_id', 'code', 'name', 'location_type']);

        return [
            'branches' => $branches,
            'warehouses' => $locations->filter(fn (Location $location): bool => in_array(mb_strtolower((string) $location->location_type), ['warehouse', 'gudang'], true))->values(),
            'storageLocations' => $locations->filter(fn (Location $location): bool => $location->parent_id !== null)->values(),
        ];
    }
}
