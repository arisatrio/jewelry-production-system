<?php

namespace App\Models;

use Database\Factories\ResinDetailFactory;
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
 * @property string|null $berat_resin
 * @property string|null $status_resin
 * @property string|null $catatan
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
    'berat_resin',
    'status_resin',
    'catatan',
    'is_deleted',
    'created_date',
    'created_by',
    'modified_date',
    'modified_by',
    'deleted_date',
    'deleted_by',
])]
class ResinDetail extends Model
{
    /** @use HasFactory<ResinDetailFactory> */
    use HasFactory;

    public const STATUS_OK = 'OK';

    public const STATUS_NOT_OK = 'NOT OK';

    /** @deprecated Legacy open status for existing rows */
    public const STATUS_OPEN = 'RES010';

    /** @deprecated Legacy done status for existing rows */
    public const STATUS_DONE = 'RESDONE';

    /**
     * @return list<string>
     */
    public static function inputStatuses(): array
    {
        return [
            self::STATUS_OK,
            self::STATUS_NOT_OK,
        ];
    }

    public static function normalizeInputStatus(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $status = trim((string) $value);

        if ($status === '' || $status === '—') {
            return null;
        }

        if (! in_array($status, self::inputStatuses(), true)) {
            return null;
        }

        return $status;
    }

    /**
     * @return list<string>
     */
    public static function allowedStatuses(): array
    {
        return [
            self::STATUS_OK,
            self::STATUS_NOT_OK,
            self::STATUS_OPEN,
            self::STATUS_DONE,
        ];
    }

    public static function isCompletedStatus(?string $status): bool
    {
        return in_array($status, [self::STATUS_OK, self::STATUS_DONE], true);
    }

    public static function isInProgressStatus(?string $status): bool
    {
        if (! filled($status)) {
            return true;
        }

        return in_array($status, [self::STATUS_NOT_OK, self::STATUS_OPEN], true);
    }

    protected $connection = 'third';

    protected $table = 'resindetails';

    protected $primaryKey = 'line_id';

    public $timestamps = false;

    /**
     * @param  Builder<ResinDetail>  $query
     * @return Builder<ResinDetail>
     */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', 0);
    }

    /**
     * @return BelongsTo<Resin, $this>
     */
    public function resin(): BelongsTo
    {
        return $this->belongsTo(Resin::class, 'row_id', 'row_id');
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
            'berat_resin' => 'decimal:3',
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
            'deleted_date' => 'datetime',
        ];
    }
}
