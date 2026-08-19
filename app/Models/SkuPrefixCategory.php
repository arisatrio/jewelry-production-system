<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $category
 * @property string $prefix
 * @property string|null $description
 * @property int $usage_count
 * @property int $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'category',
    'prefix',
    'description',
    'usage_count',
    'is_active',
])]
class SkuPrefixCategory extends Model
{
    /**
     * The connection name for the model.
     *
     * @var string|null
     */
    protected $connection = 'second';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sku_prefix_categories';

    /**
     * @param  Builder<SkuPrefixCategory>  $query
     * @return Builder<SkuPrefixCategory>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', 1);
    }

    /**
     * Display label for selectors.
     */
    public function displayName(): string
    {
        $category = trim((string) $this->category);
        $prefix = trim((string) $this->prefix);

        if ($category !== '' && $prefix !== '') {
            return strtoupper("{$category} ({$prefix})");
        }

        if ($category !== '') {
            return strtoupper($category);
        }

        if ($prefix !== '') {
            return strtoupper($prefix);
        }

        return '-';
    }

    /**
     * @return HasMany<SkuMaster, $this>
     */
    public function skus(): HasMany
    {
        return $this->hasMany(SkuMaster::class, 'category_prefix_id', 'id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'usage_count' => 'integer',
            'is_active' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
