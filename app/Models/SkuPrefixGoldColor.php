<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $gold_color
 * @property string|null $prefix
 * @property string|null $description
 * @property int|null $usage_count
 * @property int|null $is_active
 */
#[Fillable([
    'gold_color',
    'prefix',
    'description',
    'usage_count',
    'is_active',
])]
class SkuPrefixGoldColor extends Model
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
    protected $table = 'sku_prefix_gold_colors';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

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
