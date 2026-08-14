<?php

namespace App\Models;

use Database\Factories\MsStoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $row_id
 * @property string|null $parcel
 * @property string|null $stone_size
 * @property string|null $crt
 * @property int|null $shape_id
 * @property string|null $name
 * @property string|null $mounting_rate
 * @property int $is_deleted
 * @property Carbon|null $created_date
 * @property string|null $created_by
 * @property Carbon|null $modified_date
 * @property string|null $modified_by
 * @property Carbon|null $deleted_date
 * @property string|null $deleted_by
 * @property-read MsShape|null $shape
 */
#[Fillable([
    'parcel',
    'stone_size',
    'crt',
    'shape_id',
    'name',
    'mounting_rate',
    'is_deleted',
    'created_date',
    'created_by',
    'modified_date',
    'modified_by',
    'deleted_date',
    'deleted_by',
])]
class MsStone extends Model
{
    /** @use HasFactory<MsStoneFactory> */
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
    protected $table = 'msstone';

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
     * @param  Builder<MsStone>  $query
     * @return Builder<MsStone>
     */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', 0);
    }

    /**
     * @return BelongsTo<MsShape, $this>
     */
    public function shape(): BelongsTo
    {
        return $this->belongsTo(MsShape::class, 'shape_id', 'row_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shape_id' => 'integer',
            'crt' => 'decimal:3',
            'mounting_rate' => 'decimal:2',
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
            'deleted_date' => 'datetime',
        ];
    }
}
