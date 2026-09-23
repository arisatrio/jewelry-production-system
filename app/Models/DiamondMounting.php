<?php

namespace App\Models;

use App\Support\DiamondMountingApprovalService;
use Database\Factories\DiamondMountingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $row_id
 * @property string|null $doc_no
 * @property Carbon|null $trans_date
 * @property string|null $process_name
 * @property int|null $spk_id
 * @property string|null $weight_frame
 * @property string|null $weight_diamond
 * @property string|null $total_weigth_frame_diamond
 * @property string|null $mounting_return
 * @property string|null $mounting_shrink
 * @property string|null $weight_finish_goods
 * @property string|null $polish_shrink
 * @property int|null $craftman_id
 * @property int|null $setting_id
 * @property int|null $qc_id
 * @property string|null $notes
 * @property string|null $status
 * @property Carbon|null $send_craftsman_date
 * @property Carbon|null $received_craftsman_date
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
    'trans_date',
    'process_name',
    'spk_id',
    'weight_frame',
    'weight_diamond',
    'total_weigth_frame_diamond',
    'mounting_return',
    'mounting_shrink',
    'weight_finish_goods',
    'polish_shrink',
    'craftman_id',
    'setting_id',
    'qc_id',
    'notes',
    'status',
    'send_craftsman_date',
    'received_craftsman_date',
    'is_from_new_system',
    'is_deleted',
    'created_date',
    'created_by',
    'modified_date',
    'modified_by',
    'deleted_date',
    'deleted_by',
])]
class DiamondMounting extends Model
{
    /** @use HasFactory<DiamondMountingFactory> */
    use HasFactory;

    public const STATUS_TO_CRAFTSMAN = 'DMT010';

    public const STATUS_FROM_CRAFTSMAN = 'DMT020';

    public const STATUS_TO_PPIC = 'DMT030';

    public const STATUS_DONE = 'DMTDONE';

    /**
     * @return list<string>
     */
    public static function processNameOptions(): array
    {
        return ['Pasang Batu', 'Reparation'];
    }

    public function canEditForm(): bool
    {
        return app(DiamondMountingApprovalService::class)->canEditForm($this);
    }

    protected $connection = 'third';

    protected $table = 'diamondmounting';

    protected $primaryKey = 'row_id';

    public $timestamps = false;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_from_new_system' => 0,
    ];

    /**
     * @param  Builder<DiamondMounting>  $query
     * @return Builder<DiamondMounting>
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
        return app(DiamondMountingApprovalService::class)->isDone($this);
    }

    public function statusLabel(): string
    {
        return app(DiamondMountingApprovalService::class)->statusLabelFor($this);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'spk_id' => 'integer',
            'craftman_id' => 'integer',
            'setting_id' => 'integer',
            'qc_id' => 'integer',
            'weight_frame' => 'decimal:2',
            'weight_diamond' => 'decimal:3',
            'total_weigth_frame_diamond' => 'decimal:2',
            'mounting_return' => 'decimal:2',
            'mounting_shrink' => 'decimal:2',
            'weight_finish_goods' => 'decimal:2',
            'polish_shrink' => 'decimal:2',
            'trans_date' => 'date',
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
