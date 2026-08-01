<x-app-layout :title="__('app.product.add')">
    <div class="mx-auto max-w-4xl">
        <x-card :title="__('app.product.new')" :description="__('app.product.new_sub')">
            {{-- enctype is required for the image upload. Without it the browser
                 posts only the file name and the picture never arrives. --}}
            <form method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data">
                @csrf

                @include('products.partials.form', ['creating' => true])

                <div class="flex items-center justify-end gap-2 border-t border-line bg-surface-sunken px-4 py-3 sm:px-5">
                    <x-button :href="route('products.index')" variant="secondary">{{ __('app.common.cancel') }}</x-button>
                    <x-button type="submit">{{ __('app.common.create') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
