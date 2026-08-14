<?php

namespace App\Models;

use Database\Factories\ProductionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $row_id
 * @property string|null $spk_no
 * @property string|null $spk_type
 * @property string|null $request_order_no
 * @property int|null $ref_spk_id
 * @property string|null $customer_name
 * @property string|null $item_name
 * @property string|null $description
 * @property Carbon|null $order_date
 * @property int|null $work_estimated
 * @property Carbon|null $estimated_delivery_time
 * @property int|null $supplier_id
 * @property string|null $status_order
 * @property string|null $jwcad_3d
 * @property int|null $item_id
 * @property int|null $qty
 * @property string|null $satuan
 * @property string|null $diameter_length_ringsize
 * @property string|null $gold_weight
 * @property string|null $gold_color
 * @property string|null $gold_content
 * @property string|null $priority
 * @property int|null $item_type_id
 * @property int|null $item_variance_id
 * @property int|null $sku_id
 * @property int|null $category_prefix_id
 * @property string|null $notes
 * @property string $status
 * @property float|null $last_weight
 * @property string|null $frame_id
 * @property string|null $file_name
 * @property string|null $last_process
 * @property int $is_coran
 * @property int $is_finishinghandmade
 * @property int $is_polishframe
 * @property int $is_diamondmounting
 * @property int $is_polishfinishedgood
 * @property int $is_grafir
 * @property int $is_inprocess
 * @property int $is_deleted
 * @property Carbon|null $created_date
 * @property string|null $created_by
 * @property Carbon|null $modified_date
 * @property string|null $modified_by
 * @property Carbon|null $deleted_date
 * @property string|null $deleted_by
 */
#[Fillable([
    'spk_no',
    'spk_type',
    'request_order_no',
    'ref_spk_id',
    'customer_name',
    'item_name',
    'description',
    'order_date',
    'work_estimated',
    'estimated_delivery_time',
    'supplier_id',
    'status_order',
    'jwcad_3d',
    'item_id',
    'qty',
    'satuan',
    'diameter_length_ringsize',
    'gold_weight',
    'gold_color',
    'gold_content',
    'priority',
    'item_type_id',
    'item_variance_id',
    'sku_id',
    'category_prefix_id',
    'notes',
    'status',
    'last_weight',
    'frame_id',
    'file_name',
    'last_process',
    'is_coran',
    'is_finishinghandmade',
    'is_polishframe',
    'is_diamondmounting',
    'is_polishfinishedgood',
    'is_grafir',
    'is_inprocess',
    'is_deleted',
    'created_date',
    'created_by',
    'modified_date',
    'modified_by',
    'deleted_date',
    'deleted_by',
])]
class Production extends Model
{
    /** @use HasFactory<ProductionFactory> */
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
    protected $table = 'spk';

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
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'spk_no';
    }

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => '0',
        'is_coran' => 0,
        'is_finishinghandmade' => 0,
        'is_polishframe' => 0,
        'is_diamondmounting' => 0,
        'is_polishfinishedgood' => 0,
        'is_grafir' => 0,
        'is_inprocess' => 0,
        'is_deleted' => 0,
    ];

    /**
     * @param  Builder<Production>  $query
     * @return Builder<Production>
     */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', 0);
    }

    /**
     * @return HasMany<SpkStone, $this>
     */
    public function stones(): HasMany
    {
        return $this->hasMany(SpkStone::class, 'row_id', 'row_id');
    }

    /**
     * @return BelongsTo<MsItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(MsItem::class, 'item_id', 'row_id');
    }

    /**
     * @return BelongsTo<MsItemVariance, $this>
     */
    public function itemVariance(): BelongsTo
    {
        return $this->belongsTo(MsItemVariance::class, 'item_variance_id', 'row_id');
    }

    /**
     * @return BelongsTo<SkuMaster, $this>
     */
    public function sku(): BelongsTo
    {
        return $this->belongsTo(SkuMaster::class, 'sku_id', 'id');
    }

    /**
     * @return BelongsTo<SkuPrefixCategory, $this>
     */
    public function categoryPrefix(): BelongsTo
    {
        return $this->belongsTo(SkuPrefixCategory::class, 'category_prefix_id', 'id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'estimated_delivery_time' => 'date',
            'work_estimated' => 'integer',
            'ref_spk_id' => 'integer',
            'supplier_id' => 'integer',
            'item_id' => 'integer',
            'qty' => 'integer',
            'item_type_id' => 'integer',
            'item_variance_id' => 'integer',
            'sku_id' => 'integer',
            'category_prefix_id' => 'integer',
            'gold_weight' => 'decimal:2',
            'last_weight' => 'float',
            'is_coran' => 'integer',
            'is_finishinghandmade' => 'integer',
            'is_polishframe' => 'integer',
            'is_diamondmounting' => 'integer',
            'is_polishfinishedgood' => 'integer',
            'is_grafir' => 'integer',
            'is_inprocess' => 'integer',
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
            'deleted_date' => 'datetime',
        ];
    }
}
