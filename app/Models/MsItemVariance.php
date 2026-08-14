<?php

namespace App\Models;

use Database\Factories\MsItemVarianceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $row_id
 * @property int $item_id
 * @property string|null $name
 * @property string|null $description
 * @property string|null $diameter
 * @property string|null $dimensi
 * @property string|null $ring_size
 * @property string|null $diameter_length_ringsize
 * @property string|null $gold_weight
 * @property string|null $gold_color
 * @property string|null $jwcad_3d
 * @property string|null $image
 * @property int $is_deleted
 * @property Carbon|null $created_date
 * @property string|null $created_by
 * @property Carbon|null $modified_date
 * @property string|null $modified_by
 * @property Carbon|null $deleted_date
 * @property string|null $deleted_by
 */
#[Fillable([
    'item_id',
    'name',
    'description',
    'diameter',
    'dimensi',
    'ring_size',
    'diameter_length_ringsize',
    'gold_weight',
    'gold_color',
    'jwcad_3d',
    'image',
    'is_deleted',
    'created_date',
    'created_by',
    'modified_date',
    'modified_by',
    'deleted_date',
    'deleted_by',
])]
class MsItemVariance extends Model
{
    /** @use HasFactory<MsItemVarianceFactory> */
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
    protected $table = 'msitemvariance';

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
     * @param  Builder<MsItemVariance>  $query
     * @return Builder<MsItemVariance>
     */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', 0);
    }

    /**
     * @return BelongsTo<MsItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(MsItem::class, 'item_id', 'row_id');
    }

    /**
     * @return HasMany<MsItemVarianceStone, $this>
     */
    public function stones(): HasMany
    {
        return $this->hasMany(MsItemVarianceStone::class, 'item_variance_id', 'row_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'item_id' => 'integer',
            'gold_weight' => 'decimal:2',
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
            'deleted_date' => 'datetime',
        ];
    }
}
