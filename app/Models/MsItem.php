<?php

namespace App\Models;

use Database\Factories\MsItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $row_id
 * @property string|null $name
 * @property int $is_deleted
 * @property Carbon|null $created_date
 * @property string|null $created_by
 * @property Carbon|null $modified_date
 * @property string|null $modified_by
 * @property Carbon|null $deleted_date
 * @property string|null $deleted_by
 */
#[Fillable([
    'name',
    'is_deleted',
    'created_date',
    'created_by',
    'modified_date',
    'modified_by',
    'deleted_date',
    'deleted_by',
])]
class MsItem extends Model
{
    /** @use HasFactory<MsItemFactory> */
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
    protected $table = 'msitem';

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
     * @param  Builder<MsItem>  $query
     * @return Builder<MsItem>
     */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', 0);
    }

    /**
     * @return HasMany<Production, $this>
     */
    public function productions(): HasMany
    {
        return $this->hasMany(Production::class, 'item_id', 'row_id');
    }

    /**
     * @return HasMany<MsItemVariance, $this>
     */
    public function variances(): HasMany
    {
        return $this->hasMany(MsItemVariance::class, 'item_id', 'row_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
            'deleted_date' => 'datetime',
        ];
    }
}
