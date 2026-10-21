<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $spk_id
 * @property string|null $spk_no
 * @property int $is_deleted
 */
#[Fillable([
    'spk_id',
    'spk_no',
    'is_deleted',
    'created_date',
    'created_by',
    'modified_date',
    'modified_by',
])]
class SpkAllocation extends Model
{
    protected $connection = 'third';

    protected $table = 'spk_allocation';

    public $timestamps = false;

    /**
     * @param  Builder<SpkAllocation>  $query
     * @return Builder<SpkAllocation>
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
        return $this->belongsTo(Production::class, 'spk_id', 'row_id');
    }

    /**
     * @return HasMany<SpkAllocationStone, $this>
     */
    public function stones(): HasMany
    {
        return $this->hasMany(SpkAllocationStone::class, 'spk_allocation_id');
    }

    /**
     * @return HasMany<SpkAllocationDiamond, $this>
     */
    public function diamonds(): HasMany
    {
        return $this->hasMany(SpkAllocationDiamond::class, 'spk_allocation_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'spk_id' => 'integer',
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
        ];
    }
}
