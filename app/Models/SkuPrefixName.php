<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $prefix
 * @property string|null $description
 * @property int|null $usage_count
 * @property int|null $is_active
 */
#[Fillable([
    'name',
    'prefix',
    'description',
    'usage_count',
    'is_active',
])]
class SkuPrefixName extends Model
{
    protected $connection = 'second';

    protected $table = 'sku_prefix_names';

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
