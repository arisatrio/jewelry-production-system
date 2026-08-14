<?php

namespace App\Models;

use Database\Factories\SpkStoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $line_id
 * @property int $row_id
 * @property int|null $shape_id
 * @property int|null $position_id
 * @property int|null $pcs
 * @property string|null $carat
 * @property string|null $size
 * @property int $is_deleted
 * @property Carbon|null $created_date
 * @property string|null $created_by
 * @property Carbon|null $modified_date
 * @property string|null $modified_by
 * @property Carbon|null $deleted_date
 * @property string|null $deleted_by
 * @property-read Production|null $production
 * @property-read MsShape|null $shape
 * @property-read MsPosition|null $position
 */
#[Fillable([
    'row_id',
    'shape_id',
    'position_id',
    'pcs',
    'carat',
    'size',
    'is_deleted',
    'created_date',
    'created_by',
    'modified_date',
    'modified_by',
    'deleted_date',
    'deleted_by',
])]
class SpkStone extends Model
{
    /** @use HasFactory<SpkStoneFactory> */
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
    protected $table = 'spkstone';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'line_id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_deleted' => 0,
    ];

    /**
     * @param  Builder<SpkStone>  $query
     * @return Builder<SpkStone>
     */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', 0);
    }

    /**
     * @return BelongsTo<Production, $this>
     */
    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class, 'row_id', 'row_id');
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
            'row_id' => 'integer',
            'shape_id' => 'integer',
            'position_id' => 'integer',
            'pcs' => 'integer',
            'carat' => 'decimal:3',
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
            'deleted_date' => 'datetime',
        ];
    }
}
