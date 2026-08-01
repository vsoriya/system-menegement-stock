<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin();

        $search = $request->string('search')->toString();

        $users = User::query()
            ->withCount('stockMovements')
            ->when(filled($search), function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('role'), fn (Builder $query) => $query->where('role', $request->string('role')->toString()))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'roles' => UserRole::options(),
            'filters' => $request->only(['search', 'role']),
        ]);
    }

    public function create(): View
    {
        $this->authorizeAdmin();

        return view('users.create', [
            'user' => new User(['role' => UserRole::Staff, 'is_active' => true]),
            'roles' => UserRole::options(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::query()->create($request->payload());

        return redirect()
            ->route('users.index')
            ->with('status', __('app.user.created_msg', ['name' => $user->name]));
    }

    public function edit(User $user): View
    {
        $this->authorizeAdmin();

        return view('users.edit', [
            'user' => $user,
            'roles' => UserRole::options(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $user->update($request->payload());

        return redirect()
            ->route('users.index')
            ->with('status', __('app.user.updated_msg', ['name' => $user->name]));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin();

        // Deleting yourself would leave the session pointing at nothing, and
        // could remove the last administrator.
        if ($user->is($request->user())) {
            return redirect()
                ->route('users.index')
                ->with('error', __('app.user.cannot_delete_self'));
        }

        $name = $user->name;

        // Movements keep their history; the foreign key is nulled by the schema.
        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('status', __('app.user.deleted_msg', ['name' => $name]));
    }

    protected function authorizeAdmin(): void
    {
        abort_unless((bool) request()->user()?->isAdmin(), 403);
    }
}
