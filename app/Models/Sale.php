<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'number',
    'customer_id',
    'user_id',
    'status',
    'payment_method',
    'subtotal',
    'discount',
    'total',
    'paid',
    'change_due',
    'sold_at',
    'voided_at',
    'note',
])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SaleStatus::class,
            'payment_method' => PaymentMethod::class,
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid' => 'decimal:2',
            'change_due' => 'decimal:2',
            'sold_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * Whoever rang the sale up.
     *
     * @return BelongsTo<User, $this>
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<SaleLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    protected function itemCount(): Attribute
    {
        return Attribute::get(fn (): int => (int) $this->lines->sum('quantity'));
    }

    /**
     * Gross profit, from the costs captured at the time of sale.
     */
    protected function profit(): Attribute
    {
        return Attribute::get(fn (): float => round(
            $this->lines->sum(fn (SaleLine $line) => $line->line_profit) - (float) $this->discount,
            2,
        ));
    }

    protected function isVoided(): Attribute
    {
        return Attribute::get(fn (): bool => $this->status === SaleStatus::Voided);
    }

    #[Scope]
    protected function completed(Builder $query): void
    {
        $query->where('status', SaleStatus::Completed);
    }

    #[Scope]
    protected function withStatus(Builder $query, ?string $status): void
    {
        $query->when(filled($status), fn (Builder $query) => $query->where('status', $status));
    }

    #[Scope]
    protected function betweenDates(Builder $query, ?string $from, ?string $to): void
    {
        $query->when(filled($from), fn (Builder $query) => $query->whereDate('sold_at', '>=', $from))
            ->when(filled($to), fn (Builder $query) => $query->whereDate('sold_at', '<=', $to));
    }

    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when(filled($term), function (Builder $query) use ($term): void {
            $query->where(function (Builder $query) use ($term): void {
                $query->where('number', 'like', "%{$term}%")
                    ->orWhere('note', 'like', "%{$term}%")
                    ->orWhereHas('customer', function (Builder $q) use ($term): void {
                        $q->where('name', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%");
                    });
            });
        });
    }

    /**
     * Sequential invoice number in the form INV-2026-000001.
     */
    public static function nextNumber(): string
    {
        $prefix = 'INV-'.Carbon::now()->year.'-';

        $last = static::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last ? ((int) substr((string) $last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
