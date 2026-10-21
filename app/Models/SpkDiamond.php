<?php

namespace App\Models;

use Database\Factories\SpkDiamondFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $no_spk_diamond
 * @property int $spk_id
 * @property string|null $spk_no
 * @property string|null $dmd_no
 * @property Carbon $tanggal
 * @property string|null $weight_frame
 * @property string|null $weight_stone
 * @property string|null $weight_total
 * @property string|null $keterangan
 * @property string $status
 * @property Carbon|null $submitted_at
 * @property string|null $submitted_by
 * @property Carbon|null $completed_at
 * @property string|null $completed_by
 * @property int $is_deleted
 */
#[Fillable([
    'no_spk_diamond',
    'spk_id',
    'spk_no',
    'dmd_no',
    'tanggal',
    'weight_frame',
    'weight_stone',
    'weight_total',
    'keterangan',
    'status',
    'submitted_at',
    'submitted_by',
    'completed_at',
    'completed_by',
    'is_deleted',
    'created_date',
    'created_by',
    'modified_date',
    'modified_by',
    'deleted_date',
    'deleted_by',
])]
class SpkDiamond extends Model
{
    /** @use HasFactory<SpkDiamondFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_SUBMITTED = 'SUBMITTED';

    /** @deprecated Legacy typo persisted in some rows before normalization */
    public const LEGACY_STATUS_SUBMITTED_TYPO = 'SUMBITTED';

    public const STATUS_MOUNTING = 'MOUNTING';

    public const STATUS_DONE = 'DONE';

    protected $connection = 'third';

    protected $table = 'spk_diamond';

    public $timestamps = false;

    /**
     * @param  Builder<SpkDiamond>  $query
     * @return Builder<SpkDiamond>
     */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', 0);
    }

    /**
     * @param  Builder<SpkDiamond>  $query
     * @return Builder<SpkDiamond>
     */
    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_SUBMITTED,
            self::LEGACY_STATUS_SUBMITTED_TYPO,
        ]);
    }

    public function getRouteKeyName(): string
    {
        return 'no_spk_diamond';
    }

    /**
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?static
    {
        $query = static::query()->notDeleted();
        $value = (string) $value;

        $byDocumentNo = (clone $query)->where('no_spk_diamond', $value)->first();

        if ($byDocumentNo !== null) {
            return $byDocumentNo;
        }

        if (is_numeric($value)) {
            return $query->where('id', (int) $value)->first();
        }

        return null;
    }

    public function isDraft(): bool
    {
        return strtoupper(trim($this->status)) === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        $status = strtoupper(trim($this->status));

        return in_array($status, [self::STATUS_SUBMITTED, self::LEGACY_STATUS_SUBMITTED_TYPO], true);
    }

    /**
     * @return BelongsTo<Production, $this>
     */
    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class, 'spk_id', 'row_id');
    }

    /**
     * @return HasMany<SpkDiamondStone, $this>
     */
    public function stones(): HasMany
    {
        return $this->hasMany(SpkDiamondStone::class, 'spk_diamond_id');
    }

    /**
     * @return HasMany<SpkDiamondDiamond, $this>
     */
    public function diamonds(): HasMany
    {
        return $this->hasMany(SpkDiamondDiamond::class, 'spk_diamond_id');
    }

    /**
     * @return HasOne<DiamondMounting, $this>
     */
    public function diamondMounting(): HasOne
    {
        return $this->hasOne(DiamondMounting::class, 'spk_diamond_id', 'id')
            ->where('is_deleted', 0);
    }

    public function statusLabel(): string
    {
        return match (strtoupper(trim($this->status))) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SUBMITTED, self::LEGACY_STATUS_SUBMITTED_TYPO => 'Kirim ke produksi',
            self::STATUS_MOUNTING => 'Sedang proses pasang batu',
            self::STATUS_DONE => 'Selesai proses pasang batu',
            default => $this->status,
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'spk_id' => 'integer',
            'tanggal' => 'date',
            'weight_frame' => 'decimal:2',
            'weight_stone' => 'decimal:3',
            'weight_total' => 'decimal:3',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
            'deleted_date' => 'datetime',
        ];
    }
}
