@props(['data'])

@php
    // Scale every bar against the busiest day so the tallest bar fills the plot.
    $peak = collect($data)->flatMap(fn (array $d) => [$d['in'], $d['out']])->max() ?: 1;
@endphp

<div class="px-4 py-5 sm:px-5">
    <div class="mb-4 flex flex-wrap items-center gap-4">
        <span class="flex items-center gap-1.5 text-xs font-medium text-ink-muted">
            <span class="size-2.5 rounded-sm bg-linear-to-t from-emerald-500 to-emerald-400" aria-hidden="true"></span>
            Received
        </span>
        <span class="flex items-center gap-1.5 text-xs font-medium text-ink-muted">
            <span class="size-2.5 rounded-sm bg-linear-to-t from-rose-500 to-rose-400" aria-hidden="true"></span>
            Issued
        </span>
        <span class="ml-auto text-xs text-ink-subtle">{{ __('app.dashboard.peak', ['count' => number_format($peak)]) }}</span>
    </div>

    <div class="flex h-44 items-end justify-between gap-1.5 sm:gap-2">
        @foreach ($data as $index => $day)
            @php
                $inHeight = max($day['in'] > 0 ? 4 : 0, round($day['in'] / $peak * 100));
                $outHeight = max($day['out'] > 0 ? 4 : 0, round($day['out'] / $peak * 100));
                $delay = $index * 45;
            @endphp

            <div class="group relative flex h-full flex-1 flex-col justify-end">
                {{-- Tooltip --}}
                <div
                    class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 -translate-x-1/2 scale-95 rounded-lg bg-ink px-2.5 py-1.5 text-xs whitespace-nowrap text-surface opacity-0 shadow-lift transition duration-150 group-hover:scale-100 group-hover:opacity-100"
                    role="tooltip"
                >
                    <p class="font-semibold">{{ $day['date']->format('D d M') }}</p>
                    <p class="text-emerald-300 dark:text-emerald-700">+{{ number_format($day['in']) }} {{ __('app.dashboard.received') }}</p>
                    <p class="text-rose-300 dark:text-rose-700">-{{ number_format($day['out']) }} {{ __('app.dashboard.issued') }}</p>
                </div>

                <div class="flex h-full items-end justify-center gap-0.5">
                    <span
                        class="animate-grow-bar stagger w-full max-w-3 origin-bottom rounded-t-md bg-linear-to-t from-emerald-500 to-emerald-400 transition-opacity duration-200 group-hover:opacity-100 sm:max-w-2.5"
                        style="height: {{ $inHeight }}%; --d: {{ $delay }}ms"
                        aria-hidden="true"
                    ></span>
                    <span
                        class="animate-grow-bar stagger w-full max-w-3 origin-bottom rounded-t-md bg-linear-to-t from-rose-500 to-rose-400 transition-opacity duration-200 group-hover:opacity-100 sm:max-w-2.5"
                        style="height: {{ $outHeight }}%; --d: {{ $delay + 90 }}ms"
                        aria-hidden="true"
                    ></span>
                </div>

                <span class="mt-2 block text-center text-[0.625rem] font-medium text-ink-subtle transition group-hover:text-ink">
                    {{ $day['date']->format('j') }}
                </span>
            </div>
        @endforeach
    </div>

    {{-- Text equivalent for screen readers, since the bars are decorative. --}}
    <table class="sr-only">
        <caption>{{ __('app.dashboard.chart_caption') }}</caption>
        <thead>
            <tr><th scope="col">Date</th><th scope="col">{{ __('app.dashboard.received') }}</th><th scope="col">{{ __('app.dashboard.issued') }}</th></tr>
        </thead>
        <tbody>
            @foreach ($data as $day)
                <tr>
                    <th scope="row">{{ $day['date']->format('D d M Y') }}</th>
                    <td>{{ number_format($day['in']) }}</td>
                    <td>{{ number_format($day['out']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
