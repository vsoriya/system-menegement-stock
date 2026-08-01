{{--
    Flash messages and the validation summary.

    Success and error flashes behave like toasts: they slide in, then fade out
    on their own after a few seconds. The validation summary stays put, since
    the user needs it while fixing the form.
--}}

@if (session('status'))
    <div
        x-data="{ show: true }"
        x-show="show"
        x-init="setTimeout(() => show = false, 5000)"
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="opacity-100 translate-x-0"
        x-transition:leave-end="opacity-0 translate-x-4"
        role="status"
        class="animate-toast mb-5 flex items-start gap-3 overflow-hidden rounded-xl border border-emerald-200/70 dark:border-emerald-500/30 bg-emerald-50/90 dark:bg-emerald-500/15 px-4 py-3 text-sm text-emerald-900 dark:text-emerald-100 shadow-soft backdrop-blur"
    >
        <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white">
            <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 0 1 0 1.414l-8 8a1 1 0 0 1-1.414 0l-4-4a1 1 0 1 1 1.414-1.414L8 12.586l7.293-7.293a1 1 0 0 1 1.414 0Z" clip-rule="evenodd" />
            </svg>
        </span>
        <p class="flex-1 font-medium">{{ session('status') }}</p>
        <button
            type="button"
            @click="show = false"
            class="shrink-0 rounded-md p-0.5 text-emerald-700/70 dark:text-emerald-300/70 transition hover:bg-emerald-100 dark:hover:bg-emerald-500/25 hover:text-emerald-900 dark:hover:text-emerald-100 dark:text-emerald-100"
        >
            <span class="sr-only">{{ __('app.common.dismiss') }}</span>
            <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
            </svg>
        </button>
    </div>
@endif

@if (session('error'))
    <div
        x-data="{ show: true }"
        x-show="show"
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="opacity-100 translate-x-0"
        x-transition:leave-end="opacity-0 translate-x-4"
        role="alert"
        class="animate-toast mb-5 flex items-start gap-3 rounded-xl border border-rose-200/70 dark:border-rose-500/30 bg-rose-50/90 dark:bg-rose-500/15 px-4 py-3 text-sm text-rose-900 dark:text-rose-100 shadow-soft backdrop-blur"
    >
        <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-rose-600 text-white">
            <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M10 5a.75.75 0 0 1 .75.75v4a.75.75 0 0 1-1.5 0v-4A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" />
            </svg>
        </span>
        <p class="flex-1 font-medium">{{ session('error') }}</p>
        <button
            type="button"
            @click="show = false"
            class="shrink-0 rounded-md p-0.5 text-rose-700/70 dark:text-rose-300/70 transition hover:bg-rose-100 dark:hover:bg-rose-500/25 hover:text-rose-900 dark:hover:text-rose-100 dark:text-rose-100"
        >
            <span class="sr-only">{{ __('app.common.dismiss') }}</span>
            <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
            </svg>
        </button>
    </div>
@endif

@if ($errors->any())
    <div
        role="alert"
        class="animate-toast mb-5 rounded-xl border border-rose-200/70 dark:border-rose-500/30 bg-rose-50/90 dark:bg-rose-500/15 px-4 py-3 text-sm text-rose-900 dark:text-rose-100 shadow-soft backdrop-blur"
    >
        <div class="flex items-center gap-2.5">
            <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-rose-600 text-white">
                <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M10 5a.75.75 0 0 1 .75.75v4a.75.75 0 0 1-1.5 0v-4A.75.75 0 0 1 10 5Zm0 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" />
                </svg>
            </span>
            <p class="font-semibold">
                {{ $errors->count() === 1 ? __('app.common.problem_one') : __('app.common.problem_many', ['count' => $errors->count()]) }}
            </p>
        </div>
        <ul class="mt-2 space-y-1 pl-7">
            @foreach ($errors->all() as $index => $message)
                <li class="animate-slide-left stagger flex items-start gap-2" style="--d: {{ $index * 50 }}ms">
                    <span class="mt-1.5 size-1 shrink-0 rounded-full bg-rose-500" aria-hidden="true"></span>
                    {{ $message }}
                </li>
            @endforeach
        </ul>
    </div>
@endif
