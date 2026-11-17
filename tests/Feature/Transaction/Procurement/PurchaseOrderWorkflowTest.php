<?php

namespace Tests\Feature\Transaction\Procurement;

use App\Models\Administrator\Workflow\ApprovalWorkflow;
use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\Department;
use App\Models\Master\Procurement\ServiceType;
use App\Models\Master\Procurement\UnitOfMeasure;
use App\Models\Master\Procurement\Vendor;
use App\Models\Master\Procurement\VendorType;
use App\Models\NumberingDocument;
use App\Models\Transaction\Procurement\PurchaseOrder;
use App\Models\Transaction\Procurement\PurchaseOrderItemPriceHistory;
use App\Models\Transaction\Procurement\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PurchaseOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_pr_approval_generates_po_tracks_price_and_submits_to_gr_queue(): void
    {
        Storage::fake('local');

        $requester = User::query()->create([
            'name' => 'PO Workflow Requester',
            'email' => 'po-workflow-requester@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $approver = User::query()->create([
            'name' => 'PO Workflow Approver',
            'email' => 'po-workflow-approver@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $company = Company::query()->create([
            'code' => 'PO-COMP',
            'name' => 'PO Test Company',
            'is_active' => true,
        ]);
        $department = Department::query()->create([
            'company_id' => $company->id,
            'code' => 'PO-DEPT',
            'name' => 'PO Test Department',
            'is_active' => true,
        ]);
        $vendorType = VendorType::query()->create([
            'code' => 'PO-VENDOR-TYPE',
            'name' => 'Supplier',
            'is_active' => true,
        ]);
        $vendor = Vendor::query()->create([
            'vendor_type_id' => $vendorType->id,
            'code' => 'PO-VENDOR',
            'name' => 'PO Test Vendor',
            'is_active' => true,
        ]);
        $serviceType = ServiceType::query()->create([
            'code' => 'PO-SERVICE',
            'name' => 'Equipment Service',
            'requires_asset' => false,
            'is_active' => true,
        ]);
        $unit = UnitOfMeasure::query()->create([
            'code' => 'PO-UNIT',
            'name' => 'Unit',
            'is_active' => true,
        ]);

        $workflow = ApprovalWorkflow::query()->create([
            'module' => 'PURCHASE_REQUEST',
            'company_id' => $company->id,
            'department_id' => $department->id,
            'name' => 'PO Test Workflow',
            'is_active' => true,
        ]);
        $workflow->levels()->create([
            'level' => 1,
            'approver_user_id' => $approver->id,
        ]);
        $workflow->levels()->create([
            'level' => 2,
            'approver_user_id' => $requester->id,
        ]);

        foreach ([
            [
                'code' => 'PURCHASE_REQUEST',
                'name' => 'Purchase Request',
                'pattern' => 'PR-{DEPARTMENT}-{YYYY}{SEQ}',
            ],
            [
                'code' => 'PURCHASE_ORDER',
                'name' => 'Purchase Order',
                'pattern' => '{COMPANY}/{DOCUMENT_TYPE}/{DEPARTMENT}/{YYYY}/{SEQ}',
            ],
        ] as $numberingDocument) {
            NumberingDocument::query()->create([
                ...$numberingDocument,
                'sequence_length' => 3,
                'reset_period' => 'yearly',
                'is_active' => true,
            ]);
        }

        $purchaseRequest = PurchaseRequest::query()->create([
            'number' => 'DRAFT-PO-FLOW',
            'request_date' => now()->toDateString(),
            'requester_id' => $requester->id,
            'company_id' => $company->id,
            'department_id' => $department->id,
            'purpose' => 'Equipment service procurement',
            'status' => 'DRAFT',
        ]);
        $purchaseRequest->items()->create([
            'item_type' => 'SERVICE',
            'service_type_id' => $serviceType->id,
            'description' => 'Preventive service',
            'quantity' => 2,
            'uom_id' => $unit->id,
            'estimated_unit_price' => 100000,
            'vendor_id' => $vendor->id,
            'requires_po' => true,
            'tax_included' => false,
        ]);
        $purchaseRequest->items()->create([
            'item_type' => 'SERVICE',
            'service_type_id' => $serviceType->id,
            'description' => 'Service purchased without a PO',
            'quantity' => 1,
            'uom_id' => $unit->id,
            'estimated_unit_price' => 50000,
            'requires_po' => false,
            'tax_included' => false,
        ]);

        $this->actingAs($requester)
            ->post(route('transaction.procurement.purchase-requests.submit', $purchaseRequest))
            ->assertRedirect(route('transaction.procurement.purchase-requests.show', $purchaseRequest))
            ->assertSessionHasNoErrors();

        $this->actingAs($requester)
            ->post(route('transaction.procurement.purchase-requests.mark-approved', $purchaseRequest), [
                'remarks' => 'Dokumen sudah mendapat tanda tangan manual.',
                'signed_document' => UploadedFile::fake()->create('approved-pr.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect(route('transaction.procurement.purchase-requests.show', $purchaseRequest))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'APPROVED',
        ]);
        $this->assertDatabaseCount('purchase_orders', 1);

        $purchaseOrder = PurchaseOrder::query()->with('items')->firstOrFail();
        $this->assertSame('DRAFT', $purchaseOrder->status);
        $this->assertSame($vendor->id, $purchaseOrder->vendor_id);
        $this->assertCount(1, $purchaseOrder->items);
        $this->assertSame('100000.00', $purchaseOrder->items->first()->unit_price);
        $this->assertSame('100000.00', $purchaseOrder->items->first()->pr_estimated_unit_price);

        $requester->givePermissionTo(
            Permission::query()->create([
                'name' => 'purchase-order.update',
                'guard_name' => 'web',
            ]),
        );
        $requester->givePermissionTo(
            Permission::query()->create([
                'name' => 'purchase-order.submit',
                'guard_name' => 'web',
            ]),
        );
        $requester->givePermissionTo(
            Permission::query()->create([
                'name' => 'purchase-order.cancel',
                'guard_name' => 'web',
            ]),
        );

        $orderItem = $purchaseOrder->items->firstOrFail();
        $this->actingAs($requester)
            ->put(route('transaction.procurement.purchase-orders.update', $purchaseOrder), [
                'items' => [[
                    'id' => $orderItem->id,
                    'unit_price' => 110000,
                    'reason' => '',
                ]],
            ])
            ->assertSessionHasErrors(['items.0.reason']);

        $this->actingAs($requester)
            ->put(route('transaction.procurement.purchase-orders.update', $purchaseOrder), [
                'items' => [[
                    'id' => $orderItem->id,
                    'unit_price' => 125000,
                    'reason' => 'Penawaran vendor terbaru lebih tinggi.',
                    'description' => 'Percobaan mengubah deskripsi yang terkunci.',
                ]],
            ])
            ->assertRedirect(route('transaction.procurement.purchase-orders.show', $purchaseOrder))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('purchase_order_items', [
            'id' => $orderItem->id,
            'pr_estimated_unit_price' => 100000,
            'unit_price' => 125000,
            'description' => 'Preventive service',
        ]);
        $this->assertDatabaseHas('purchase_order_item_price_histories', [
            'purchase_order_item_id' => $orderItem->id,
            'previous_unit_price' => 100000,
            'new_unit_price' => 125000,
            'reason' => 'Penawaran vendor terbaru lebih tinggi.',
            'changed_by' => $requester->id,
        ]);

        $this->actingAs($requester)
            ->post(route('transaction.procurement.purchase-orders.submit', $purchaseOrder))
            ->assertRedirect(route('transaction.procurement.purchase-orders.show', $purchaseOrder));

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'SUBMITTED',
            'submitted_by' => $requester->id,
        ]);

        $this->actingAs($requester)
            ->put(route('transaction.procurement.purchase-orders.update', $purchaseOrder), [
                'items' => [[
                    'id' => $orderItem->id,
                    'unit_price' => 150000,
                    'reason' => 'Price edit after submit is forbidden.',
                ]],
            ])
            ->assertForbidden();

        $this->actingAs($requester)
            ->post(route('transaction.procurement.purchase-orders.cancel', $purchaseOrder), [
                'reason' => 'Vendor tidak dapat memenuhi jadwal.',
            ])
            ->assertRedirect(route('transaction.procurement.purchase-orders.index'));

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'CANCELLED',
            'cancellation_reason' => 'Vendor tidak dapat memenuhi jadwal.',
        ]);
        $this->assertDatabaseHas('purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'APPROVED',
        ]);
        $this->assertSame(1, PurchaseOrderItemPriceHistory::query()->count());
    }
}
