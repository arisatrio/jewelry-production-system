<?php

namespace App\Models;

use Database\Factories\MsComponentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $row_id
 * @property string $component_category
 * @property string $item
 * @property string $item_code
 * @property string|null $item_name
 * @property string|null $description
 * @property string|null $photo
 * @property int $is_deleted
 * @property Carbon|null $created_date
 * @property string|null $created_by
 * @property Carbon|null $modified_date
 * @property string|null $modified_by
 * @property Carbon|null $deleted_date
 * @property string|null $deleted_by
 */
#[Fillable([
    'component_category',
    'item',
    'item_code',
    'item_name',
    'description',
    'photo',
    'is_deleted',
    'created_date',
    'created_by',
    'modified_date',
    'modified_by',
    'deleted_date',
    'deleted_by',
])]
class MsComponent extends Model
{
    /** @use HasFactory<MsComponentFactory> */
    use HasFactory;

    public const CATEGORY_KOMPONEN = 'Komponen';

    public const CATEGORY_STOPPER = 'Stopper';

    /**
     * @var string|null
     */
    protected $connection = 'third';

    /**
     * @var string
     */
    protected $table = 'mscomponent';

    /**
     * @var string
     */
    protected $primaryKey = 'row_id';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @param  Builder<MsComponent>  $query
     * @return Builder<MsComponent>
     */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', 0);
    }

    /**
     * @param  Builder<MsComponent>  $query
     * @return Builder<MsComponent>
     */
    public function scopeWhereIdentity(
        Builder $query,
        string $componentCategory,
        string $item,
        string $itemCode,
    ): Builder {
        return $query
            ->where('component_category', $componentCategory)
            ->where('item', $item)
            ->where('item_code', $itemCode);
    }

    public static function composeItemName(string $item, string $itemCode): string
    {
        return "{$item} - {$itemCode}";
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
            'deleted_date' => 'datetime',
        ];
    }
}
