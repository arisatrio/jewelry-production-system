<?php

namespace App\Models;

use Database\Factories\MsChainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $row_id
 * @property string $chain_category
 * @property string $item
 * @property string $item_code
 * @property string $length
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
    'chain_category',
    'item',
    'item_code',
    'length',
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
class MsChain extends Model
{
    /** @use HasFactory<MsChainFactory> */
    use HasFactory;

    public const CATEGORY_BRACELET = 'Bracelet Chain';

    public const CATEGORY_PENDANT = 'Pendant Chain';

    /**
     * @var string|null
     */
    protected $connection = 'third';

    /**
     * @var string
     */
    protected $table = 'mschain';

    /**
     * @var string
     */
    protected $primaryKey = 'row_id';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @param  Builder<MsChain>  $query
     * @return Builder<MsChain>
     */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', 0);
    }

    /**
     * @param  Builder<MsChain>  $query
     * @return Builder<MsChain>
     */
    public function scopeWhereIdentity(
        Builder $query,
        string $chainCategory,
        string $item,
        string $itemCode,
        string $length,
    ): Builder {
        return $query
            ->where('chain_category', $chainCategory)
            ->where('item', $item)
            ->where('item_code', $itemCode)
            ->where('length', $length);
    }

    public static function composeItemName(string $item, string $itemCode, string $length): string
    {
        $lengthLabel = rtrim(rtrim(number_format((float) $length, 2, '.', ''), '0'), '.');

        return "{$item} - {$itemCode} - {$lengthLabel} cm";
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'length' => 'decimal:2',
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
            'deleted_date' => 'datetime',
        ];
    }
}
