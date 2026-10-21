<?php

namespace App\Models;

use Database\Factories\SpkDiamondDiamondFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $spk_diamond_id
 * @property int $trdiamond_id
 * @property int $is_deleted
 */
#[Fillable([
    'spk_diamond_id',
    'trdiamond_id',
    'is_deleted',
    'created_date',
    'created_by',
])]
class SpkDiamondDiamond extends Model
{
    /** @use HasFactory<SpkDiamondDiamondFactory> */
    use HasFactory;

    protected $connection = 'third';

    protected $table = 'spk_diamond_diamond';

    public $timestamps = false;

    /**
     * @param  Builder<SpkDiamondDiamond>  $query
     * @return Builder<SpkDiamondDiamond>
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'spk_diamond_id' => 'integer',
            'trdiamond_id' => 'integer',
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
        ];
    }
}
