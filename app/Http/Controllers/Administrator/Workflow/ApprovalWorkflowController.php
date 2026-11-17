<?php

namespace App\Http\Controllers\Administrator\Workflow;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administrator\Workflow\ApprovalWorkflowRequest;
use App\Models\Administrator\Workflow\ApprovalWorkflow;
use App\Models\Master\Organization\Company;
use App\Models\Master\Organization\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalWorkflowController extends Controller
{
    /**
     * Display workflow configuration.
     */
    public function index(Request $request): Response
    {
        // Ambil per_page dari request, default 5 data per halaman
        $perPage = (int) $request->input('per_page', 5);

        $workflows = ApprovalWorkflow::query()
            ->with([
                'company:id,name',
                'department:id,name',
                'levels' => fn ($query) => $query
                    ->with('approver:id,name')
                    ->orderBy('level'),
            ])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'ilike', "%{$search}%")
                        ->orWhere('module', 'ilike', "%{$search}%")
                        ->orWhereHas('company', function ($query) use ($search) {
                            $query->where('name', 'ilike', "%{$search}%");
                        })
                        ->orWhereHas('department', function ($query) use ($search) {
                            $query->where('name', 'ilike', "%{$search}%");
                        });
                });
            })
            ->when(
                $request->filled('module'),
                fn ($query) => $query->where('module', $request->module)
            )
            ->when(
                $request->filled('is_active'),
                fn ($query) => $query->where(
                    'is_active',
                    filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)
                )
            )
            ->latest()
            ->paginate($perPage) // Menggunakan variabel $perPage
            ->withQueryString();

        return Inertia::render('Administrator/Workflow/Approval/Index', [
            'workflows' => $workflows,
            'filters' => [
                'search' => $request->search,
                'module' => $request->module,
                'is_active' => $request->is_active,
                'per_page' => $perPage, // Mengirimkan per_page ke frontend
            ],
        ]);
    }

    /**
     * Show workflow creation form.
     */
    public function create(): Response
    {
        return Inertia::render('Administrator/Workflow/Approval/Create', [
            'companies' => Company::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get([
                    'id',
                    'code',
                    'name',
                ])
                ->values()
                ->toArray(),

            'departments' => Department::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get([
                    'id',
                    'company_id',
                    'code',
                    'name',
                ])
                ->values()
                ->toArray(),

            'users' => User::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->with([
                    'company:id,name',
                    'department:id,name',
                    'position:id,name',
                ])
                ->get([
                    'id',
                    'name',
                    'email',
                    'company_id',
                    'department_id',
                    'position_id',
                ])
                ->values()
                ->toArray(),

            'modules' => $this->modules(),
        ]);
    }

    /**
     * Store workflow configuration.
     */
    public function store(
        ApprovalWorkflowRequest $request
    ): RedirectResponse {
        DB::transaction(function () use ($request) {
            $workflow = ApprovalWorkflow::create([
                'module' => $request->validated('module'),
                'company_id' => $request->validated('company_id'),
                'department_id' => $request->validated('department_id'),
                'name' => $request->validated('name'),
                'is_active' => $request->boolean('is_active'),
            ]);

            foreach ($request->validated('levels') as $level) {
                $workflow->levels()->create([
                    'level' => $level['level'],
                    'approver_user_id' => $level['approver_user_id'],
                ]);
            }
        });

        return redirect()
            ->route('administrator.approval-workflows.index')
            ->with('success', 'Approval workflow was created successfully.');
    }

    /**
     * Display workflow detail.
     */
    public function show(ApprovalWorkflow $approvalWorkflow): Response
    {
        $approvalWorkflow->load([
            'company:id,code,name',
            'department:id,code,name',
            'levels' => fn ($query) => $query
                ->with([
                    'approver' => fn ($query) => $query->with([
                        'company:id,name',
                        'department:id,name',
                        'position:id,name',
                    ]),
                ])
                ->orderBy('level'),
        ]);

        return Inertia::render(
            'Administrator/Workflow/Approval/Show',
            [
                'workflow' => $approvalWorkflow,
            ]
        );
    }

    /**
     * Show workflow edit form.
     */
    public function edit(
        ApprovalWorkflow $approvalWorkflow
    ): Response {
        $approvalWorkflow->load([
            'levels' => fn ($query) => $query->orderBy('level'),
        ]);

        return Inertia::render(
            'Administrator/Workflow/Approval/Edit',
            [
                'workflow' => $approvalWorkflow,

                'companies' => Company::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get([
                        'id',
                        'code',
                        'name',
                    ]),

                'departments' => Department::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get([
                        'id',
                        'company_id',
                        'code',
                        'name',
                    ]),

                'users' => User::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'email',
                        'company_id',
                        'department_id',
                        'position_id',
                    ])
                    ->load([
                        'company:id,name',
                        'department:id,name',
                        'position:id,name',
                    ]),

                'modules' => $this->modules(),
            ]
        );
    }

    /**
     * Update workflow configuration.
     */
    public function update(
        ApprovalWorkflowRequest $request,
        ApprovalWorkflow $approvalWorkflow
    ): RedirectResponse {
        DB::transaction(function () use (
            $request,
            $approvalWorkflow
        ) {
            $approvalWorkflow->update([
                'module' => $request->validated('module'),
                'company_id' => $request->validated('company_id'),
                'department_id' => $request->validated('department_id'),
                'name' => $request->validated('name'),
                'is_active' => $request->boolean('is_active'),
            ]);

            $approvalWorkflow->levels()->get()->each->delete();

            foreach ($request->validated('levels') as $level) {
                $approvalWorkflow->levels()->create([
                    'level' => $level['level'],
                    'approver_user_id' => $level['approver_user_id'],
                ]);
            }
        });

        return redirect()
            ->route('administrator.approval-workflows.index')
            ->with('success', 'Approval workflow was updated successfully.');
    }

    /**
     * Delete workflow configuration.
     */
    public function destroy(
        ApprovalWorkflow $approvalWorkflow
    ): RedirectResponse {
        if (
            $approvalWorkflow->approvalRequests()->exists()
        ) {
            return back()->with(
                'error',
                'This workflow cannot be deleted because it is already used by an approval transaction.'
            );
        }

        $approvalWorkflow->delete();

        return redirect()
            ->route('administrator.approval-workflows.index')
            ->with('success', 'Approval workflow was deleted successfully.');
    }

    /**
     * Available workflow modules.
     */
    private function modules(): array
    {
        return [
            [
                'value' => 'PURCHASE_REQUEST',
                'label' => 'Purchase Request',
            ],
            [
                'value' => 'INVENTORY_REQUEST',
                'label' => 'Inventory Request',
            ],
            [
                'value' => 'INVENTORY_OPENING_BALANCE',
                'label' => 'Inventory Opening Balance',
            ],
            [
                'value' => 'ASSET_TRANSFER',
                'label' => 'Asset Transfer',
            ],
            [
                'value' => 'ASSET_DISPOSAL',
                'label' => 'Asset Disposal',
            ],
            [
                'value' => 'ASSET_ADJUSTMENT',
                'label' => 'Asset Adjustment',
            ],
        ];
    }
}
