<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administrator\NumberingDocumentRequest;
use App\Models\NumberingDocument;
use App\Models\NumberingModule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class NumberingDocumentController extends Controller
{
    public function index(Request $request): Response
    {
        if (! auth()->user()?->hasGlobalDepartmentAccess()) {
            Gate::authorize('numbering.document.view');
        }

        $perPage = (int) $request->input('per_page', 5);
        if (! in_array($perPage, [5, 15, 25, 50, 100], true)) {
            $perPage = 5;
        }

        $search = trim((string) $request->input('search', ''));
        $status = $request->input('is_active');

        $documents = NumberingDocument::query()
            ->with('module')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'ilike', "%{$search}%")
                        ->orWhere('code', 'ilike', "%{$search}%")
                        ->orWhere('pattern', 'ilike', "%{$search}%");
                });
            })
            ->when(
                in_array($status, ['0', '1'], true),
                fn ($query) => $query->where('is_active', $status === '1'),
            )
            ->orderBy('name')
            ->orderBy('code')
            ->paginate($perPage)
            ->withQueryString();

        $documents->through(function (NumberingDocument $document): array {
            return [
                ...$document->toArray(),
                'module' => $document->module?->only([
                    'code',
                    'name',
                    'category',
                    'is_active',
                ]),
            ];
        });

        return Inertia::render('Administrator/Numbering/Index', [
            'documents' => $documents,
            'filters' => [
                'search' => $search,
                'is_active' => $status,
                'per_page' => $perPage,
            ],
            'placeholders' => NumberingDocument::SUPPORTED_PLACEHOLDERS,
            'modules' => NumberingModule::query()
                ->where('is_active', true)
                ->orderBy('category')
                ->orderBy('name')
                ->get([
                    'code',
                    'name',
                    'category',
                ])
                ->keyBy('code'),
        ]);
    }

    public function store(
        NumberingDocumentRequest $request,
    ): RedirectResponse {
        NumberingDocument::query()->create($request->validated());

        return redirect()
            ->route('administrator.numbering-documents.index')
            ->with('success', 'Document numbering was created successfully.');
    }

    public function update(
        NumberingDocumentRequest $request,
        NumberingDocument $numberingDocument,
    ): RedirectResponse {
        $numberingDocument->update($request->safe()->only([
            'name',
            'pattern',
            'sequence_length',
            'sequence_start',
            'reset_period',
            'is_active',
        ]));

        return redirect()
            ->route('administrator.numbering-documents.index')
            ->with('success', 'Document numbering was updated successfully.');
    }
}
