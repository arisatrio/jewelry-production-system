<?php

namespace App\Models;

use App\Support\PolishFrameApprovalService;
use Database\Factories\PolishFrameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $row_id
 * @property string|null $doc_no
 * @property Carbon|null $date_from
 * @property int|null $spk_id
 * @property int|null $craftsman_id
 * @property Carbon|null $date_to
 * @property string|null $start_weight
 * @property string|null $finish_weight
 * @property string|null $shrink
 * @property string|null $status_item
 * @property Carbon|null $send_craftsman_date
 * @property Carbon|null $received_craftsman_date
 * @property int|null $frame_id
 * @property string|null $status
 * @property string|null $notes
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
    'date_from',
    'spk_id',
    'craftsman_id',
    'date_to',
    'start_weight',
    'finish_weight',
    'shrink',
    'status_item',
    'send_craftsman_date',
    'received_craftsman_date',
    'frame_id',
    'status',
    'notes',
    'is_deleted',
    'created_date',
    'created_by',
    'modified_date',
    'modified_by',
    'deleted_date',
    'deleted_by',
])]
class PolishFrame extends Model
{
    /** @use HasFactory<PolishFrameFactory> */
    use HasFactory;

    public const STATUS_TO_CRAFTSMAN = 'PRK010';

    public const STATUS_FROM_CRAFTSMAN = 'PRK020';

    public const STATUS_TO_PPIC = 'PRK030';

    public const STATUS_TO_JB = 'PRK040';

    public const STATUS_DONE = 'PRKDONE';

    /**
     * @return list<string>
     */
    public static function statusItemOptions(): array
    {
        return ['OK', 'NOK'];
    }

    public function canEditForm(): bool
    {
        return app(PolishFrameApprovalService::class)->canEditForm($this);
    }

    protected $connection = 'third';

    protected $table = 'polishframe';

    protected $primaryKey = 'row_id';

    public $timestamps = false;

    /**
     * @param  Builder<PolishFrame>  $query
     * @return Builder<PolishFrame>
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
        return app(PolishFrameApprovalService::class)->isDone($this);
    }

    public function statusLabel(): string
    {
        return app(PolishFrameApprovalService::class)->statusLabelFor($this);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'spk_id' => 'integer',
            'craftsman_id' => 'integer',
            'frame_id' => 'integer',
            'start_weight' => 'decimal:2',
            'finish_weight' => 'decimal:2',
            'shrink' => 'decimal:2',
            'date_from' => 'datetime',
            'date_to' => 'datetime',
            'send_craftsman_date' => 'datetime',
            'received_craftsman_date' => 'datetime',
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
            'deleted_date' => 'datetime',
        ];
    }
}
