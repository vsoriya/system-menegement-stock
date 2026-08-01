<?php

namespace App\Enums;

enum StockTakeStatus: string
{
    /** Count sheet is being filled in. */
    case Open = 'open';

    /** Differences have been written to stock as adjustments. */
    case Posted = 'posted';

    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('app.stocktake.'.$this->value);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Open => 'bg-amber-50 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 ring-amber-600/20 dark:ring-amber-400/30',
            self::Posted => 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 ring-emerald-600/20 dark:ring-emerald-400/30',
            self::Cancelled => 'bg-rose-50 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 ring-rose-600/20 dark:ring-rose-400/30',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Open;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case) => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
