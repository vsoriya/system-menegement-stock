<?php

namespace App\Enums;

enum MovementType: string
{
    /** Stock received into the warehouse (purchase, return from customer). */
    case In = 'in';

    /** Stock leaving the warehouse (sale, damage, return to supplier). */
    case Out = 'out';

    /** Manual correction that sets the quantity to a counted value. */
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return __('app.movement.'.$this->value);
    }

    /**
     * Tailwind classes used to badge the movement in listings.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::In => 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 ring-emerald-600/20 dark:ring-emerald-400/30',
            self::Out => 'bg-rose-50 dark:bg-rose-500/15 text-rose-700 dark:text-rose-300 ring-rose-600/20 dark:ring-rose-400/30',
            self::Adjustment => 'bg-amber-50 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 ring-amber-600/20 dark:ring-amber-400/30',
        };
    }

    /**
     * Sign shown in front of the quantity in listings.
     */
    public function sign(): string
    {
        return match ($this) {
            self::In => '+',
            self::Out => '-',
            self::Adjustment => '=',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $type) => $carry + [$type->value => $type->label()],
            [],
        );
    }
}
