{{--
    The till.

    The whole basket lives in Alpine state on this one page. Nothing here
    navigates or reloads while a sale is being built, because losing a
    half-scanned basket to a page load is the fastest way to make a cashier
    distrust the system. Searching and filtering therefore happen in the
    browser, over the product list handed down by the controller.
--}}
<x-app-layout :title="__('app.pos.title')">
    <x-slot:actions>
        <x-button :href="route('sales.index')" variant="secondary" size="sm">{{ __('app.sale.title') }}</x-button>
    </x-slot:actions>

    @php
        // The basket lives in the browser, so a rejected submit would normally
        // wipe it. Stock can genuinely run out between loading this page and
        // pressing the button, if another till got there first, so the basket is
        // rebuilt from the old input rather than making the cashier start again.
        $restore = collect(old('items', []))
            ->map(fn ($item): array => [
                'id' => (int) ($item['product_id'] ?? 0),
                'quantity' => (int) ($item['quantity'] ?? 0),
                'price' => (float) ($item['unit_price'] ?? 0),
            ])
            ->filter(fn (array $item): bool => $item['id'] > 0 && $item['quantity'] > 0)
            ->values();
    @endphp

    <div
        x-data="till({
            products: @js($products),
            restore: @js($restore),
            customerId: @js((string) old('customer_id', $selectedCustomer ?? '')),
            discount: @js((float) old('discount', 0)),
            paid: @js((string) old('paid', '')),
            method: @js((string) old('payment_method', 'cash')),
        })"
        class="grid grid-cols-1 gap-5 xl:grid-cols-5"
    >
        {{-- Left: what is for sale --}}
        <div class="space-y-4 xl:col-span-3">
            <x-card>
                <div class="space-y-3 p-4">
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="min-w-56 flex-1 space-y-1.5">
                            <label for="pos-search" class="block text-sm font-medium text-ink">
                                {{ __('app.pos.scan_or_search') }}
                            </label>
                            <input
                                id="pos-search"
                                type="search"
                                x-model="search"
                                x-ref="search"
                                @keydown.enter.prevent="submitSearch()"
                                autocomplete="off"
                                placeholder="{{ __('app.pos.scan_placeholder') }}"
                                class="block w-full rounded-xl border border-line bg-surface px-3 py-2 text-sm text-ink shadow-soft transition duration-200 ease-out placeholder:text-ink-subtle hover:border-ink-subtle focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 focus:outline-none"
                            >
                            <p class="text-xs text-ink-muted">{{ __('app.pos.scan_hint') }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-1.5">
                        <button
                            type="button"
                            @click="category = null"
                            :class="category === null
                                ? 'bg-brand-600 text-white shadow-soft'
                                : 'border border-line bg-surface text-ink-muted hover:text-ink'"
                            class="rounded-full px-3 py-1 text-xs font-medium transition"
                        >{{ __('app.common.all') }}</button>

                        @foreach ($categories as $category)
                            <button
                                type="button"
                                @click="category = {{ $category->id }}"
                                :class="category === {{ $category->id }}
                                    ? 'bg-brand-600 text-white shadow-soft'
                                    : 'border border-line bg-surface text-ink-muted hover:text-ink'"
                                class="rounded-full px-3 py-1 text-xs font-medium transition"
                            >{{ $category->name }}</button>
                        @endforeach
                    </div>
                </div>
            </x-card>

            <x-card>
                <template x-if="visible.length === 0">
                    <div class="px-4 py-14 text-center">
                        <h3 class="text-sm font-semibold text-ink">{{ __('app.pos.no_products') }}</h3>
                        <p class="mx-auto mt-1 max-w-sm text-sm text-ink-muted">{{ __('app.pos.no_products_sub') }}</p>
                    </div>
                </template>

                <div class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3 lg:grid-cols-4">
                    <template x-for="product in visible" :key="product.id">
                        <button
                            type="button"
                            @click="add(product)"
                            :disabled="product.stock <= 0"
                            :title="product.name"
                            class="group flex flex-col overflow-hidden rounded-xl border border-line bg-surface text-left shadow-soft transition duration-200 hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-lift focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:translate-y-0"
                        >
                            <span class="relative block aspect-square w-full overflow-hidden bg-surface-sunken">
                                <template x-if="product.image">
                                    <img
                                        :src="product.image"
                                        :alt="product.name"
                                        loading="lazy"
                                        class="size-full object-cover transition duration-300 group-hover:scale-105"
                                    >
                                </template>

                                {{-- No photo yet, so show the initials instead of an empty box. --}}
                                <template x-if="! product.image">
                                    <span class="flex size-full items-center justify-center text-lg font-semibold text-ink-subtle" x-text="initials(product.name)"></span>
                                </template>

                                <span
                                    x-show="product.stock <= 0"
                                    class="absolute inset-x-0 bottom-0 bg-rose-600/90 px-1 py-0.5 text-center text-[0.625rem] font-semibold text-white"
                                >{{ __('app.stock_status.out_of_stock') }}</span>
                            </span>

                            <span class="flex flex-1 flex-col gap-0.5 p-2.5">
                                <span class="line-clamp-2 text-xs font-medium text-ink" x-text="product.name"></span>
                                <span class="mt-auto flex items-baseline justify-between gap-1">
                                    <span class="text-sm font-semibold tabular-nums text-brand-600 dark:text-brand-400" x-text="money(product.price)"></span>
                                    <span class="text-[0.625rem] tabular-nums text-ink-muted" x-text="product.stock + ' ' + product.unit"></span>
                                </span>
                            </span>
                        </button>
                    </template>
                </div>

                <p
                    x-show="matches.length > visible.length"
                    class="border-t border-line px-4 py-2.5 text-center text-xs text-ink-muted"
                >
                    <span x-text="visible.length"></span> / <span x-text="matches.length"></span>
                    &middot; {{ __('app.pos.narrow_search') }}
                </p>
            </x-card>
        </div>

        {{-- Right: the basket --}}
        <div class="xl:col-span-2">
            <form method="POST" action="{{ route('pos.store') }}" @submit="submitting = true" class="xl:sticky xl:top-20">
                @csrf

                <x-card :title="__('app.pos.cart')">
                    <x-slot:cardActions>
                        <button
                            type="button"
                            @click="clear()"
                            x-show="cart.length > 0"
                            class="rounded-lg px-2 py-1 text-xs font-medium text-rose-600 transition hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/15"
                        >{{ __('app.pos.clear_cart') }}</button>
                    </x-slot:cardActions>

                    @error('items')
                        <p class="border-b border-line bg-rose-50 px-4 py-2.5 text-sm font-medium text-rose-700 dark:bg-rose-500/15 dark:text-rose-300">
                            {{ $message }}
                        </p>
                    @enderror

                    <template x-if="cart.length === 0">
                        <p class="px-4 py-10 text-center text-sm text-ink-muted">{{ __('app.pos.cart_empty') }}</p>
                    </template>

                    <ul x-show="cart.length > 0" class="divide-y divide-line">
                        <template x-for="(line, index) in cart" :key="line.id">
                            <li class="space-y-2 px-4 py-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-ink" x-text="line.name"></p>
                                        <p class="font-mono text-xs text-ink-muted" x-text="line.sku"></p>
                                    </div>
                                    <button
                                        type="button"
                                        @click="remove(line.id)"
                                        class="shrink-0 rounded-lg p-1 text-ink-subtle transition hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/15 dark:hover:text-rose-400"
                                    >
                                        <span class="sr-only">{{ __('app.pos.remove_line') }}</span>
                                        <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <div class="flex items-center rounded-xl border border-line bg-surface">
                                        <button
                                            type="button"
                                            @click="setQuantity(line, line.quantity - 1)"
                                            class="px-2.5 py-1.5 text-ink-muted transition hover:text-ink"
                                        >
                                            <span class="sr-only">{{ __('app.pos.decrease') }}</span>
                                            <span aria-hidden="true">&minus;</span>
                                        </button>
                                        <input
                                            type="number"
                                            min="1"
                                            :max="line.stock"
                                            x-model.number="line.quantity"
                                            @change="setQuantity(line, line.quantity)"
                                            class="w-14 border-x border-line bg-transparent px-1 py-1.5 text-center text-sm tabular-nums text-ink focus:outline-none"
                                            :aria-label="@js(__('app.sale.items')) + ' ' + line.name"
                                        >
                                        <button
                                            type="button"
                                            @click="setQuantity(line, line.quantity + 1)"
                                            class="px-2.5 py-1.5 text-ink-muted transition hover:text-ink"
                                        >
                                            <span class="sr-only">{{ __('app.pos.increase') }}</span>
                                            <span aria-hidden="true">+</span>
                                        </button>
                                    </div>

                                    {{-- Haggling is normal here, so the line price stays editable. --}}
                                    <label class="flex items-center gap-1 text-xs text-ink-muted">
                                        <span>{{ config('app.currency_symbol') }}</span>
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            x-model.number="line.price"
                                            class="w-20 rounded-lg border border-line bg-surface px-2 py-1 text-right text-sm tabular-nums text-ink focus:border-brand-500 focus:outline-none"
                                            :aria-label="@js(__('app.sale.unit_price')) + ' ' + line.name"
                                        >
                                    </label>

                                    <span class="ml-auto text-sm font-semibold tabular-nums text-ink" x-text="money(line.quantity * line.price)"></span>
                                </div>

                                <p x-show="line.quantity >= line.stock" class="text-xs text-amber-600 dark:text-amber-400">
                                    {{ __('app.pos.stock_limit') }}
                                </p>

                                {{-- Hidden inputs are what actually gets posted. --}}
                                <input type="hidden" :name="`items[${index}][product_id]`" :value="line.id">
                                <input type="hidden" :name="`items[${index}][quantity]`" :value="line.quantity">
                                <input type="hidden" :name="`items[${index}][unit_price]`" :value="line.price">
                            </li>
                        </template>
                    </ul>

                    <div class="space-y-3 border-t border-line p-4">
                        <div class="space-y-1.5">
                            <label for="customer_id" class="block text-sm font-medium text-ink">{{ __('app.customer.one') }}</label>
                            <div class="flex gap-2">
                                <select
                                    id="customer_id"
                                    name="customer_id"
                                    x-model="customerId"
                                    class="block w-full rounded-xl border border-line bg-surface px-3 py-2 text-sm text-ink shadow-soft focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 focus:outline-none"
                                >
                                    <option value="">{{ __('app.customer.walk_in') }}</option>
                                    @foreach ($customers as $customer)
                                        <option value="{{ $customer->id }}">
                                            {{ $customer->name }}@if (filled($customer->phone)) &middot; {{ $customer->phone }}@endif
                                        </option>
                                    @endforeach
                                </select>
                                <x-button :href="route('customers.create', ['from_pos' => 1])" variant="secondary" size="sm">
                                    {{ __('app.common.add') }}
                                </x-button>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <label for="discount" class="block text-sm font-medium text-ink">{{ __('app.sale.discount') }}</label>
                                <input
                                    id="discount"
                                    type="number"
                                    name="discount"
                                    min="0"
                                    step="0.01"
                                    x-model.number="discount"
                                    class="block w-full rounded-xl border border-line bg-surface px-3 py-2 text-right text-sm tabular-nums text-ink shadow-soft focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 focus:outline-none"
                                >
                            </div>

                            <div class="space-y-1.5">
                                <label for="payment_method" class="block text-sm font-medium text-ink">{{ __('app.sale.payment_method') }}</label>
                                <select
                                    id="payment_method"
                                    name="payment_method"
                                    x-model="method"
                                    class="block w-full rounded-xl border border-line bg-surface px-3 py-2 text-sm text-ink shadow-soft focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 focus:outline-none"
                                >
                                    @foreach ($paymentMethods as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Cash is the only method that involves change. --}}
                        <div x-show="method === 'cash'" class="space-y-1.5">
                            <label for="paid" class="block text-sm font-medium text-ink">{{ __('app.sale.paid') }}</label>
                            <input
                                id="paid"
                                type="number"
                                name="paid"
                                min="0"
                                step="0.01"
                                x-model.number="paid"
                                :placeholder="total.toFixed(2)"
                                class="block w-full rounded-xl border border-line bg-surface px-3 py-2 text-right text-lg font-semibold tabular-nums text-ink shadow-soft focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 focus:outline-none"
                            >
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="amount in quickCash" :key="amount">
                                    <button
                                        type="button"
                                        @click="paid = amount"
                                        class="rounded-lg border border-line bg-surface px-2 py-1 text-xs font-medium tabular-nums text-ink-muted transition hover:text-ink"
                                        x-text="money(amount)"
                                    ></button>
                                </template>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label for="note" class="block text-sm font-medium text-ink">{{ __('app.sale.note') }}</label>
                            <input
                                id="note"
                                type="text"
                                name="note"
                                maxlength="500"
                                value="{{ old('note') }}"
                                class="block w-full rounded-xl border border-line bg-surface px-3 py-2 text-sm text-ink shadow-soft focus:border-brand-500 focus:ring-4 focus:ring-brand-500/15 focus:outline-none"
                            >
                        </div>
                    </div>

                    <dl class="space-y-1.5 border-t border-line bg-surface-sunken px-4 py-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-ink-muted">{{ __('app.sale.subtotal') }}</dt>
                            <dd class="font-medium tabular-nums text-ink" x-text="money(subtotal)"></dd>
                        </div>
                        <div class="flex justify-between gap-4" x-show="discount > 0">
                            <dt class="text-ink-muted">{{ __('app.sale.discount') }}</dt>
                            <dd class="font-medium tabular-nums text-rose-600 dark:text-rose-400" x-text="'-' + money(discount)"></dd>
                        </div>
                        <div class="flex justify-between gap-4 border-t border-line pt-1.5">
                            <dt class="font-semibold text-ink">{{ __('app.sale.total') }}</dt>
                            <dd class="text-lg font-semibold tabular-nums text-ink" x-text="money(total)"></dd>
                        </div>
                        <div class="flex justify-between gap-4" x-show="method === 'cash' && paid !== '' && paid !== null">
                            <dt class="text-ink-muted">{{ __('app.sale.change_due') }}</dt>
                            <dd
                                class="font-semibold tabular-nums"
                                :class="change < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'"
                                x-text="money(change)"
                            ></dd>
                        </div>
                    </dl>

                    <div class="border-t border-line p-4">
                        <button
                            type="submit"
                            :disabled="! canSubmit || submitting"
                            class="w-full rounded-xl bg-linear-to-b from-brand-500 to-brand-600 px-4 py-3 text-sm font-semibold text-white shadow-soft transition duration-200 hover:shadow-glow active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60 disabled:active:scale-100"
                        >
                            <span x-show="! submitting">{{ __('app.pos.complete_sale') }}</span>
                            <span x-show="submitting">{{ __('app.common.loading') }}</span>
                        </button>

                        <p x-show="discountTooBig" class="mt-2 text-xs font-medium text-rose-600 dark:text-rose-400">
                            {{ __('app.pos.discount_too_big') }}
                        </p>
                        <p x-show="method === 'cash' && change < 0" class="mt-2 text-xs font-medium text-rose-600 dark:text-rose-400">
                            {{ __('app.pos.not_enough_paid') }}
                        </p>
                    </div>
                </x-card>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            function till({ products, restore, customerId, discount, paid, method }) {
                return {
                    products,
                    search: '',
                    category: null,
                    limit: 60,
                    cart: [],
                    discount,
                    paid,
                    method,
                    customerId,
                    submitting: false,

                    init() {
                        // Prices come back from the old input, not the product
                        // list, so a haggled price survives a rejected submit.
                        this.cart = restore
                            .map((line) => {
                                const product = this.products.find((item) => Number(item.id) === Number(line.id));

                                if (! product) {
                                    return null;
                                }

                                return {
                                    id: product.id,
                                    name: product.name,
                                    sku: product.sku,
                                    unit: product.unit,
                                    price: line.price,
                                    stock: product.stock,
                                    quantity: Math.min(line.quantity, product.stock),
                                };
                            })
                            .filter((line) => line !== null && line.quantity > 0);
                    },

                    get matches() {
                        const term = this.search.trim().toLowerCase();

                        return this.products.filter((product) => {
                            // Number() on both sides, so a category id arriving
                            // as a string still matches. Belt and braces with
                            // the casting the controller already does.
                            if (this.category !== null && Number(product.category_id) !== Number(this.category)) {
                                return false;
                            }

                            if (term === '') {
                                return true;
                            }

                            return product.name.toLowerCase().includes(term)
                                || product.sku.toLowerCase().includes(term)
                                || product.barcode.toLowerCase().includes(term);
                        });
                    },

                    /**
                     * Capped, because building a tile for every product in a
                     * large catalogue would make the page crawl. Searching or
                     * picking a category narrows it; scanning bypasses the grid
                     * entirely and goes straight into the basket.
                     */
                    get visible() {
                        return this.matches.slice(0, this.limit);
                    },

                    get subtotal() {
                        return this.cart.reduce(
                            (sum, line) => sum + (Number(line.quantity) || 0) * (Number(line.price) || 0),
                            0,
                        );
                    },

                    get total() {
                        return Math.max(this.subtotal - (Number(this.discount) || 0), 0);
                    },

                    get change() {
                        return (Number(this.paid) || 0) - this.total;
                    },

                    get discountTooBig() {
                        return (Number(this.discount) || 0) > this.subtotal;
                    },

                    // Rounded up to handy notes, so the cashier can tap rather
                    // than type what was handed over.
                    get quickCash() {
                        const total = this.total;

                        if (total <= 0) {
                            return [];
                        }

                        return [1, 5, 10, 20, 50, 100]
                            .map((step) => Math.ceil(total / step) * step)
                            .filter((amount, index, all) => amount >= total && all.indexOf(amount) === index)
                            .slice(0, 4);
                    },

                    get canSubmit() {
                        if (this.cart.length === 0 || this.total < 0 || this.discountTooBig) {
                            return false;
                        }

                        // Cash has to cover the total. Card and transfer settle
                        // for the exact amount, so there is nothing to check.
                        return this.method !== 'cash' || this.change >= 0;
                    },

                    /**
                     * A repeat scan raises the quantity on the existing line
                     * rather than adding a second one, which matches how the
                     * invoice is stored.
                     */
                    add(product) {
                        if (product.stock <= 0) {
                            return;
                        }

                        const existing = this.cart.find((line) => line.id === product.id);

                        if (existing) {
                            this.setQuantity(existing, existing.quantity + 1);

                            return;
                        }

                        this.cart.push({
                            id: product.id,
                            name: product.name,
                            sku: product.sku,
                            unit: product.unit,
                            price: product.price,
                            stock: product.stock,
                            quantity: 1,
                        });
                    },

                    /**
                     * Clamped to what is on the shelf. The server checks this
                     * again under a row lock, this only spares the cashier a
                     * pointless rejection.
                     */
                    setQuantity(line, quantity) {
                        const wanted = Math.floor(Number(quantity) || 0);

                        if (wanted < 1) {
                            this.remove(line.id);

                            return;
                        }

                        line.quantity = Math.min(wanted, line.stock);
                    },

                    remove(id) {
                        this.cart = this.cart.filter((line) => line.id !== id);
                    },

                    clear() {
                        this.cart = [];
                        this.discount = 0;
                        this.paid = '';
                    },

                    /**
                     * Enter in the search box. A barcode scanner types the code
                     * then sends Enter, so an exact match on barcode or SKU goes
                     * straight into the basket. Otherwise, if the filter has
                     * narrowed things to a single product, take that.
                     */
                    submitSearch() {
                        const term = this.search.trim().toLowerCase();

                        if (term === '') {
                            return;
                        }

                        const exact = this.products.find(
                            (product) => product.barcode.toLowerCase() === term
                                || product.sku.toLowerCase() === term,
                        );

                        const target = exact ?? (this.visible.length === 1 ? this.visible[0] : null);

                        if (target) {
                            this.add(target);
                            this.search = '';
                            this.$refs.search.focus();
                        }
                    },

                    initials(name) {
                        return name
                            .split(/\s+/)
                            .slice(0, 2)
                            .map((word) => word.charAt(0).toUpperCase())
                            .join('');
                    },

                    money(amount) {
                        return @js(config('app.currency_symbol')) + (Number(amount) || 0).toFixed(2);
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>
