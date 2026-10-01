<?php

namespace App\Models;

use Database\Factories\DiamondCrtMatrixFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $shape_id
 * @property string $crt_min
 * @property string $crt_max
 * @property int $sort_order
 * @property string|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MsShape|null $shape
 */
#[Fillable([
    'shape_id',
    'crt_min',
    'crt_max',
    'sort_order',
    'updated_by',
])]
class DiamondCrtMatrix extends Model
{
    /** @use HasFactory<DiamondCrtMatrixFactory> */
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
    protected $table = 'diamond_crt_matrix';

    /**
     * @return BelongsTo<MsShape, $this>
     */
    public function shape(): BelongsTo
    {
        return $this->belongsTo(MsShape::class, 'shape_id', 'row_id');
    }

    /**
     * @param  Builder<DiamondCrtMatrix>  $query
     * @return Builder<DiamondCrtMatrix>
     */
    public function scopeMatching(Builder $query, int $shapeId, float $crt): Builder
    {
        return $query->where('shape_id', $shapeId)
            ->where('crt_min', '<=', $crt)
            ->where('crt_max', '>=', $crt);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shape_id' => 'integer',
            'crt_min' => 'decimal:3',
            'crt_max' => 'decimal:3',
            'sort_order' => 'integer',
        ];
    }
}
