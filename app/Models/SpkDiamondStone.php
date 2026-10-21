<?php

namespace App\Models;

use Database\Factories\SpkDiamondStoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $spk_diamond_id
 * @property int $stone_id
 * @property string|null $pcs
 * @property string|null $crt
 * @property string|null $notes
 * @property int|null $trstone_row_id
 * @property int $is_deleted
 */
#[Fillable([
    'spk_diamond_id',
    'stone_id',
    'pcs',
    'crt',
    'notes',
    'trstone_row_id',
    'is_deleted',
    'created_date',
    'created_by',
    'modified_date',
    'modified_by',
])]
class SpkDiamondStone extends Model
{
    /** @use HasFactory<SpkDiamondStoneFactory> */
    use HasFactory;

    protected $connection = 'third';

    protected $table = 'spk_diamond_stone';

    public $timestamps = false;

    /**
     * @param  Builder<SpkDiamondStone>  $query
     * @return Builder<SpkDiamondStone>
     */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', 0);
    }

    /**
     * @return BelongsTo<SpkDiamond, $this>
     */
    public function spkDiamond(): BelongsTo
    {
        return $this->belongsTo(SpkDiamond::class, 'spk_diamond_id');
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
            'spk_diamond_id' => 'integer',
            'stone_id' => 'integer',
            'pcs' => 'decimal:3',
            'crt' => 'decimal:3',
            'trstone_row_id' => 'integer',
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
        ];
    }
}
