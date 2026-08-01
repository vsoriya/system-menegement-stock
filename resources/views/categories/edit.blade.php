<x-app-layout :title="__('app.category.edit', ['name' => $category->name])">
    <div class="mx-auto max-w-2xl">
        <x-card :title="__('app.category.edit', ['name' => $category->name])">
            <form method="POST" action="{{ route('categories.update', $category) }}">
                @csrf
                @method('PUT')

                @include('categories.partials.form')

                <div class="flex items-center justify-end gap-2 border-t border-line bg-surface-sunken px-4 py-3 sm:px-5">
                    <x-button :href="route('categories.index')" variant="secondary">{{ __('app.common.cancel') }}</x-button>
                    <x-button type="submit">{{ __('app.common.save') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
