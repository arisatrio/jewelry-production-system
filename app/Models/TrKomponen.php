<?php

namespace App\Models;

use Database\Factories\TrKomponenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $row_id
 * @property Carbon|null $entry_date
 * @property Carbon|null $out_date
 * @property string|null $jenis_komponen
 * @property string|null $name
 * @property string|null $item_code
 * @property string|null $supplier
 * @property string|null $length
 * @property string|null $color
 * @property string|null $weight
 * @property int|null $spk_id
 * @property int|null $is_used
 * @property int|null $is_reserved
 * @property int $is_deleted
 * @property Carbon|null $created_date
 * @property string|null $created_by
 * @property Carbon|null $modified_date
 * @property string|null $modified_by
 * @property Carbon|null $deleted_date
 * @property string|null $deleted_by
 */
#[Fillable([
    'entry_date',
    'out_date',
    'jenis_komponen',
    'name',
    'item_code',
    'supplier',
    'length',
    'color',
    'weight',
    'spk_id',
    'is_used',
    'is_reserved',
    'is_deleted',
    'created_date',
    'created_by',
    'modified_date',
    'modified_by',
    'deleted_date',
    'deleted_by',
])]
class TrKomponen extends Model
{
    /** @use HasFactory<TrKomponenFactory> */
    use HasFactory;

    /**
     * @var string|null
     */
    protected $connection = 'third';

    /**
     * @var string
     */
    protected $table = 'trkomponen';

    /**
     * @var string
     */
    protected $primaryKey = 'row_id';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @param  Builder<TrKomponen>  $query
     * @return Builder<TrKomponen>
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

    public function inventoryStatus(): string
    {
        if ((int) $this->is_used === 1) {
            return 'used';
        }

        if ((int) $this->is_reserved === 1) {
            return 'reserved';
        }

        return 'available';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'out_date' => 'date',
            'length' => 'decimal:2',
            'weight' => 'decimal:2',
            'spk_id' => 'integer',
            'is_used' => 'integer',
            'is_reserved' => 'integer',
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
            'deleted_date' => 'datetime',
        ];
    }
}
