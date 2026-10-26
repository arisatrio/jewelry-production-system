<?php

namespace App\Models;

use Database\Factories\SpkComponentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $line_id
 * @property int $row_id
 * @property string|null $category
 * @property string|null $item
 * @property string|null $color
 * @property string|null $size
 * @property int $is_deleted
 * @property Carbon|null $created_date
 * @property string|null $created_by
 * @property Carbon|null $modified_date
 * @property string|null $modified_by
 * @property Carbon|null $deleted_date
 * @property string|null $deleted_by
 * @property-read Production|null $production
 */
#[Fillable([
    'row_id',
    'category',
    'item',
    'color',
    'size',
    'is_deleted',
    'created_date',
    'created_by',
    'modified_date',
    'modified_by',
    'deleted_date',
    'deleted_by',
])]
class SpkComponent extends Model
{
    /** @use HasFactory<SpkComponentFactory> */
    use HasFactory;

    /**
     * @var string|null
     */
    protected $connection = 'third';

    /**
     * @var string
     */
    protected $table = 'spk_components';

    /**
     * @var string
     */
    protected $primaryKey = 'line_id';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_deleted' => 0,
    ];

    /**
     * @param  Builder<SpkComponent>  $query
     * @return Builder<SpkComponent>
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'row_id' => 'integer',
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
            'deleted_date' => 'datetime',
        ];
    }
}
