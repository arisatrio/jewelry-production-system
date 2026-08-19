<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $stone_shape
 * @property string|null $prefix
 * @property string|null $description
 * @property int|null $usage_count
 * @property int|null $is_active
 */
#[Fillable([
    'stone_shape',
    'prefix',
    'description',
    'usage_count',
    'is_active',
])]
class SkuPrefixStoneShape extends Model
{
    protected $connection = 'second';

    protected $table = 'sku_prefix_stone_shapes';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'usage_count' => 'integer',
            'is_active' => 'integer',
        ];
    }
}
