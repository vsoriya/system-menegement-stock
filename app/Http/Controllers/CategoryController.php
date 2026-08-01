<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $trashed = $request->boolean('trashed') && (bool) $request->user()?->canDelete();

        $categories = Category::query()
            ->when($trashed, fn ($query) => $query->onlyTrashed())
            ->withCount('products')
            ->withSum('products as units_on_hand', 'quantity')
            ->search($request->string('search')->toString())
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('categories.index', [
            'categories' => $categories,
            'filters' => $request->only('search'),
            'trashed' => $trashed,
            'trashedCount' => Category::onlyTrashed()->count(),
        ]);
    }

    public function create(): View
    {
        $this->authorizeManage();

        return view('categories.create', [
            'category' => new Category(['is_active' => true]),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = Category::query()->create($request->payload());

        return redirect()
            ->route('categories.index')
            ->with('status', __('app.category.created_msg', ['name' => $category->name]));
    }

    public function edit(Category $category): View
    {
        $this->authorizeManage();

        return view('categories.edit', [
            'category' => $category,
        ]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->payload());

        return redirect()
            ->route('categories.index')
            ->with('status', __('app.category.updated_msg', ['name' => $category->name]));
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canDelete(), 403);

        $name = $category->name;

        // Products are kept; the foreign key is set to null by the schema.
        $category->delete();

        return redirect()
            ->route('categories.index')
            ->with('status', __('app.category.deleted_msg', ['name' => $name]));
    }


    public function restore(Request $request, int $category): RedirectResponse
    {
        abort_unless((bool) $request->user()?->canDelete(), 403);

        $model = Category::onlyTrashed()->findOrFail($category);
        $model->restore();

        return redirect()
            ->route('categories.index', ['trashed' => 1])
            ->with('status', __('app.category.restored_msg', ['name' => $model->name]));
    }

    protected function authorizeManage(): void
    {
        abort_unless((bool) request()->user()?->canManageCatalog(), 403);
    }
}
