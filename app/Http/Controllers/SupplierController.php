<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $trashed = $request->boolean('trashed') && (bool) $request->user()?->canDelete();

        $suppliers = Supplier::query()
            ->when($trashed, fn ($query) => $query->onlyTrashed())
            ->withCount('products')
            ->withSum('products as units_on_hand', 'quantity')
            ->search($request->string('search')->toString())
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('suppliers.index', [
            'suppliers' => $suppliers,
            'filters' => $request->only('search'),
            'trashed' => $trashed,
            'trashedCount' => Supplier::onlyTrashed()->count(),
        ]);
    }

    public function create(): View
    {
        $this->authorizeManage();

        return view('suppliers.create', [
            'supplier' => new Supplier(['is_active' => true]),
        ]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::query()->create($request->payload());

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('status', __('app.supplier.created_msg', ['name' => $supplier->name]));
    }

    public function show(Supplier $supplier): View
    {
        return view('suppliers.show', [
            'supplier' => $supplier,
            'products' => $supplier->products()
                ->with('category')
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function edit(Supplier $supplier): View
    {
        $this->authorizeManage();

        return view('suppliers.edit', [
            'supplier' => $supplier,
        ]);
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->payload());

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('status', __('app.supplier.updated_msg', ['name' => $supplier->name]));
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canDelete(), 403);

        $name = $supplier->name;

        // Products are kept; the foreign key is set to null by the schema.
        $supplier->delete();

        return redirect()
            ->route('suppliers.index')
            ->with('status', __('app.supplier.deleted_msg', ['name' => $name]));
    }


    public function restore(Request $request, int $supplier): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canDelete(), 403);

        $model = Supplier::onlyTrashed()->findOrFail($supplier);
        $model->restore();

        return redirect()
            ->route('suppliers.index', ['trashed' => 1])
            ->with('status', __('app.supplier.restored_msg', ['name' => $model->name]));
    }

    protected function authorizeManage(): void
    {
        abort_unless((bool) request()->user()?->canManageCatalog(), 403);
    }
}
