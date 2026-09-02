<?php

namespace App\Models;

use Database\Factories\ResinFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $row_id
 * @property string|null $doc_no
 * @property string|null $operator
 * @property string|null $notes
 * @property Carbon|null $trans_date
 * @property int|null $spk_id
 * @property string|null $file_upload
 * @property string|null $status
 * @property int $is_deleted
 * @property Carbon|null $created_date
 * @property string|null $created_by
 * @property Carbon|null $modified_date
 * @property string|null $modified_by
 * @property Carbon|null $deleted_date
 * @property string|null $deleted_by
 */
#[Fillable([
    'doc_no',
    'operator',
    'notes',
    'trans_date',
    'spk_id',
    'file_upload',
    'status',
    'is_deleted',
    'created_date',
    'created_by',
    'modified_date',
    'modified_by',
    'deleted_date',
    'deleted_by',
])]
class Resin extends Model
{
    /** @use HasFactory<ResinFactory> */
    use HasFactory;

    public const STATUS_OPEN = 'RES010';

    public const STATUS_DONE = 'RESDONE';

    protected $connection = 'third';

    protected $table = 'resin';

    protected $primaryKey = 'row_id';

    public $timestamps = false;

    /**
     * @param  Builder<Resin>  $query
     * @return Builder<Resin>
     */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', 0);
    }

    /**
     * @return HasMany<ResinStone, $this>
     */
    public function stones(): HasMany
    {
        return $this->hasMany(ResinStone::class, 'row_id', 'row_id');
    }

    /**
     * @return HasMany<ResinDetail, $this>
     */
    public function details(): HasMany
    {
        return $this->hasMany(ResinDetail::class, 'row_id', 'row_id');
    }

    /**
     * @return BelongsTo<Production, $this>
     */
    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class, 'spk_id', 'row_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trans_date' => 'date',
            'spk_id' => 'integer',
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
            'deleted_date' => 'datetime',
        ];
    }
}
