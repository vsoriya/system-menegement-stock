{{-- Shared category form. @param \App\Models\Category $category --}}

<div class="grid grid-cols-1 gap-5 p-4 sm:p-5">
    <x-field
        :label="__('app.category.name')"
        name="name"
        :value="$category->name"
        placeholder=""
        required
    />

    <x-field
        :label="__('app.common.description')"
        name="description"
        type="textarea"
        :value="$category->description"
        rows="3"
        placeholder=""
    />

    <x-field
        :label="__('app.common.status')"
        name="is_active"
        type="checkbox"
        :value="$category->is_active ?? true"
        :hint="__('app.category.active_hint')"
    />
</div>
