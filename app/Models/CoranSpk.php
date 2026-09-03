<?php

namespace App\Models;

use Database\Factories\CoranSpkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $line_id
 * @property int $row_id
 * @property int $spk_id
 * @property string|null $weight
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
    'row_id',
    'spk_id',
    'weight',
    'status',
    'is_deleted',
    'created_date',
    'created_by',
    'modified_date',
    'modified_by',
    'deleted_date',
    'deleted_by',
])]
class CoranSpk extends Model
{
    /** @use HasFactory<CoranSpkFactory> */
    use HasFactory;

    public const STATUS_OK = 'OK';

    public const STATUS_NOK = 'NOK';

    /**
     * @return list<string>
     */
    public static function inputStatuses(): array
    {
        return [
            self::STATUS_OK,
            self::STATUS_NOK,
        ];
    }

    public static function normalizeInputStatus(mixed $status): ?string
    {
        if (! filled($status)) {
            return null;
        }

        $normalized = strtoupper(trim((string) $status));

        return match ($normalized) {
            'OK' => self::STATUS_OK,
            'NOK', 'NOT OK', 'NOTOK' => self::STATUS_NOK,
            default => $normalized,
        };
    }

    protected $connection = 'third';

    protected $table = 'coranspk';

    protected $primaryKey = 'line_id';

    public $timestamps = false;

    /**
     * @param  Builder<CoranSpk>  $query
     * @return Builder<CoranSpk>
     */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', 0);
    }

    /**
     * @return BelongsTo<Coran, $this>
     */
    public function coran(): BelongsTo
    {
        return $this->belongsTo(Coran::class, 'row_id', 'row_id');
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
            'row_id' => 'integer',
            'spk_id' => 'integer',
            'weight' => 'decimal:3',
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
            'deleted_date' => 'datetime',
        ];
    }
}
