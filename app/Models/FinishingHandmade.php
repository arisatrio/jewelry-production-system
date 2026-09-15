<?php

namespace App\Models;

use Database\Factories\FinishingHandmadeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $row_id
 * @property string|null $doc_no
 * @property int|null $spk_id
 * @property string|null $process_name
 * @property int|null $craftsman_id
 * @property string|null $start_weight
 * @property string|null $finish_weight
 * @property string|null $submit_materialgold
 * @property string|null $result_materialgold
 * @property string|null $shrink
 * @property string|null $shrink_tolerance
 * @property Carbon|null $send_craftsman_date
 * @property Carbon|null $received_craftsman_date
 * @property string|null $item_category
 * @property string|null $notes
 * @property string|null $status
 * @property int $is_deleted
 * @property Carbon|null $created_date
 * @property string|null $created_by
 * @property Carbon|null $modified_date
 * @property string|null $modified_by
 * @property Carbon|null $deleted_date
 * @property string|null $deleted_by
 * @property int|null $koreksi_qc
 * @property string|null $keterangan_qc
 */
#[Fillable([
    'doc_no',
    'spk_id',
    'process_name',
    'craftsman_id',
    'start_weight',
    'finish_weight',
    'submit_materialgold',
    'result_materialgold',
    'shrink',
    'shrink_tolerance',
    'send_craftsman_date',
    'received_craftsman_date',
    'item_category',
    'notes',
    'status',
    'is_deleted',
    'created_date',
    'created_by',
    'modified_date',
    'modified_by',
    'deleted_date',
    'deleted_by',
    'koreksi_qc',
    'keterangan_qc',
])]
class FinishingHandmade extends Model
{
    /** @use HasFactory<FinishingHandmadeFactory> */
    use HasFactory;

    public const STATUS_OPEN = 'FHM010';

    public const STATUS_TO_CRAFTSMAN = 'FHM020';

    public const STATUS_TO_PPIC = 'FHM040';

    public const STATUS_DONE = 'FHMDONE';

    public const STATUS_REPARATION_OPEN = 'RFH010';

    public const STATUS_REPARATION_DONE = 'RFHDONE';

    /**
     * @return list<string>
     */
    public static function processNameOptions(): array
    {
        return ['Finishing', 'Handmade', 'Reparation'];
    }

    /**
     * @return list<string>
     */
    public static function itemCategoryOptions(): array
    {
        return [
            'Barang Kecil',
            'Barang Kecil - lvl 1',
            'Barang Kecil - lvl 2',
            'Barang Kecil - lvl 3',
            'Barang Besar',
            'Barang Besar - lvl 1',
            'Barang Besar - lvl 2',
            'Barang Besar - lvl 3',
        ];
    }

    public function canEditForm(): bool
    {
        return ! $this->isDone();
    }

    protected $connection = 'third';

    protected $table = 'finishinghandmade';

    protected $primaryKey = 'row_id';

    public $timestamps = false;

    /**
     * @param  Builder<FinishingHandmade>  $query
     * @return Builder<FinishingHandmade>
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

    public function isDone(): bool
    {
        $status = strtoupper(trim((string) ($this->status ?? '')));

        return in_array($status, [self::STATUS_DONE, self::STATUS_REPARATION_DONE], true);
    }

    public function statusLabel(): string
    {
        $status = strtoupper(trim((string) ($this->status ?? '')));

        if ($status === '' || $status === '-' || $status === 'OPEN' || $status === 'DRAFT') {
            return 'Open';
        }

        return match ($status) {
            self::STATUS_OPEN => 'Serahkan ke Loket',
            self::STATUS_TO_CRAFTSMAN, self::STATUS_REPARATION_OPEN => 'Serahkan ke Pengrajin',
            self::STATUS_TO_PPIC => 'Serahkan ke PPIC',
            self::STATUS_DONE, self::STATUS_REPARATION_DONE => 'Completed',
            default => filled($this->status) ? trim((string) $this->status) : 'Open',
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'spk_id' => 'integer',
            'craftsman_id' => 'integer',
            'start_weight' => 'decimal:3',
            'finish_weight' => 'decimal:3',
            'submit_materialgold' => 'decimal:3',
            'result_materialgold' => 'decimal:3',
            'shrink' => 'decimal:3',
            'shrink_tolerance' => 'decimal:2',
            'send_craftsman_date' => 'datetime',
            'received_craftsman_date' => 'datetime',
            'is_deleted' => 'integer',
            'koreksi_qc' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
            'deleted_date' => 'datetime',
        ];
    }
}
