<?php

namespace App\Models;

use App\Support\PolishFinishedGoodApprovalService;
use Database\Factories\PolishFinishedGoodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $row_id
 * @property string|null $doc_no
 * @property string|null $process_name
 * @property Carbon|null $date_from
 * @property int|null $spk_id
 * @property int|null $craftsman_id
 * @property Carbon|null $date_to
 * @property string|null $start_weight
 * @property string|null $finish_weight
 * @property string|null $shrink
 * @property string|null $status_item
 * @property string|null $status
 * @property Carbon|null $send_craftsman_date
 * @property Carbon|null $received_craftsman_date
 * @property string|null $notes
 * @property string|null $qty_stone
 * @property int $is_from_new_system
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
    'process_name',
    'date_from',
    'spk_id',
    'craftsman_id',
    'date_to',
    'start_weight',
    'finish_weight',
    'shrink',
    'status_item',
    'status',
    'send_craftsman_date',
    'received_craftsman_date',
    'notes',
    'qty_stone',
    'is_from_new_system',
    'is_deleted',
    'created_date',
    'created_by',
    'modified_date',
    'modified_by',
    'deleted_date',
    'deleted_by',
])]
class PolishFinishedGood extends Model
{
    /** @use HasFactory<PolishFinishedGoodFactory> */
    use HasFactory;

    public const STATUS_TO_CRAFTSMAN = 'PFG010';

    public const STATUS_FROM_CRAFTSMAN = 'PFG020';

    public const STATUS_TO_PPIC = 'PFG030';

    public const STATUS_TO_JB = 'PFG040';

    public const STATUS_DONE = 'PFGDONE';

    /**
     * @return list<string>
     */
    public static function statusItemOptions(): array
    {
        return ['OK', 'NOK'];
    }

    /**
     * @return list<string>
     */
    public static function processNameOptions(): array
    {
        return ['General', 'Reparation'];
    }

    public function canEditForm(): bool
    {
        return app(PolishFinishedGoodApprovalService::class)->canEditForm($this);
    }

    protected $connection = 'third';

    protected $table = 'polishfinishedgood';

    protected $primaryKey = 'row_id';

    public $timestamps = false;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_from_new_system' => 0,
    ];

    /**
     * @param  Builder<PolishFinishedGood>  $query
     * @return Builder<PolishFinishedGood>
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
        return app(PolishFinishedGoodApprovalService::class)->isDone($this);
    }

    public function statusLabel(): string
    {
        return app(PolishFinishedGoodApprovalService::class)->statusLabelFor($this);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'spk_id' => 'integer',
            'craftsman_id' => 'integer',
            'start_weight' => 'decimal:2',
            'finish_weight' => 'decimal:2',
            'shrink' => 'decimal:2',
            'date_from' => 'datetime',
            'date_to' => 'datetime',
            'send_craftsman_date' => 'datetime',
            'received_craftsman_date' => 'datetime',
            'is_from_new_system' => 'integer',
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
            'deleted_date' => 'datetime',
        ];
    }
}
