<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $spk_allocation_id
 * @property int $stone_id
 * @property string|null $pcs
 * @property string|null $crt
 * @property string|null $notes
 * @property int $is_deleted
 */
#[Fillable([
    'spk_allocation_id',
    'stone_id',
    'pcs',
    'crt',
    'notes',
    'is_deleted',
    'created_date',
    'created_by',
    'modified_date',
    'modified_by',
])]
class SpkAllocationStone extends Model
{
    protected $connection = 'third';

    protected $table = 'spk_allocation_stone';

    public $timestamps = false;

    /**
     * @param  Builder<SpkAllocationStone>  $query
     * @return Builder<SpkAllocationStone>
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
     * @return BelongsTo<MsStone, $this>
     */
    public function stone(): BelongsTo
    {
        return $this->belongsTo(MsStone::class, 'stone_id', 'row_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'spk_allocation_id' => 'integer',
            'stone_id' => 'integer',
            'pcs' => 'decimal:3',
            'crt' => 'decimal:3',
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
        ];
    }
}
