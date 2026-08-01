<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'description', 'is_active'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        // Keep the slug in sync so categories always have a stable URL key.
        static::saving(function (self $category): void {
            if (blank($category->slug)) {
                $category->slug = static::uniqueSlug($category->name, $category->getKey());
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when(filled($term), function (Builder $query) use ($term): void {
            $query->where(function (Builder $query) use ($term): void {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        });
    }

    /**
     * Build a slug that does not collide with an existing category.
     */
    protected static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $suffix = 2;

        // Soft deleted rows still hold their slug, so they must be considered
        // or restoring one would collide with a newer category.
        while (static::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
