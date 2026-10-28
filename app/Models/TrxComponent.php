<?php

namespace App\Models;

use Database\Factories\TrxComponentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $row_id
 * @property string|null $unit_no
 * @property int $mscomponent_id
 * @property string $source_type
 * @property int|null $parent_row_id
 * @property string $name
 * @property string|null $item_code
 * @property string $component_size
 * @property string|null $color
 * @property string|null $weight
 * @property string|null $supplier
 * @property string|null $purchase_price
 * @property Carbon|null $entry_date
 * @property Carbon|null $out_date
 * @property int|null $spk_id
 * @property int $is_used
 * @property string|null $notes
 * @property int $is_deleted
 * @property Carbon|null $created_date
 * @property string|null $created_by
 * @property Carbon|null $modified_date
 * @property string|null $modified_by
 * @property Carbon|null $deleted_date
 * @property string|null $deleted_by
 * @property-read MsComponent|null $msComponent
 * @property-read Production|null $production
 * @property-read TrxComponent|null $parent
 */
#[Fillable([
    'unit_no',
    'mscomponent_id',
    'source_type',
    'parent_row_id',
    'name',
    'item_code',
    'component_size',
    'color',
    'weight',
    'supplier',
    'purchase_price',
    'entry_date',
    'out_date',
    'spk_id',
    'is_used',
    'notes',
    'is_deleted',
    'created_date',
    'created_by',
    'modified_date',
    'modified_by',
    'deleted_date',
    'deleted_by',
])]
class TrxComponent extends Model
{
    /** @use HasFactory<TrxComponentFactory> */
    use HasFactory;

    public const SOURCE_PURCHASE = 'purchase';

    public const SOURCE_REMNANT = 'remnant';

    public const SOURCE_ADJUSTMENT = 'adjustment';

    /**
     * @var string|null
     */
    protected $connection = 'third';

    /**
     * @var string
     */
    protected $table = 'trxcomponent';

    /**
     * @var string
     */
    protected $primaryKey = 'row_id';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @param  Builder<TrxComponent>  $query
     * @return Builder<TrxComponent>
     */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', 0);
    }

    /**
     * @param  Builder<TrxComponent>  $query
     * @return Builder<TrxComponent>
     */
    public function scopeInStock(Builder $query): Builder
    {
        return $query
            ->whereNotNull('entry_date')
            ->whereNull('out_date');
    }

    /**
     * @return BelongsTo<MsComponent, $this>
     */
    public function msComponent(): BelongsTo
    {
        return $this->belongsTo(MsComponent::class, 'mscomponent_id', 'row_id');
    }

    /**
     * @return BelongsTo<TrxComponent, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_row_id', 'row_id');
    }

    /**
     * @return BelongsTo<Production, $this>
     */
    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class, 'spk_id', 'row_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mscomponent_id' => 'integer',
            'parent_row_id' => 'integer',
            'component_size' => 'decimal:2',
            'weight' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'entry_date' => 'date',
            'out_date' => 'date',
            'spk_id' => 'integer',
            'is_used' => 'integer',
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
            'deleted_date' => 'datetime',
        ];
    }
}
