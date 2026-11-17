<?php

namespace App\Http\Controllers\Approval;

use App\Http\Controllers\Controller;
use App\Models\Administrator\Workflow\ApprovalRequest;
use App\Services\Administrator\Workflow\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalInboxController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvalService
    ) {}

    public function index(Request $request): Response
    {
        $inbox = $this->approvalService->inbox(
            $request->user()
        );

        return Inertia::render('Approval/Inbox/Index', [
            'inbox' => $inbox,
        ]);
    }

    public function approve(
        Request $request,
        ApprovalRequest $approvalRequest
    ): RedirectResponse {
        abort_unless($approvalRequest->module !== 'PURCHASE_REQUEST', 403);

        $this->approvalService->approve(
            $approvalRequest,
            $request->user(),
            $request->input('remarks')
        );

        return back()->with(
            'success',
            'Approval berhasil diproses.'
        );
    }

    public function reject(
        Request $request,
        ApprovalRequest $approvalRequest
    ): RedirectResponse {
        abort_unless($approvalRequest->module !== 'PURCHASE_REQUEST', 403);

        $request->validate([
            'remarks' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        $this->approvalService->reject(
            $approvalRequest,
            $request->user(),
            $request->input('remarks')
        );

        return back()->with(
            'success',
            'Dokumen berhasil ditolak.'
        );
    }
}
