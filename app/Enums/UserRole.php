<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Staff = 'staff';

    public function label(): string
    {
        return __('app.role.'.$this->value);
    }

    /**
     * Roles allowed to create, update and delete catalog records.
     */
    public function canManageCatalog(): bool
    {
        return in_array($this, [self::Admin, self::Manager], true);
    }

    /**
     * Roles allowed to permanently remove records.
     */
    public function canDelete(): bool
    {
        return $this === self::Admin;
    }

    /**
     * Roles allowed to overwrite a quantity on hand with a counted figure.
     *
     * Stock In and Out are deltas: they say what moved, and the history adds up.
     * An Adjustment replaces the balance outright, which is the one operation
     * that can make a shortfall disappear without a trace of what caused it.
     *
     * Kept away from Staff on purpose. Someone who both sells and can rewrite a
     * balance can take goods and then correct the count to match the shelf. The
     * proper route for a genuine discrepancy is a stock count, which records the
     * expected figure alongside the counted one and is already manager-only.
     */
    public function canAdjustStock(): bool
    {
        return in_array($this, [self::Admin, self::Manager], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $role) => $carry + [$role->value => $role->label()],
            [],
        );
    }
}
