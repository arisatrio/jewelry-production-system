<?php

namespace App\Models;

use Database\Factories\SkuMasterDiamondFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $line_id
 * @property int $row_id
 * @property int|null $grain
 * @property string|null $grade
 * @property string|null $diamond_type
 * @property string|null $no_sert
 * @property string|null $diameter
 * @property string|null $position
 * @property string|null $color
 * @property int|null $is_gia
 * @property int $is_deleted
 * @property Carbon|null $created_date
 * @property string|null $created_by
 * @property Carbon|null $modified_date
 * @property string|null $modified_by
 * @property-read SkuMaster|null $sku
 */
#[Fillable([
    'row_id',
    'grain',
    'grade',
    'diamond_type',
    'no_sert',
    'diameter',
    'position',
    'color',
    'is_gia',
    'is_deleted',
    'created_date',
    'created_by',
    'modified_date',
    'modified_by',
])]
class SkuMasterDiamond extends Model
{
    /** @use HasFactory<SkuMasterDiamondFactory> */
    use HasFactory;

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
    protected $table = 'sku_master_diamond';

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
     * @param  Builder<SkuMasterDiamond>  $query
     * @return Builder<SkuMasterDiamond>
     */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', 0);
    }

    /**
     * @return BelongsTo<SkuMaster, $this>
     */
    public function sku(): BelongsTo
    {
        return $this->belongsTo(SkuMaster::class, 'row_id', 'id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'row_id' => 'integer',
            'grain' => 'integer',
            'grade' => 'decimal:3',
            'is_gia' => 'integer',
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
        ];
    }
}
