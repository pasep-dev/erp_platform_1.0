<?php

namespace App\Http\Controllers\Master\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\Procurement\PaymentTermRequest;
use App\Models\Master\Procurement\PaymentTerm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentTermController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $paymentTerms = PaymentTerm::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('code', 'ilike', "%{$search}%")
                        ->orWhere('name', 'ilike', "%{$search}%")
                        ->orWhere(
                            'description',
                            'ilike',
                            "%{$search}%",
                        );
                });
            })
            ->orderBy('due_days')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render(
            'Master/Procurement/PaymentTerms/Index',
            [
                'paymentTerms' => $paymentTerms,
                'filters' => [
                    'search' => $search,
                ],
            ],
        );
    }

    public function create(): Response
    {
        return Inertia::render(
            'Master/Procurement/PaymentTerms/Create',
        );
    }

    public function store(
        PaymentTermRequest $request,
    ): RedirectResponse {
        PaymentTerm::create($request->validated());

        return to_route('master.procurement.payment-terms.index')
            ->with('success', 'Payment Term was created successfully.');
    }

    public function edit(
        PaymentTerm $paymentTerm,
    ): Response {
        return Inertia::render(
            'Master/Procurement/PaymentTerms/Edit',
            [
                'paymentTerm' => $paymentTerm,
            ],
        );
    }

    public function update(
        PaymentTermRequest $request,
        PaymentTerm $paymentTerm,
    ): RedirectResponse {
        $paymentTerm->update($request->validated());

        return to_route('master.procurement.payment-terms.index')
            ->with('success', 'Payment Term was updated successfully.');
    }

    public function destroy(
        PaymentTerm $paymentTerm,
    ): RedirectResponse {
        $paymentTerm->delete();

        return to_route('master.procurement.payment-terms.index')
            ->with('success', 'Payment Term was deleted successfully.');
    }
}
