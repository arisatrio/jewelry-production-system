<?php

namespace App\Models;

use Database\Factories\ResinStoneFactory;
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
 * @property int|null $pcs
 * @property int|null $carat
 * @property string|null $size
 * @property int $is_deleted
 * @property Carbon|null $created_date
 * @property string|null $created_by
 * @property Carbon|null $modified_date
 * @property string|null $modified_by
 * @property Carbon|null $deleted_date
 * @property string|null $deleted_by
 */
#[Fillable([
    'row_id',
    'shape_id',
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
class ResinStone extends Model
{
    /** @use HasFactory<ResinStoneFactory> */
    use HasFactory;

    protected $connection = 'third';

    protected $table = 'resinstone';

    protected $primaryKey = 'line_id';

    public $timestamps = false;

    /**
     * @param  Builder<ResinStone>  $query
     * @return Builder<ResinStone>
     */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', 0);
    }

    /**
     * @return BelongsTo<Resin, $this>
     */
    public function resin(): BelongsTo
    {
        return $this->belongsTo(Resin::class, 'row_id', 'row_id');
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
            'row_id' => 'integer',
            'shape_id' => 'integer',
            'pcs' => 'integer',
            'carat' => 'integer',
            'size' => 'decimal:2',
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
            'deleted_date' => 'datetime',
        ];
    }
}
