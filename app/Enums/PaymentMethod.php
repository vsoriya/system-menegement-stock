<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';

    /** Card machine, or a QR wallet such as Bakong or ABA Pay. */
    case Card = 'card';

    case Transfer = 'transfer';

    public function label(): string
    {
        return __('app.sale.pay_'.$this->value);
    }

    /**
     * Only cash needs change worked out and a drawer to open.
     */
    public function needsChange(): bool
    {
        return $this === self::Cash;
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
