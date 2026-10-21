<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $spk_allocation_id
 * @property int $trdiamond_id
 * @property int $is_deleted
 */
#[Fillable([
    'spk_allocation_id',
    'trdiamond_id',
    'is_deleted',
    'created_date',
    'created_by',
])]
class SpkAllocationDiamond extends Model
{
    protected $connection = 'third';

    protected $table = 'spk_allocation_diamond';

    public $timestamps = false;

    /**
     * @param  Builder<SpkAllocationDiamond>  $query
     * @return Builder<SpkAllocationDiamond>
     */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', 0);
    }

    /**
     * @return BelongsTo<SpkAllocation, $this>
     */
    public function allocation(): BelongsTo
    {
        return $this->belongsTo(SpkAllocation::class, 'spk_allocation_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'spk_allocation_id' => 'integer',
            'trdiamond_id' => 'integer',
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
        ];
    }
}
