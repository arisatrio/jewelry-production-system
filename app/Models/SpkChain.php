<?php

namespace App\Models;

use Database\Factories\SpkChainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $line_id
 * @property int $row_id
 * @property int|null $mschain_id
 * @property string|null $category
 * @property string|null $item
 * @property string|null $color
 * @property string|null $size
 * @property string|null $fulfillment_status
 * @property int|null $finishing_id
 * @property int $is_deleted
 * @property Carbon|null $created_date
 * @property string|null $created_by
 * @property Carbon|null $modified_date
 * @property string|null $modified_by
 * @property Carbon|null $deleted_date
 * @property string|null $deleted_by
 * @property-read Production|null $production
 * @property-read MsChain|null $msChain
 * @property-read FinishingHandmade|null $finishing
 */
#[Fillable([
    'row_id',
    'mschain_id',
    'category',
    'item',
    'color',
    'size',
    'fulfillment_status',
    'finishing_id',
    'is_deleted',
    'created_date',
    'created_by',
    'modified_date',
    'modified_by',
    'deleted_date',
    'deleted_by',
])]
class SpkChain extends Model
{
    /** @use HasFactory<SpkChainFactory> */
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
    protected $table = 'spkchains';

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
     * @param  Builder<SpkChain>  $query
     * @return Builder<SpkChain>
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
     * @return BelongsTo<MsChain, $this>
     */
    public function msChain(): BelongsTo
    {
        return $this->belongsTo(MsChain::class, 'mschain_id', 'row_id');
    }

    /**
     * @return BelongsTo<FinishingHandmade, $this>
     */
    public function finishing(): BelongsTo
    {
        return $this->belongsTo(FinishingHandmade::class, 'finishing_id', 'row_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'row_id' => 'integer',
            'mschain_id' => 'integer',
            'finishing_id' => 'integer',
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
            'deleted_date' => 'datetime',
        ];
    }
}
