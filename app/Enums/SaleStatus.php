<?php

namespace App\Enums;

enum SaleStatus: string
{
    /** Paid for and handed over. Stock has already left. */
    case Completed = 'completed';

    /** Reversed. The stock was put back and the invoice is kept for the record. */
    case Voided = 'voided';

    public function label(): string
    {
        return __('app.sale.'.$this->value);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Completed => 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 ring-emerald-600/20 dark:ring-emerald-400/30',
            self::Voided => 'bg-rose-50 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 ring-rose-600/20 dark:ring-rose-400/30',
        };
    }

    public function isVoidable(): bool
    {
        return $this === self::Completed;
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
