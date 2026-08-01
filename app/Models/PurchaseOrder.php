<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Database\Factories\PurchaseOrderFactory;
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
    'supplier_id',
    'created_by',
    'approved_by',
    'status',
    'ordered_at',
    'expected_at',
    'approved_at',
    'received_at',
    'notes',
])]
class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'ordered_at' => 'date',
            'expected_at' => 'date',
            'approved_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<PurchaseOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    /**
     * Total cost of everything ordered.
     */
    protected function subtotal(): Attribute
    {
        return Attribute::get(fn (): float => round(
            $this->lines->sum(fn (PurchaseOrderLine $line) => $line->line_total),
            2,
        ));
    }

    /**
     * True once every line has been fully delivered.
     */
    protected function isFullyReceived(): Attribute
    {
        return Attribute::get(fn (): bool => $this->lines->isNotEmpty()
            && $this->lines->every(fn (PurchaseOrderLine $line) => $line->outstanding <= 0));
    }

    protected function hasReceipts(): Attribute
    {
        return Attribute::get(fn (): bool => $this->lines->contains(
            fn (PurchaseOrderLine $line) => $line->quantity_received > 0
        ));
    }

    #[Scope]
    protected function withStatus(Builder $query, ?string $status): void
    {
        $query->when(filled($status), fn (Builder $query) => $query->where('status', $status));
    }

    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when(filled($term), function (Builder $query) use ($term): void {
            $query->where(function (Builder $query) use ($term): void {
                $query->where('number', 'like', "%{$term}%")
                    ->orWhere('notes', 'like', "%{$term}%")
                    ->orWhereHas('supplier', fn (Builder $q) => $q->where('name', 'like', "%{$term}%"));
            });
        });
    }

    /**
     * Sequential order number in the form PO-2026-0001.
     */
    public static function nextNumber(): string
    {
        $prefix = 'PO-'.Carbon::now()->year.'-';

        $last = static::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last ? ((int) substr((string) $last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
