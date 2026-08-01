{{-- Shared supplier form. @param \App\Models\Supplier $supplier --}}

<div class="grid grid-cols-1 gap-5 p-4 sm:p-5 lg:grid-cols-2">
    <x-field
        :label="__('app.supplier.name')"
        name="name"
        :value="$supplier->name"
        placeholder="e.g. Mekong Trading Co."
        required
        class="lg:col-span-2"
    />

    <x-field
        :label="__('app.supplier.contact_person')"
        name="contact_person"
        :value="$supplier->contact_person"
        placeholder="e.g. Dara Sok"
    />

    <x-field
        :label="__('app.supplier.phone')"
        name="phone"
        type="tel"
        :value="$supplier->phone"
        placeholder="e.g. +855 12 345 678"
    />

    <x-field
        :label="__('app.supplier.email')"
        name="email"
        type="email"
        :value="$supplier->email"
        placeholder="e.g. sales@example.com"
    />

    <x-field
        :label="__('app.supplier.address')"
        name="address"
        :value="$supplier->address"
        placeholder=""
    />

    <x-field
        :label="__('app.common.notes')"
        name="notes"
        type="textarea"
        :value="$supplier->notes"
        rows="3"
        :placeholder="__('app.supplier.notes_hint')"
        class="lg:col-span-2"
    />

    <x-field
        :label="__('app.common.status')"
        name="is_active"
        type="checkbox"
        :value="$supplier->is_active ?? true"
        :hint="__('app.category.active_hint')"
        class="lg:col-span-2"
    />
</div>
