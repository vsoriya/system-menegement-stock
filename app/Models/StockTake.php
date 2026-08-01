<?php

namespace App\Models;

use App\Enums\StockTakeStatus;
use Database\Factories\StockTakeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference',
    'created_by',
    'category_id',
    'status',
    'counted_at',
    'posted_at',
    'notes',
])]
class StockTake extends Model
{
    /** @use HasFactory<StockTakeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StockTakeStatus::class,
            'counted_at' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    /**
     * @return HasMany<StockTakeLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockTakeLine::class);
    }

    /**
     * How many lines someone has actually counted.
     */
    protected function countedLines(): Attribute
    {
        return Attribute::get(fn (): int => $this->lines
            ->filter(fn (StockTakeLine $line) => $line->counted_quantity !== null)
            ->count());
    }

    /**
     * Lines where the count differs from the system balance.
     */
    protected function varianceLines(): Attribute
    {
        return Attribute::get(fn (): int => $this->lines
            ->filter(fn (StockTakeLine $line) => $line->hasVariance())
            ->count());
    }

    #[Scope]
    protected function withStatus(Builder $query, ?string $status): void
    {
        $query->when(filled($status), fn (Builder $query) => $query->where('status', $status));
    }

    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when(filled($term), fn (Builder $query) => $query->where('reference', 'like', "%{$term}%"));
    }
}
