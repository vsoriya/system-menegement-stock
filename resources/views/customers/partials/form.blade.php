{{-- Shared customer form. @param \App\Models\Customer $customer --}}

<div class="grid grid-cols-1 gap-5 p-4 sm:p-5 lg:grid-cols-2">
    <x-field
        :label="__('app.customer.name')"
        name="name"
        :value="$customer->name"
        :placeholder="__('app.customer.name_placeholder')"
        required
        class="lg:col-span-2"
    />

    <x-field
        :label="__('app.customer.phone')"
        name="phone"
        type="tel"
        :value="$customer->phone"
        :placeholder="__('app.customer.phone_placeholder')"
        :hint="__('app.customer.phone_hint')"
    />

    <x-field
        :label="__('app.customer.email')"
        name="email"
        type="email"
        :value="$customer->email"
        :placeholder="__('app.customer.email_placeholder')"
    />

    <x-field
        :label="__('app.customer.address')"
        name="address"
        :value="$customer->address"
        class="lg:col-span-2"
    />

    <x-field
        :label="__('app.common.notes')"
        name="notes"
        type="textarea"
        :value="$customer->notes"
        rows="3"
        :placeholder="__('app.customer.notes_hint')"
        class="lg:col-span-2"
    />

    <x-field
        :label="__('app.common.status')"
        name="is_active"
        type="checkbox"
        :value="$customer->is_active ?? true"
        :hint="__('app.customer.active_hint')"
        class="lg:col-span-2"
    />
</div>
