<?php

namespace Tests\Feature\Transaction\Procurement;

use App\Models\Administrator\Workflow\ApprovalAction;
use App\Models\Administrator\Workflow\ApprovalRequest;
use App\Models\Administrator\Workflow\ApprovalRequestLevel;
use App\Models\Administrator\Workflow\ApprovalWorkflow;
use App\Models\Master\Organization\Branch;
use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\Department;
use App\Models\Master\Organization\Position;
use App\Models\Master\Procurement\ServiceType;
use App\Models\Master\Procurement\UnitOfMeasure;
use App\Models\Master\Product\Brand;
use App\Models\Master\Product\Category;
use App\Models\Master\Product\Model as ProductModel;
use App\Models\Master\Product\Product;
use App\Models\Master\Product\Type;
use App\Models\NumberingDocument;
use App\Models\Transaction\Procurement\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchaseRequestPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_receives_all_required_props_without_organization_assignments(): void
    {
        $user = User::query()->create([
            'name' => 'Purchase Request Tester',
            'email' => 'purchase-request-page@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('transaction.procurement.purchase-requests.create'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Transaction/Procurement/PurchaseRequest/Create')
                ->hasAll([
                    'products',
                    'companies',
                    'departments',
                    'branches',
                    'serviceTypes',
                    'vendors',
                    'unitsOfMeasure',
                    'requester',
                    'company',
                    'department',
                ])
                ->where('requester.name', $user->name)
                ->where('company', null)
                ->where('department', null));
    }

    public function test_index_exposes_actions_that_match_purchase_request_status(): void
    {
        $owner = User::query()->create([
            'name' => 'Index Owner',
            'email' => 'purchase-request-index-owner@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $company = Company::query()->create([
            'code' => 'INDEX-COMP',
            'name' => 'Index Company',
            'is_active' => true,
        ]);
        $department = Department::query()->create([
            'company_id' => $company->id,
            'code' => 'INDEX-DEPT',
            'name' => 'Index Department',
            'is_active' => true,
        ]);
        $draft = $this->createPurchaseRequestForIndex(
            $owner,
            $company,
            $department,
            'INDEX-DRAFT',
            'DRAFT',
            now(),
        );
        $submitted = $this->createPurchaseRequestForIndex(
            $owner,
            $company,
            $department,
            'INDEX-SUBMITTED',
            'SUBMITTED',
            now()->subDay(),
        );
        $approved = $this->createPurchaseRequestForIndex(
            $owner,
            $company,
            $department,
            'INDEX-APPROVED',
            'APPROVED',
            now()->subDays(2),
        );
        $cancelled = $this->createPurchaseRequestForIndex(
            $owner,
            $company,
            $department,
            'INDEX-CANCELLED',
            'CANCELLED',
            now()->subDays(3),
        );

        $this->actingAs($owner)
            ->get(route('transaction.procurement.purchase-requests.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Transaction/Procurement/PurchaseRequest/Index')
                ->where('purchaseRequests.data.0.id', $draft->id)
                ->where('purchaseRequests.data.0.can_edit', true)
                ->where('purchaseRequests.data.0.can_delete', true)
                ->where('purchaseRequests.data.0.can_print', false)
                ->where('purchaseRequests.data.0.can_cancel', false)
                ->where('purchaseRequests.data.1.id', $submitted->id)
                ->where('purchaseRequests.data.1.can_print', true)
                ->where('purchaseRequests.data.1.can_cancel', true)
                ->where('purchaseRequests.data.2.id', $approved->id)
                ->where('purchaseRequests.data.2.is_locked', true)
                ->where('purchaseRequests.data.3.id', $cancelled->id)
                ->where('purchaseRequests.data.3.is_locked', true));
    }

    public function test_show_page_includes_signatory_positions_for_print(): void
    {
        [$owner, $approver, $purchaseRequest, $approvalRequest] = $this->createSubmittedRequestForCancellation(
            'PRINT-SIGNATORIES',
        );
        $company = Company::query()->findOrFail($purchaseRequest->company_id);
        $requesterPosition = Position::query()->create([
            'company_id' => $company->id,
            'code' => 'REQUESTER-POSITION',
            'name' => 'IT Manager',
            'is_active' => true,
        ]);
        $approverPosition = Position::query()->create([
            'company_id' => $company->id,
            'code' => 'APPROVER-POSITION',
            'name' => 'Finance Director',
            'is_active' => true,
        ]);
        $owner->update(['position_id' => $requesterPosition->id]);
        $approver->update(['position_id' => $approverPosition->id]);
        ApprovalRequestLevel::query()->create([
            'approval_request_id' => $approvalRequest->id,
            'level' => 1,
            'approver_user_id' => $approver->id,
            'status' => 'PENDING',
        ]);

        $this->actingAs($owner)
            ->get(route('transaction.procurement.purchase-requests.show', $purchaseRequest))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Transaction/Procurement/PurchaseRequest/Show')
                ->where('purchaseRequest.requester.position.name', 'IT Manager')
                ->where('approval.levels.0.approver.position.name', 'Finance Director'));
    }

    public function test_store_uses_selected_company_department_and_branch_address(): void
    {
        $user = User::query()->create([
            'name' => 'Purchase Request Tester',
            'email' => 'purchase-request-store@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $company = Company::query()->create([
            'code' => 'COMP-01',
            'name' => 'Company One',
            'is_active' => true,
        ]);
        $department = Department::query()->create([
            'company_id' => $company->id,
            'code' => 'DEPT-01',
            'name' => 'Department One',
            'is_active' => true,
        ]);
        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'code' => 'BR-01',
            'name' => 'Main Branch',
            'address' => 'Jl. Merdeka No. 1',
            'is_active' => true,
        ]);

        $category = Category::query()->create([
            'code' => 'CAT-01',
            'name' => 'Equipment',
            'is_active' => true,
        ]);
        $type = Type::query()->create([
            'category_id' => $category->id,
            'code' => 'TYPE-01',
            'name' => 'Computer',
            'is_active' => true,
        ]);
        $brand = Brand::query()->create([
            'code' => 'BRAND-01',
            'name' => 'Example Brand',
            'is_active' => true,
        ]);
        $productModel = ProductModel::query()->create([
            'brand_id' => $brand->id,
            'type_id' => $type->id,
            'code' => 'MODEL-01',
            'name' => 'Example Model',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'type_id' => $type->id,
            'brand_id' => $brand->id,
            'model_id' => $productModel->id,
            'group' => 'IT',
            'code' => 'PRODUCT-01',
            'name' => 'Laptop',
            'is_active' => true,
        ]);
        $unit = UnitOfMeasure::query()->create([
            'code' => 'PCS',
            'name' => 'Pieces',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(
            route('transaction.procurement.purchase-requests.store'),
            [
                'company_id' => $company->id,
                'department_id' => $department->id,
                'request_date' => now()->toDateString(),
                'purpose' => 'Laptop procurement',
                'items' => [[
                    'item_type' => 'PRODUCT',
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'uom_id' => $unit->id,
                    'estimated_unit_price' => 2000000,
                    'tax_included' => false,
                    'requires_po' => true,
                    'required_date' => now()->addWeek()->toDateString(),
                    'vendor_pic' => 'Purchasing contact',
                    'delivery_branch_id' => $branch->id,
                ]],
            ],
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $purchaseRequest = PurchaseRequest::query()->first();

        $this->assertNotNull(
            $purchaseRequest,
            'Purchase Request was not stored. Redirected to '.$response->headers->get('Location'),
        );

        $response->assertRedirect(
            route('transaction.procurement.purchase-requests.show', $purchaseRequest),
        );

        $this->assertDatabaseHas('trx_purchase_requests', [
            'id' => $purchaseRequest->id,
            'company_id' => $company->id,
            'department_id' => $department->id,
        ]);

        $this->assertDatabaseHas('trx_purchase_request_items', [
            'purchase_request_id' => $purchaseRequest->id,
            'delivery_address' => $branch->address,
            'estimated_unit_price' => 2000000,
            'tax_included' => false,
            'requires_po' => true,
            'vendor_pic' => 'Purchasing contact',
        ]);
    }

    public function test_owner_can_edit_draft_and_update_item_details(): void
    {
        $user = User::query()->create([
            'name' => 'Draft Owner',
            'email' => 'purchase-request-edit@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $company = Company::query()->create([
            'code' => 'EDIT-COMP',
            'name' => 'Edit Company',
            'is_active' => true,
        ]);
        $department = Department::query()->create([
            'company_id' => $company->id,
            'code' => 'EDIT-DEPT',
            'name' => 'Edit Department',
            'is_active' => true,
        ]);
        $serviceType = ServiceType::query()->create([
            'code' => 'EDIT-SERVICE',
            'name' => 'Installation',
            'requires_asset' => false,
            'is_active' => true,
        ]);
        $unit = UnitOfMeasure::query()->create([
            'code' => 'EDIT-UNIT',
            'name' => 'Unit',
            'is_active' => true,
        ]);
        $purchaseRequest = PurchaseRequest::query()->create([
            'number' => 'DRAFT-EDIT-TEST',
            'request_date' => now()->toDateString(),
            'requester_id' => $user->id,
            'company_id' => $company->id,
            'department_id' => $department->id,
            'purpose' => 'Original purpose',
            'status' => 'DRAFT',
        ]);
        $purchaseRequest->items()->create([
            'item_type' => 'SERVICE',
            'service_type_id' => $serviceType->id,
            'description' => 'Original description',
            'quantity' => 1,
            'uom_id' => $unit->id,
            'estimated_unit_price' => 100000,
            'tax_included' => true,
        ]);

        $this->actingAs($user)
            ->get(route('transaction.procurement.purchase-requests.edit', $purchaseRequest))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Transaction/Procurement/PurchaseRequest/Create')
                ->where('purchaseRequest.id', $purchaseRequest->id)
                ->where('purchaseRequest.items.0.estimated_unit_price', 100000));

        $response = $this->actingAs($user)->put(
            route('transaction.procurement.purchase-requests.update', $purchaseRequest),
            [
                'company_id' => $company->id,
                'department_id' => $department->id,
                'request_date' => now()->toDateString(),
                'purpose' => 'Updated purpose',
                'items' => [[
                    'item_type' => 'SERVICE',
                    'service_type_id' => $serviceType->id,
                    'description' => 'Updated description',
                    'quantity' => 2,
                    'uom_id' => $unit->id,
                    'estimated_unit_price' => 250000,
                    'tax_included' => false,
                    'requires_po' => false,
                ]],
            ],
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(
            route('transaction.procurement.purchase-requests.show', $purchaseRequest),
        );
        $this->assertDatabaseHas('trx_purchase_requests', [
            'id' => $purchaseRequest->id,
            'purpose' => 'Updated purpose',
        ]);
        $this->assertDatabaseHas('trx_purchase_request_items', [
            'purchase_request_id' => $purchaseRequest->id,
            'description' => 'Updated description',
            'quantity' => 2,
            'estimated_unit_price' => 250000,
        ]);
    }

    public function test_only_request_owner_can_edit_a_draft_and_submitted_requests_cannot_be_edited(): void
    {
        $owner = User::query()->create([
            'name' => 'Draft Owner',
            'email' => 'purchase-request-owner@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $otherUser = User::query()->create([
            'name' => 'Other User',
            'email' => 'purchase-request-other@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $company = Company::query()->create([
            'code' => 'OWNER-COMP',
            'name' => 'Owner Company',
            'is_active' => true,
        ]);
        $department = Department::query()->create([
            'company_id' => $company->id,
            'code' => 'OWNER-DEPT',
            'name' => 'Owner Department',
            'is_active' => true,
        ]);
        $purchaseRequest = PurchaseRequest::query()->create([
            'number' => 'DRAFT-OWNER-TEST',
            'request_date' => now()->toDateString(),
            'requester_id' => $owner->id,
            'company_id' => $company->id,
            'department_id' => $department->id,
            'purpose' => 'Owner purpose',
            'status' => 'DRAFT',
        ]);

        $this->actingAs($otherUser)
            ->get(route('transaction.procurement.purchase-requests.edit', $purchaseRequest))
            ->assertForbidden();

        $purchaseRequest->update(['status' => 'SUBMITTED']);

        $this->actingAs($owner)
            ->get(route('transaction.procurement.purchase-requests.edit', $purchaseRequest))
            ->assertForbidden();
    }

    public function test_submit_uses_the_purchase_request_organization_for_workflow_matching(): void
    {
        $submitter = User::query()->create([
            'name' => 'PR Submitter',
            'email' => 'purchase-request-submit@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $approver = User::query()->create([
            'name' => 'GA Approver',
            'email' => 'purchase-request-approver@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $secondApprover = User::query()->create([
            'name' => 'Finance Approver',
            'email' => 'purchase-request-second-approver@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $company = Company::query()->create([
            'code' => 'SUBMIT-COMP',
            'name' => 'Submit Company',
            'is_active' => true,
        ]);
        $department = Department::query()->create([
            'company_id' => $company->id,
            'code' => 'SUBMIT-DEPT',
            'name' => 'Submit Department',
            'is_active' => true,
        ]);
        $workflow = ApprovalWorkflow::query()->create([
            'module' => 'PURCHASE_REQUEST',
            'company_id' => $company->id,
            'department_id' => $department->id,
            'name' => 'Purchase Request Workflow',
            'is_active' => true,
        ]);
        $workflow->levels()->create([
            'level' => 1,
            'approver_user_id' => $approver->id,
        ]);
        $workflow->levels()->create([
            'level' => 2,
            'approver_user_id' => $submitter->id,
        ]);
        $workflow->levels()->create([
            'level' => 3,
            'approver_user_id' => $secondApprover->id,
        ]);
        NumberingDocument::query()->create([
            'code' => 'PURCHASE_REQUEST',
            'name' => 'Purchase Request',
            'pattern' => '{DEPARTMENT}/{SEQ}',
            'sequence_length' => 3,
            'reset_period' => 'yearly',
            'is_active' => true,
        ]);
        $purchaseRequest = PurchaseRequest::query()->create([
            'number' => 'DRAFT-SUBMIT-TEST',
            'request_date' => now()->toDateString(),
            'requester_id' => $submitter->id,
            'company_id' => $company->id,
            'department_id' => $department->id,
            'purpose' => 'Submit test',
            'status' => 'DRAFT',
        ]);

        $response = $this->actingAs($submitter)->post(
            route('transaction.procurement.purchase-requests.submit', $purchaseRequest),
        );

        $response->assertRedirect(
            route('transaction.procurement.purchase-requests.show', $purchaseRequest),
        );
        $approvalRequest = ApprovalRequest::query()
            ->where('document_id', $purchaseRequest->id)
            ->firstOrFail();

        $this->assertSame($workflow->id, $approvalRequest->approval_workflow_id);
        $this->assertDatabaseHas('trx_purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'SUBMITTED',
        ]);
        $this->assertSame('PENDING', $approvalRequest->levels()->first()->status->value);
        $this->assertSame('WAITING', $approvalRequest->levels()->where('level', 2)->first()->status->value);

        Storage::fake('local');
        $this->actingAs($submitter)->post(
            route('transaction.procurement.purchase-requests.mark-approved', $purchaseRequest),
            [],
        )->assertSessionHasErrors(['remarks', 'signed_document']);

        $this->actingAs($submitter)->post(
            route('transaction.procurement.purchase-requests.mark-approved', $purchaseRequest),
            [
                'remarks' => 'Dokumen telah ditandatangani secara manual.',
                'signed_document' => UploadedFile::fake()->create('signed-pr.pdf', 200, 'application/pdf'),
            ],
        )->assertRedirect(route('transaction.procurement.purchase-requests.show', $purchaseRequest));

        $this->assertDatabaseHas('trx_purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'APPROVED',
        ]);
        $this->assertNotNull($purchaseRequest->fresh()->approved_at);
        $this->assertDatabaseHas('approval_requests', [
            'id' => $approvalRequest->id,
            'status' => 'APPROVED',
            'current_level' => null,
            'manual_approved_by' => $submitter->id,
            'manual_approval_remarks' => 'Dokumen telah ditandatangani secara manual.',
        ]);
        $this->assertDatabaseCount('approval_actions', 3);

        foreach ([1, 2, 3] as $level) {
            $this->assertDatabaseHas('approval_request_levels', [
                'approval_request_id' => $approvalRequest->id,
                'level' => $level,
                'status' => 'APPROVED',
                'remarks' => 'Dokumen telah ditandatangani secara manual.',
            ]);
        }

        $savedDocumentPath = $approvalRequest->fresh()->manual_approval_document_path;
        $this->assertNotNull($savedDocumentPath);
        Storage::disk('local')->assertExists($savedDocumentPath);

        $this->actingAs($submitter)
            ->get(route('transaction.procurement.purchase-requests.approval-document', $purchaseRequest))
            ->assertOk();

        $this->assertDatabaseHas('trx_purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'APPROVED',
        ]);
    }

    public function test_submit_accepts_a_workflow_that_assigns_the_submitter_as_manual_approver(): void
    {
        $submitter = User::query()->create([
            'name' => 'Self Approving Submitter',
            'email' => 'purchase-request-self-approval@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $company = Company::query()->create([
            'code' => 'SELF-COMP',
            'name' => 'Self Approval Company',
            'is_active' => true,
        ]);
        $department = Department::query()->create([
            'company_id' => $company->id,
            'code' => 'SELF-DEPT',
            'name' => 'Self Approval Department',
            'is_active' => true,
        ]);
        $workflow = ApprovalWorkflow::query()->create([
            'module' => 'PURCHASE_REQUEST',
            'company_id' => $company->id,
            'department_id' => $department->id,
            'name' => 'Self Approval Workflow',
            'is_active' => true,
        ]);
        $workflow->levels()->create([
            'level' => 1,
            'approver_user_id' => $submitter->id,
        ]);
        NumberingDocument::query()->create([
            'code' => 'PURCHASE_REQUEST',
            'name' => 'Purchase Request',
            'pattern' => '{DEPARTMENT}/{SEQ}',
            'sequence_length' => 3,
            'reset_period' => 'yearly',
            'is_active' => true,
        ]);
        $purchaseRequest = PurchaseRequest::query()->create([
            'number' => 'DRAFT-SELF-APPROVAL',
            'request_date' => now()->toDateString(),
            'requester_id' => $submitter->id,
            'company_id' => $company->id,
            'department_id' => $department->id,
            'purpose' => 'Self approval test',
            'status' => 'DRAFT',
        ]);

        $response = $this->actingAs($submitter)->post(
            route('transaction.procurement.purchase-requests.submit', $purchaseRequest),
        );

        $response->assertRedirect(
            route('transaction.procurement.purchase-requests.show', $purchaseRequest),
        );
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('trx_purchase_requests', [
            'id' => $purchaseRequest->id,
            'number' => 'SELF-DEPT/001',
            'status' => 'SUBMITTED',
        ]);
        $approvalRequest = ApprovalRequest::query()
            ->where('document_id', $purchaseRequest->id)
            ->firstOrFail();
        $this->assertDatabaseHas('approval_requests', [
            'id' => $approvalRequest->id,
            'status' => 'PENDING',
        ]);
        $this->assertDatabaseHas('approval_request_levels', [
            'approval_request_id' => $approvalRequest->id,
            'approver_user_id' => $submitter->id,
            'status' => 'PENDING',
        ]);
    }

    public function test_only_purchase_request_owner_or_administrator_can_mark_manual_approval(): void
    {
        $owner = User::query()->create([
            'name' => 'PR Owner',
            'email' => 'purchase-request-manual-owner@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $otherUser = User::query()->create([
            'name' => 'Other User',
            'email' => 'purchase-request-manual-other@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $company = Company::query()->create([
            'code' => 'MANUAL-COMP',
            'name' => 'Manual Approval Company',
            'is_active' => true,
        ]);
        $department = Department::query()->create([
            'company_id' => $company->id,
            'code' => 'MANUAL-DEPT',
            'name' => 'Manual Approval Department',
            'is_active' => true,
        ]);
        $workflow = ApprovalWorkflow::query()->create([
            'module' => 'PURCHASE_REQUEST',
            'company_id' => $company->id,
            'department_id' => $department->id,
            'name' => 'Manual Approval Workflow',
            'is_active' => true,
        ]);
        $workflow->levels()->create([
            'level' => 1,
            'approver_user_id' => $otherUser->id,
        ]);
        NumberingDocument::query()->create([
            'code' => 'PURCHASE_REQUEST',
            'name' => 'Purchase Request',
            'pattern' => '{DEPARTMENT}/{SEQ}',
            'sequence_length' => 3,
            'reset_period' => 'yearly',
            'is_active' => true,
        ]);
        $purchaseRequest = PurchaseRequest::query()->create([
            'number' => 'DRAFT-MANUAL-AUTH',
            'request_date' => now()->toDateString(),
            'requester_id' => $owner->id,
            'company_id' => $company->id,
            'department_id' => $department->id,
            'purpose' => 'Manual approval authorization test',
            'status' => 'DRAFT',
        ]);

        $this->actingAs($owner)->post(
            route('transaction.procurement.purchase-requests.submit', $purchaseRequest),
        )->assertRedirect();
        $approvalRequest = ApprovalRequest::query()
            ->where('document_id', $purchaseRequest->id)
            ->firstOrFail();

        Storage::fake('local');
        $this->actingAs($otherUser)->post(
            route('transaction.procurement.purchase-requests.mark-approved', $purchaseRequest),
            [
                'remarks' => 'Unauthorized approval attempt',
                'signed_document' => UploadedFile::fake()->create('signed-pr.pdf', 100, 'application/pdf'),
            ],
        )->assertForbidden();

        $this->assertDatabaseHas('trx_purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'SUBMITTED',
        ]);
        $this->assertDatabaseHas('approval_requests', [
            'id' => $approvalRequest->id,
            'status' => 'PENDING',
        ]);
        $this->assertDatabaseMissing('approval_actions', [
            'approval_request_id' => $approvalRequest->id,
        ]);
    }

    public function test_administrator_can_mark_manual_approval(): void
    {
        $admin = User::query()->create([
            'name' => 'PR Administrator',
            'email' => 'purchase-request-admin@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $owner = User::query()->create([
            'name' => 'PR Owner',
            'email' => 'purchase-request-admin-owner@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $administratorRole = Role::query()->create([
            'name' => 'Administrator',
            'guard_name' => 'web',
        ]);
        $admin->assignRole($administratorRole);
        $company = Company::query()->create([
            'code' => 'ADMIN-MANUAL-COMP',
            'name' => 'Administrator Manual Approval Company',
            'is_active' => true,
        ]);
        $department = Department::query()->create([
            'company_id' => $company->id,
            'code' => 'ADMIN-MANUAL-DEPT',
            'name' => 'Administrator Manual Approval Department',
            'is_active' => true,
        ]);
        $workflow = ApprovalWorkflow::query()->create([
            'module' => 'PURCHASE_REQUEST',
            'company_id' => $company->id,
            'department_id' => $department->id,
            'name' => 'Administrator Manual Approval Workflow',
            'is_active' => true,
        ]);
        $workflow->levels()->create([
            'level' => 1,
            'approver_user_id' => $admin->id,
        ]);
        NumberingDocument::query()->create([
            'code' => 'PURCHASE_REQUEST',
            'name' => 'Purchase Request',
            'pattern' => '{DEPARTMENT}/{SEQ}',
            'sequence_length' => 3,
            'reset_period' => 'yearly',
            'is_active' => true,
        ]);
        $purchaseRequest = PurchaseRequest::query()->create([
            'number' => 'DRAFT-ADMIN-MANUAL',
            'request_date' => now()->toDateString(),
            'requester_id' => $owner->id,
            'company_id' => $company->id,
            'department_id' => $department->id,
            'purpose' => 'Administrator manual approval test',
            'status' => 'DRAFT',
        ]);

        $this->actingAs($owner)->post(
            route('transaction.procurement.purchase-requests.submit', $purchaseRequest),
        )->assertRedirect();

        Storage::fake('local');
        $this->actingAs($admin)->post(
            route('transaction.procurement.purchase-requests.mark-approved', $purchaseRequest),
            [
                'remarks' => 'Approved from signed paper document.',
                'signed_document' => UploadedFile::fake()->create('signed-pr.pdf', 100, 'application/pdf'),
            ],
        )->assertRedirect();

        $this->assertDatabaseHas('trx_purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'APPROVED',
        ]);
        $this->assertDatabaseHas('approval_requests', [
            'document_id' => $purchaseRequest->id,
            'manual_approved_by' => $admin->id,
        ]);
    }

    public function test_owner_can_delete_a_draft_purchase_request(): void
    {
        $owner = User::query()->create([
            'name' => 'Draft Owner',
            'email' => 'purchase-request-delete-owner@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $company = Company::query()->create([
            'code' => 'DELETE-COMP',
            'name' => 'Delete Company',
            'is_active' => true,
        ]);
        $department = Department::query()->create([
            'company_id' => $company->id,
            'code' => 'DELETE-DEPT',
            'name' => 'Delete Department',
            'is_active' => true,
        ]);
        $purchaseRequest = PurchaseRequest::query()->create([
            'number' => 'DRAFT-DELETE-TEST',
            'request_date' => now()->toDateString(),
            'requester_id' => $owner->id,
            'company_id' => $company->id,
            'department_id' => $department->id,
            'purpose' => 'Delete draft test',
            'status' => 'DRAFT',
        ]);

        $this->actingAs($owner)
            ->delete(route('transaction.procurement.purchase-requests.destroy', $purchaseRequest))
            ->assertRedirect(route('transaction.procurement.purchase-requests.index'));

        $this->assertDatabaseMissing('trx_purchase_requests', [
            'id' => $purchaseRequest->id,
        ]);
    }

    public function test_owner_can_cancel_a_submitted_request_before_any_approval_action(): void
    {
        [$owner, $approver, $purchaseRequest, $approvalRequest] = $this->createSubmittedRequestForCancellation(
            'CANCEL-BEFORE-APPROVAL',
        );
        $level = ApprovalRequestLevel::query()->create([
            'approval_request_id' => $approvalRequest->id,
            'level' => 1,
            'approver_user_id' => $approver->id,
            'status' => 'PENDING',
        ]);

        $this->actingAs($approver)
            ->post(route('transaction.procurement.purchase-requests.cancel', $purchaseRequest))
            ->assertForbidden();

        $this->actingAs($owner)
            ->post(route('transaction.procurement.purchase-requests.cancel', $purchaseRequest))
            ->assertRedirect(route('transaction.procurement.purchase-requests.index'));

        $this->assertDatabaseHas('trx_purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'CANCELLED',
        ]);
        $this->assertDatabaseHas('approval_requests', [
            'id' => $approvalRequest->id,
            'status' => 'CANCELLED',
            'current_level' => null,
        ]);
        $this->assertDatabaseHas('approval_request_levels', [
            'id' => $level->id,
            'status' => 'CANCELLED',
        ]);
    }

    public function test_submitted_request_cannot_be_cancelled_after_an_approval_action(): void
    {
        [$owner, $approver, $purchaseRequest, $approvalRequest] = $this->createSubmittedRequestForCancellation(
            'CANCEL-AFTER-APPROVAL',
        );
        $approvedLevel = ApprovalRequestLevel::query()->create([
            'approval_request_id' => $approvalRequest->id,
            'level' => 1,
            'approver_user_id' => $approver->id,
            'status' => 'APPROVED',
        ]);
        ApprovalRequestLevel::query()->create([
            'approval_request_id' => $approvalRequest->id,
            'level' => 2,
            'approver_user_id' => $owner->id,
            'status' => 'PENDING',
        ]);
        $approvalRequest->update(['current_level' => 2]);
        ApprovalAction::query()->create([
            'approval_request_id' => $approvalRequest->id,
            'approval_request_level_id' => $approvedLevel->id,
            'user_id' => $approver->id,
            'action' => 'APPROVED',
            'remarks' => 'Level one approved',
        ]);

        $this->actingAs($owner)
            ->post(route('transaction.procurement.purchase-requests.cancel', $purchaseRequest))
            ->assertForbidden();

        $this->assertDatabaseHas('trx_purchase_requests', [
            'id' => $purchaseRequest->id,
            'status' => 'SUBMITTED',
        ]);
        $this->assertDatabaseHas('approval_requests', [
            'id' => $approvalRequest->id,
            'status' => 'PENDING',
            'current_level' => 2,
        ]);
    }

    /**
     * @return array{0: User, 1: User, 2: PurchaseRequest, 3: ApprovalRequest}
     */
    private function createSubmittedRequestForCancellation(string $code): array
    {
        $owner = User::query()->create([
            'name' => 'Cancellation Owner',
            'email' => strtolower($code).'-owner@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $approver = User::query()->create([
            'name' => 'Cancellation Approver',
            'email' => strtolower($code).'-approver@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $company = Company::query()->create([
            'code' => $code.'-COMP',
            'name' => 'Cancellation Company',
            'is_active' => true,
        ]);
        $department = Department::query()->create([
            'company_id' => $company->id,
            'code' => $code.'-DEPT',
            'name' => 'Cancellation Department',
            'is_active' => true,
        ]);
        $workflow = ApprovalWorkflow::query()->create([
            'module' => 'PURCHASE_REQUEST',
            'company_id' => $company->id,
            'department_id' => $department->id,
            'name' => 'Cancellation Workflow',
            'is_active' => true,
        ]);
        $purchaseRequest = PurchaseRequest::query()->create([
            'number' => $code,
            'request_date' => now()->toDateString(),
            'requester_id' => $owner->id,
            'company_id' => $company->id,
            'department_id' => $department->id,
            'purpose' => 'Cancel request test',
            'status' => 'SUBMITTED',
        ]);
        $approvalRequest = ApprovalRequest::query()->create([
            'module' => 'PURCHASE_REQUEST',
            'document_type' => 'PURCHASE_REQUEST',
            'document_id' => $purchaseRequest->id,
            'approval_workflow_id' => $workflow->id,
            'status' => 'PENDING',
            'current_level' => 1,
            'submitted_by' => $owner->id,
            'submitted_at' => now(),
        ]);

        return [$owner, $approver, $purchaseRequest, $approvalRequest];
    }

    private function createPurchaseRequestForIndex(
        User $owner,
        Company $company,
        Department $department,
        string $number,
        string $status,
        Carbon $requestDate,
    ): PurchaseRequest {
        return PurchaseRequest::query()->create([
            'number' => $number,
            'request_date' => $requestDate->toDateString(),
            'requester_id' => $owner->id,
            'company_id' => $company->id,
            'department_id' => $department->id,
            'purpose' => 'Index action test',
            'status' => $status,
        ]);
    }
}
