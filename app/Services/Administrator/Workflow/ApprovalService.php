<?php

namespace App\Services\Administrator\Workflow;

use App\Enums\Administrator\Workflow\ApprovalAction;
use App\Enums\Administrator\Workflow\ApprovalStatus;
use App\Models\Administrator\Workflow\ApprovalAction as ApprovalActionModel;
use App\Models\Administrator\Workflow\ApprovalRequest;
use App\Models\Administrator\Workflow\ApprovalRequestLevel;
use App\Models\Administrator\Workflow\ApprovalWorkflow;
use App\Models\Transaction\AssetManagement\AssetMaintenanceRecord;
use App\Models\Transaction\Inventory\InventoryDocument;
use App\Models\Transaction\Inventory\InventoryRequest;
use App\Models\Transaction\Procurement\PurchaseRequest;
use App\Models\User;
use App\Services\DepartmentAccessService;
use App\Services\Transaction\Inventory\InventoryOpeningBalancePostingService;
use App\Services\Transaction\Procurement\PurchaseOrderGenerationService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApprovalService
{
    public function __construct(
        private readonly PurchaseOrderGenerationService $purchaseOrderGenerationService,
        private readonly InventoryOpeningBalancePostingService $inventoryOpeningBalancePostingService,
        private readonly DepartmentAccessService $departmentAccess,
    ) {}

    /**
     * Submit a document into an approval workflow.
     *
     * The workflow configuration is copied into runtime levels
     * so future workflow configuration changes do not affect
     * approvals that are already running.
     */
    public function submit(
        string $module,
        string $documentType,
        string $documentId,
        User $submittedBy,
        string $companyId,
        string $departmentId,
    ): ApprovalRequest {
        return DB::transaction(function () use (
            $module,
            $documentType,
            $documentId,
            $submittedBy,
            $companyId,
            $departmentId,
        ) {
            $workflow = ApprovalWorkflow::query()
                ->with([
                    'levels' => fn ($query) => $query->orderBy('level'),
                ])
                ->where('module', $module)
                ->where('company_id', $companyId)
                ->where('department_id', $departmentId)
                ->where('is_active', true)
                ->first();

            if (! $workflow) {
                throw new RuntimeException(
                    "No active approval workflow found for module [{$module}], ".
                    "company [{$companyId}], ".
                    "department [{$departmentId}]."
                );
            }

            if ($workflow->levels->isEmpty()) {
                throw new RuntimeException(
                    "Approval workflow [{$workflow->name}] has no approval levels."
                );
            }

            if (! in_array($module, ['PURCHASE_REQUEST', 'ASSET_MAINTENANCE'], true) && $workflow->levels->contains(
                fn ($level): bool => (int) $level->approver_user_id === (int) $submittedBy->id,
            )) {
                throw new RuntimeException(
                    'A submitter cannot approve their own document.',
                );
            }

            $expectedLevel = 1;

            foreach ($workflow->levels as $workflowLevel) {
                if ($workflowLevel->level !== $expectedLevel) {
                    throw new RuntimeException(
                        "Approval workflow [{$workflow->name}] has invalid level configuration."
                    );
                }

                if (! $workflowLevel->approver_user_id) {
                    throw new RuntimeException(
                        "Approval workflow [{$workflow->name}] has an invalid approver."
                    );
                }

                $expectedLevel++;
            }

            $approvalRequest = ApprovalRequest::create([
                'module' => $module,
                'document_type' => $documentType,
                'document_id' => $documentId,
                'approval_workflow_id' => $workflow->id,
                'status' => ApprovalStatus::PENDING,
                'current_level' => 1,
                'submitted_by' => $submittedBy->id,
                'submitted_at' => now(),
            ]);

            foreach ($workflow->levels as $workflowLevel) {
                ApprovalRequestLevel::create([
                    'approval_request_id' => $approvalRequest->id,
                    'level' => $workflowLevel->level,
                    'approver_user_id' => $workflowLevel->approver_user_id,
                    'status' => $workflowLevel->level === 1
                        ? ApprovalStatus::PENDING
                        : ApprovalStatus::WAITING,
                ]);
            }

            return $approvalRequest->load([
                'workflow',
                'levels.approver',
                'submitter',
            ]);
        });
    }

    /**
     * Approve the current level.
     */
    public function approve(
        ApprovalRequest $approvalRequest,
        User $actor,
        ?string $remarks = null
    ): ApprovalRequest {
        return $this->act(
            $approvalRequest,
            $actor,
            ApprovalAction::APPROVED,
            $remarks
        );
    }

    /**
     * Reject the current level.
     */
    public function reject(
        ApprovalRequest $approvalRequest,
        User $actor,
        ?string $remarks = null
    ): ApprovalRequest {
        return $this->act(
            $approvalRequest,
            $actor,
            ApprovalAction::REJECTED,
            $remarks
        );
    }

    /**
     * Record a manually completed approval for every configured workflow level.
     */
    public function manuallyApprovePurchaseRequest(
        ApprovalRequest $approvalRequest,
        User $actor,
        string $documentPath,
        string $remarks,
    ): ApprovalRequest {
        return DB::transaction(function () use (
            $approvalRequest,
            $actor,
            $documentPath,
            $remarks,
        ): ApprovalRequest {
            $request = ApprovalRequest::query()
                ->lockForUpdate()
                ->findOrFail($approvalRequest->id);

            if (
                $request->module !== 'PURCHASE_REQUEST'
                || $request->document_type !== 'PURCHASE_REQUEST'
            ) {
                throw new RuntimeException(
                    'Manual approval is only available for Purchase Requests.',
                );
            }

            $this->authorizeDocumentDepartment($request, $actor);

            if ($request->status !== ApprovalStatus::PENDING) {
                throw new RuntimeException(
                    'This Purchase Request has already completed its approval workflow.',
                );
            }

            $purchaseRequest = PurchaseRequest::query()
                ->lockForUpdate()
                ->findOrFail($request->document_id);

            if (
                $purchaseRequest->status !== 'SUBMITTED'
                || ($purchaseRequest->requester_id !== $actor->id
                    && ! $actor->hasGlobalDepartmentAccess())
            ) {
                throw new RuntimeException(
                    'Only the Purchase Request owner or an Administrator can mark manual approval.',
                );
            }

            $levels = ApprovalRequestLevel::query()
                ->where('approval_request_id', $request->id)
                ->orderBy('level')
                ->lockForUpdate()
                ->get();

            if ($levels->isEmpty()) {
                throw new RuntimeException(
                    'This Purchase Request has no approval levels.',
                );
            }

            $approvedAt = now();

            foreach ($levels as $level) {
                $level->update([
                    'status' => ApprovalStatus::APPROVED,
                    'acted_at' => $approvedAt,
                    'remarks' => $remarks,
                ]);

                ApprovalActionModel::create([
                    'approval_request_id' => $request->id,
                    'approval_request_level_id' => $level->id,
                    'user_id' => $actor->id,
                    'action' => ApprovalAction::APPROVED,
                    'remarks' => $remarks,
                ]);
            }

            $request->update([
                'status' => ApprovalStatus::APPROVED,
                'current_level' => null,
                'completed_at' => $approvedAt,
                'manual_approval_document_path' => $documentPath,
                'manual_approval_remarks' => $remarks,
                'manual_approved_by' => $actor->id,
                'manual_approved_at' => $approvedAt,
            ]);

            $this->syncPurchaseRequestStatus(
                $request,
                ApprovalStatus::APPROVED,
            );

            return $request->fresh([
                'workflow',
                'levels.approver',
                'submitter',
                'manualApprover',
                'actions.user',
            ]);
        });
    }

    /**
     * Record a manually completed approval for an Asset Maintenance request.
     */
    public function manuallyApproveAssetMaintenance(
        ApprovalRequest $approvalRequest,
        User $actor,
        string $documentPath,
        string $remarks,
    ): ApprovalRequest {
        return DB::transaction(function () use ($approvalRequest, $actor, $documentPath, $remarks): ApprovalRequest {
            $request = ApprovalRequest::query()->lockForUpdate()->findOrFail($approvalRequest->id);

            if ($request->module !== 'ASSET_MAINTENANCE' || $request->document_type !== 'ASSET_MAINTENANCE') {
                throw new RuntimeException('Manual approval is only available for Asset Maintenance requests.');
            }

            if ($request->status !== ApprovalStatus::PENDING) {
                throw new RuntimeException('This Asset Maintenance request has already completed its approval workflow.');
            }

            $this->authorizeDocumentDepartment($request, $actor);
            $maintenance = AssetMaintenanceRecord::query()->lockForUpdate()->findOrFail($request->document_id);

            if (
                $maintenance->status !== AssetMaintenanceRecord::STATUS_WAITING_APPROVAL
                || ($maintenance->requested_by !== $actor->id && ! $actor->hasGlobalDepartmentAccess())
            ) {
                throw new RuntimeException('Only the Asset Maintenance requester or an Administrator can mark manual approval.');
            }

            $levels = ApprovalRequestLevel::query()
                ->where('approval_request_id', $request->id)
                ->orderBy('level')
                ->lockForUpdate()
                ->get();

            if ($levels->isEmpty()) {
                throw new RuntimeException('This Asset Maintenance request has no approval levels.');
            }

            $approvedAt = now();

            foreach ($levels as $level) {
                $level->update([
                    'status' => ApprovalStatus::APPROVED,
                    'acted_at' => $approvedAt,
                    'remarks' => $remarks,
                ]);

                ApprovalActionModel::create([
                    'approval_request_id' => $request->id,
                    'approval_request_level_id' => $level->id,
                    'user_id' => $actor->id,
                    'action' => ApprovalAction::APPROVED,
                    'remarks' => $remarks,
                ]);
            }

            $request->update([
                'status' => ApprovalStatus::APPROVED,
                'current_level' => null,
                'completed_at' => $approvedAt,
                'manual_approval_document_path' => $documentPath,
                'manual_approval_remarks' => $remarks,
                'manual_approved_by' => $actor->id,
                'manual_approved_at' => $approvedAt,
            ]);

            $maintenance->update([
                'status' => $maintenance->requires_spare_parts
                    ? AssetMaintenanceRecord::STATUS_WAITING_PARTS
                    : AssetMaintenanceRecord::STATUS_READY_TO_START,
            ]);

            return $request->fresh([
                'workflow',
                'levels.approver',
                'submitter',
                'manualApprover',
                'actions.user',
            ]);
        });
    }

    /**
     * Process an approval action.
     */
    protected function act(
        ApprovalRequest $approvalRequest,
        User $actor,
        ApprovalAction $action,
        ?string $remarks = null
    ): ApprovalRequest {
        return DB::transaction(function () use (
            $approvalRequest,
            $actor,
            $action,
            $remarks
        ) {
            $request = ApprovalRequest::query()
                ->lockForUpdate()
                ->findOrFail($approvalRequest->id);

            $this->authorizeDocumentDepartment($request, $actor);

            if ($request->status !== ApprovalStatus::PENDING) {
                throw new RuntimeException(
                    'This approval request is no longer awaiting approval.'
                );
            }

            if (
                $request->module === 'PURCHASE_REQUEST'
                && $request->document_type === 'PURCHASE_REQUEST'
            ) {
                throw new RuntimeException(
                    'Purchase Requests must be approved manually from the request detail.',
                );
            }

            if (
                $request->module === 'ASSET_MAINTENANCE'
                && $request->document_type === 'ASSET_MAINTENANCE'
            ) {
                throw new RuntimeException(
                    'Asset Maintenance requests must be approved manually from the request detail.',
                );
            }

            if (! $request->current_level) {
                throw new RuntimeException(
                    'This approval request has no current approval level.'
                );
            }

            $currentLevel = ApprovalRequestLevel::query()
                ->lockForUpdate()
                ->where('approval_request_id', $request->id)
                ->where('level', $request->current_level)
                ->first();

            if (! $currentLevel) {
                throw new RuntimeException(
                    'Current approval level was not found.'
                );
            }

            if ($currentLevel->status !== ApprovalStatus::PENDING) {
                throw new RuntimeException(
                    'Current approval level is no longer pending.'
                );
            }

            if ((int) $currentLevel->approver_user_id !== (int) $actor->id) {
                throw new RuntimeException(
                    'You are not the assigned approver for this level.'
                );
            }

            $currentLevel->update([
                'status' => $action === ApprovalAction::APPROVED
                    ? ApprovalStatus::APPROVED
                    : ApprovalStatus::REJECTED,
                'acted_at' => now(),
                'remarks' => $remarks,
            ]);

            ApprovalActionModel::create([
                'approval_request_id' => $request->id,
                'approval_request_level_id' => $currentLevel->id,
                'user_id' => $actor->id,
                'action' => $action,
                'remarks' => $remarks,
            ]);

            if ($action === ApprovalAction::REJECTED) {
                $request->update([
                    'status' => ApprovalStatus::REJECTED,
                    'current_level' => null,
                    'completed_at' => now(),
                ]);

                $this->syncPurchaseRequestStatus(
                    $request,
                    ApprovalStatus::REJECTED,
                );
                $this->syncInventoryRequestStatus($request, ApprovalStatus::REJECTED);
                $this->syncInventoryOpeningBalance($request, ApprovalStatus::REJECTED, $actor);

                return $request->fresh([
                    'workflow',
                    'levels.approver',
                    'submitter',
                    'actions.user',
                ]);
            }

            $nextLevel = ApprovalRequestLevel::query()
                ->where('approval_request_id', $request->id)
                ->where('level', '>', $request->current_level)
                ->orderBy('level')
                ->first();

            if (! $nextLevel) {
                $request->update([
                    'status' => ApprovalStatus::APPROVED,
                    'current_level' => null,
                    'completed_at' => now(),
                ]);

                $this->syncPurchaseRequestStatus(
                    $request,
                    ApprovalStatus::APPROVED,
                );
                $this->syncInventoryRequestStatus($request, ApprovalStatus::APPROVED);
                $this->syncInventoryOpeningBalance($request, ApprovalStatus::APPROVED, $actor);

                return $request->fresh([
                    'workflow',
                    'levels.approver',
                    'submitter',
                    'actions.user',
                ]);
            }

            $nextLevel->update([
                'status' => ApprovalStatus::PENDING,
            ]);

            $request->update([
                'current_level' => $nextLevel->level,
            ]);

            return $request->fresh([
                'workflow',
                'levels.approver',
                'submitter',
                'actions.user',
            ]);
        });
    }

    private function syncPurchaseRequestStatus(
        ApprovalRequest $approvalRequest,
        ApprovalStatus $status,
    ): void {
        if (
            $approvalRequest->module !== 'PURCHASE_REQUEST'
            || $approvalRequest->document_type !== 'PURCHASE_REQUEST'
        ) {
            return;
        }

        $purchaseRequest = PurchaseRequest::query()
            ->lockForUpdate()
            ->find($approvalRequest->document_id);

        if (! $purchaseRequest || $purchaseRequest->status !== 'SUBMITTED') {
            return;
        }

        $purchaseRequest->update(match ($status) {
            ApprovalStatus::APPROVED => [
                'status' => 'APPROVED',
                'approved_at' => $approvalRequest->completed_at ?? now(),
            ],
            ApprovalStatus::REJECTED => [
                'status' => 'REJECTED',
                'rejected_at' => $approvalRequest->completed_at ?? now(),
            ],
            default => [],
        });

        if ($status === ApprovalStatus::APPROVED) {
            $this->purchaseOrderGenerationService->generateForApprovedPurchaseRequest($purchaseRequest);
        }
    }

    private function syncInventoryRequestStatus(
        ApprovalRequest $approvalRequest,
        ApprovalStatus $status,
    ): void {
        if (
            $approvalRequest->module !== 'INVENTORY_REQUEST'
            || $approvalRequest->document_type !== 'INVENTORY_REQUEST'
        ) {
            return;
        }

        $inventoryRequest = InventoryRequest::query()
            ->lockForUpdate()
            ->find($approvalRequest->document_id);

        if (! $inventoryRequest || $inventoryRequest->status !== InventoryRequest::STATUS_PENDING_APPROVAL) {
            return;
        }

        $inventoryRequest->update([
            'status' => $status === ApprovalStatus::APPROVED
                ? InventoryRequest::STATUS_APPROVED
                : InventoryRequest::STATUS_REJECTED,
        ]);
    }

    private function syncInventoryOpeningBalance(
        ApprovalRequest $approvalRequest,
        ApprovalStatus $status,
        User $actor,
    ): void {
        if (
            $approvalRequest->module !== 'INVENTORY_OPENING_BALANCE'
            || $approvalRequest->document_type !== 'INVENTORY_OPENING_BALANCE'
        ) {
            return;
        }

        $document = InventoryDocument::query()
            ->lockForUpdate()
            ->find($approvalRequest->document_id);

        if (
            $document === null
            || $document->document_type !== InventoryDocument::TYPE_OPENING_BALANCE
            || $document->status !== InventoryDocument::STATUS_PENDING_APPROVAL
        ) {
            return;
        }

        if ($status === ApprovalStatus::REJECTED) {
            $document->update(['status' => InventoryDocument::STATUS_REJECTED]);

            return;
        }

        if ($status === ApprovalStatus::APPROVED) {
            $this->inventoryOpeningBalancePostingService->post($document, $actor);
        }
    }

    /**
     * Get the active approval inbox for a user.
     *
     * Only the current PENDING level is returned.
     * WAITING levels never appear in the inbox.
     */
    public function inbox(User $user)
    {
        $departmentIds = $this->departmentAccess->departmentIds($user);

        return ApprovalRequestLevel::query()
            ->with([
                'request.workflow',
                'request.submitter',
                'request.levels.approver',
                'approver',
            ])
            ->where('approver_user_id', $user->id)
            ->where('status', ApprovalStatus::PENDING)
            ->whereHas('request', function ($query) {
                $query
                    ->where('status', ApprovalStatus::PENDING)
                    ->whereNotIn('module', ['PURCHASE_REQUEST', 'ASSET_MAINTENANCE']);
            })
            ->whereHas('request', function ($query) use ($departmentIds): void {
                $query->where(function ($query) use ($departmentIds): void {
                    $query->where(function ($query) use ($departmentIds): void {
                        $query->where('module', 'INVENTORY_REQUEST')
                            ->where('document_type', 'INVENTORY_REQUEST')
                            ->whereIn('document_id', InventoryRequest::query()
                                ->whereIn('department_id', $departmentIds)
                                ->select('id'));
                    })->orWhere(function ($query) use ($departmentIds): void {
                        $query->where('module', 'INVENTORY_OPENING_BALANCE')
                            ->where('document_type', 'INVENTORY_OPENING_BALANCE')
                            ->whereIn('document_id', InventoryDocument::query()
                                ->where('document_type', InventoryDocument::TYPE_OPENING_BALANCE)
                                ->whereIn('department_id', $departmentIds)
                                ->select('id'));
                    });
                });
            })
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Check whether the supplied user is the current approver.
     */
    public function canAct(
        ApprovalRequest $approvalRequest,
        User $user
    ): bool {
        if ($approvalRequest->status !== ApprovalStatus::PENDING) {
            return false;
        }

        if (! $approvalRequest->current_level) {
            return false;
        }

        return ApprovalRequestLevel::query()
            ->where('approval_request_id', $approvalRequest->id)
            ->where('level', $approvalRequest->current_level)
            ->where('approver_user_id', $user->id)
            ->where('status', ApprovalStatus::PENDING)
            ->exists();
    }

    private function authorizeDocumentDepartment(ApprovalRequest $approvalRequest, User $actor): void
    {
        $departmentId = match ($approvalRequest->module) {
            'PURCHASE_REQUEST' => PurchaseRequest::query()->findOrFail($approvalRequest->document_id)->department_id,
            'INVENTORY_REQUEST' => InventoryRequest::query()->findOrFail($approvalRequest->document_id)->department_id,
            'INVENTORY_OPENING_BALANCE' => InventoryDocument::query()->findOrFail($approvalRequest->document_id)->department_id,
            'ASSET_MAINTENANCE' => AssetMaintenanceRecord::query()
                ->with('asset:id,department_id')
                ->findOrFail($approvalRequest->document_id)
                ->asset?->department_id,
            default => null,
        };

        abort_unless($this->departmentAccess->canAccess($actor, $departmentId), 403);
    }
}
