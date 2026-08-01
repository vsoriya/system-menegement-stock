<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $trashed = $request->boolean('trashed') && (bool) $request->user()?->canDelete();

        $customers = Customer::query()
            ->when($trashed, fn ($query) => $query->onlyTrashed())
            ->withCount(['sales as completed_sales_count' => fn ($query) => $query->completed()])
            // Aliased sum of completed invoices. Done in SQL rather than through
            // the total_spent accessor so the list is one query, not one per row.
            ->withSum(['sales as spent_total' => fn ($query) => $query->completed()], 'total')
            ->search($request->string('search')->toString())
            ->when($request->input('active') === '1', fn ($query) => $query->where('is_active', true))
            ->when($request->input('active') === '0', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'filters' => $request->only(['search', 'active']),
            'trashed' => $trashed,
            'trashedCount' => Customer::onlyTrashed()->count(),
        ]);
    }

    public function create(): View
    {
        return view('customers.create', [
            'customer' => new Customer(['is_active' => true]),
        ]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $customer = Customer::query()->create($request->payload());

        // Coming from the till, go straight back to it with the new customer
        // already selected, rather than stranding the cashier on a detail page.
        if ($request->filled('from_pos')) {
            return redirect()
                ->route('pos.index', ['customer' => $customer->id])
                ->with('status', __('app.customer.created_msg', ['name' => $customer->name]));
        }

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', __('app.customer.created_msg', ['name' => $customer->name]));
    }

    public function show(Customer $customer): View
    {
        return view('customers.show', [
            'customer' => $customer,
            'sales' => $customer->sales()
                ->with('cashier')
                ->paginate(15),
        ]);
    }

    public function edit(Customer $customer): View
    {
        return view('customers.edit', [
            'customer' => $customer,
        ]);
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->payload());

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', __('app.customer.updated_msg', ['name' => $customer->name]));
    }

    /**
     * Soft delete. Past invoices still point at this customer, and the Sale
     * relation loads them withTrashed so old receipts keep reading properly.
     */
    public function destroy(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canDelete(), 403);

        $name = $customer->name;
        $customer->delete();

        return redirect()
            ->route('customers.index')
            ->with('status', __('app.customer.deleted_msg', ['name' => $name]));
    }

    public function restore(Request $request, int $customer): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canDelete(), 403);

        $model = Customer::onlyTrashed()->findOrFail($customer);
        $model->restore();

        return redirect()
            ->route('customers.index', ['trashed' => 1])
            ->with('status', __('app.customer.restored_msg', ['name' => $model->name]));
    }
}
