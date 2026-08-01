<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    /** Still being put together, freely editable. */
    case Draft = 'draft';

    /** Signed off and awaiting delivery. Lines are locked. */
    case Approved = 'approved';

    /** Everything ordered has arrived and entered stock. */
    case Received = 'received';

    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('app.po.'.$this->value);
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-surface-sunken text-ink-muted ring-line',
            self::Approved => 'bg-brand-50 dark:bg-brand-500/20 text-brand-700 dark:text-brand-300 ring-brand-600/20 dark:ring-brand-400/30',
            self::Received => 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 ring-emerald-600/20 dark:ring-emerald-400/30',
            self::Cancelled => 'bg-rose-50 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 ring-rose-600/20 dark:ring-rose-400/30',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isReceivable(): bool
    {
        return $this === self::Approved;
    }

    public function isCancellable(): bool
    {
        return in_array($this, [self::Draft, self::Approved], true);
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
