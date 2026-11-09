<?php

namespace App\Models;

use Database\Factories\StokRangkaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $doc_no
 * @property string|null $sku_id
 * @property string|null $category
 * @property string|null $item_name
 * @property string|null $variant
 * @property string|null $prd
 * @property Carbon|null $date_in
 * @property Carbon|null $date_out
 * @property string|null $gold_weight
 * @property string|null $gold_color
 * @property string|null $status
 * @property int $is_deleted
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $created_by
 * @property string|null $updated_by
 */
#[Fillable([
    'doc_no',
    'sku_id',
    'category',
    'item_name',
    'variant',
    'prd',
    'date_in',
    'date_out',
    'gold_weight',
    'gold_color',
    'status',
    'is_deleted',
    'created_by',
    'updated_by',
])]
class StokRangka extends Model
{
    /** @use HasFactory<StokRangkaFactory> */
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'stok_rangka';

    /**
     * @param  Builder<StokRangka>  $query
     * @return Builder<StokRangka>
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
        return $this->belongsTo(SkuMaster::class, 'sku_id', 'id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_in' => 'date',
            'date_out' => 'date',
            'is_deleted' => 'integer',
        ];
    }
}
