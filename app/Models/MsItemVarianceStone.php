<?php

namespace App\Models;

use Database\Factories\MsItemVarianceStoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $row_id
 * @property int $item_variance_id
 * @property int|null $shape_id
 * @property int|null $position_id
 * @property int|null $pcs
 * @property string|null $carat_per_pcs
 * @property string|null $total_carat
 * @property string|null $size
 * @property int $is_deleted
 * @property Carbon|null $created_date
 * @property string|null $created_by
 * @property Carbon|null $modified_date
 * @property string|null $modified_by
 * @property Carbon|null $deleted_date
 * @property string|null $deleted_by
 * @property-read MsShape|null $shape
 * @property-read MsPosition|null $position
 */
#[Fillable([
    'item_variance_id',
    'shape_id',
    'position_id',
    'pcs',
    'carat_per_pcs',
    'total_carat',
    'size',
    'is_deleted',
    'created_date',
    'created_by',
    'modified_date',
    'modified_by',
    'deleted_date',
    'deleted_by',
])]
class MsItemVarianceStone extends Model
{
    /** @use HasFactory<MsItemVarianceStoneFactory> */
    use HasFactory;

    /**
     * The connection name for the model.
     *
     * @var string|null
     */
    protected $connection = 'third';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'msitemvariancestone';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'row_id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * @param  Builder<MsItemVarianceStone>  $query
     * @return Builder<MsItemVarianceStone>
     */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', 0);
    }

    /**
     * @return BelongsTo<MsItemVariance, $this>
     */
    public function variance(): BelongsTo
    {
        return $this->belongsTo(MsItemVariance::class, 'item_variance_id', 'row_id');
    }

    /**
     * @return BelongsTo<MsShape, $this>
     */
    public function shape(): BelongsTo
    {
        return $this->belongsTo(MsShape::class, 'shape_id', 'row_id');
    }

    /**
     * @return BelongsTo<MsPosition, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(MsPosition::class, 'position_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'item_variance_id' => 'integer',
            'shape_id' => 'integer',
            'position_id' => 'integer',
            'pcs' => 'integer',
            'carat_per_pcs' => 'decimal:3',
            'total_carat' => 'decimal:3',
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
            'deleted_date' => 'datetime',
        ];
    }
}
