<?php

namespace App\Models;

use Database\Factories\JewelCadRequestDetailFactory;
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
 * @property string|null $material
 * @property int|null $qty
 * @property string|null $estimation_brj
 * @property string|null $notes
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
    'row_id',
    'spk_id',
    'material',
    'qty',
    'estimation_brj',
    'notes',
    'is_from_new_system',
    'is_deleted',
    'created_date',
    'created_by',
    'modified_date',
    'modified_by',
    'deleted_date',
    'deleted_by',
])]
class JewelCadRequestDetail extends Model
{
    /** @use HasFactory<JewelCadRequestDetailFactory> */
    use HasFactory;

    protected $connection = 'third';

    protected $table = 'requestjwcaddetails';

    protected $primaryKey = 'line_id';

    protected $attributes = [
        'is_from_new_system' => 0,
    ];

    public $timestamps = false;

    /**
     * @param  Builder<JewelCadRequestDetail>  $query
     * @return Builder<JewelCadRequestDetail>
     */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', 0);
    }

    /**
     * @return BelongsTo<JewelCadRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(JewelCadRequest::class, 'row_id', 'row_id');
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
            'qty' => 'integer',
            'estimation_brj' => 'decimal:3',
            'is_from_new_system' => 'integer',
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
            'deleted_date' => 'datetime',
        ];
    }
}
