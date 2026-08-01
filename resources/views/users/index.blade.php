<x-app-layout :title="__('app.user.title')">
    <x-slot:actions>
        <x-button :href="route('users.create')" size="sm">{{ __('app.user.add') }}</x-button>
    </x-slot:actions>

    <div class="space-y-5">
        <x-card>
            <form method="GET" action="{{ route('users.index') }}" class="flex flex-wrap items-end gap-3 p-4">
                <x-field
                    :label="__('app.common.search')"
                    name="search"
                    :value="$filters['search'] ?? null"
                    :placeholder="__('app.user.search_placeholder')"
                    class="min-w-56 flex-1"
                />
                <x-field
                    :label="__('app.user.role')"
                    name="role"
                    type="select"
                    :value="$filters['role'] ?? null"
                    :placeholder="__('app.common.all')"
                    :options="$roles"
                    class="w-44"
                />
                <x-button type="submit" size="sm">{{ __('app.common.search') }}</x-button>
                <x-button :href="route('users.index')" variant="secondary" size="sm">{{ __('app.common.reset') }}</x-button>
            </form>
        </x-card>

        <x-card>
            @if ($users->isEmpty())
                <x-empty-state :title="__('app.user.none_yet')" :description="__('app.user.none_yet_sub')">
                    <x-button :href="route('users.create')" size="sm">{{ __('app.user.add') }}</x-button>
                </x-empty-state>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-line text-sm">
                        <thead class="bg-surface-sunken/80 text-left text-[0.6875rem] font-semibold uppercase tracking-wider text-ink-muted">
                            <tr>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.user.one') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.user.role') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">{{ __('app.user.movements_count') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('app.common.status') }}</th>
                                <th scope="col" class="px-4 py-2.5 text-right">
                                    <span class="sr-only">{{ __('app.common.actions') }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($users as $index => $row)
                                @php
                                    $isSelf = $row->is(auth()->user());
                                    $roleClasses = $row->isAdmin()
                                        ? 'bg-brand-50 dark:bg-brand-500/20 text-brand-700 dark:text-brand-300 ring-brand-600/20 dark:ring-brand-400/30'
                                        : 'bg-surface-sunken text-ink-muted ring-line';
                                @endphp
                                <tr class="animate-fade stagger row-hover" style="--d: {{ $index * 35 }}ms">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <span
                                                class="flex size-8 shrink-0 items-center justify-center rounded-full bg-linear-to-br from-brand-400 to-brand-600 text-[0.625rem] font-semibold text-white"
                                                aria-hidden="true"
                                            >{{ $row->initials() }}</span>
                                            <div class="min-w-0">
                                                <p class="truncate font-medium text-ink">
                                                    {{ $row->name }}
                                                    @if ($isSelf)
                                                        <span class="text-xs font-normal text-ink-muted">({{ __('app.user.you') }})</span>
                                                    @endif
                                                </p>
                                                <p class="truncate text-xs text-ink-muted">{{ $row->email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-badge :classes="$roleClasses">{{ $row->role->label() }}</x-badge>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-ink-muted">
                                        @qty($row->stock_movements_count)
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($row->is_active)
                                            <x-badge classes="bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 ring-emerald-600/20 dark:ring-emerald-400/30">
                                                {{ __('app.common.active') }}
                                            </x-badge>
                                        @else
                                            <x-badge classes="bg-surface-sunken text-ink-muted ring-line">
                                                {{ __('app.common.inactive') }}
                                            </x-badge>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            <x-button :href="route('users.edit', $row)" variant="ghost" size="sm">
                                                {{ __('app.common.edit') }}
                                            </x-button>

                                            @unless ($isSelf)
                                                <form
                                                    method="POST"
                                                    action="{{ route('users.destroy', $row) }}"
                                                    onsubmit="return confirm(@js(__('app.common.confirm_delete', ['name' => $row->name])));"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-button
                                                        type="submit"
                                                        variant="ghost"
                                                        size="sm"
                                                        class="text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/15"
                                                    >
                                                        {{ __('app.common.delete') }}
                                                    </x-button>
                                                </form>
                                            @endunless
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($users->hasPages())
                    <div class="border-t border-line px-4 py-3">
                        {{ $users->links() }}
                    </div>
                @endif
            @endif
        </x-card>
    </div>
</x-app-layout>
