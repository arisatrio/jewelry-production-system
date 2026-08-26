<?php

namespace App\Models;

use Database\Factories\JewelCadRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $row_id
 * @property string|null $doc_no
 * @property string|null $operator
 * @property Carbon|null $trans_date
 * @property string|null $notes
 * @property string|null $status
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
    'operator',
    'trans_date',
    'notes',
    'status',
    'is_from_new_system',
    'is_deleted',
    'created_date',
    'created_by',
    'modified_date',
    'modified_by',
    'deleted_date',
    'deleted_by',
])]
class JewelCadRequest extends Model
{
    /** @use HasFactory<JewelCadRequestFactory> */
    use HasFactory;

    protected $connection = 'third';

    protected $table = 'requestjwcad';

    protected $primaryKey = 'row_id';

    protected $attributes = [
        'is_from_new_system' => 0,
    ];

    public $timestamps = false;

    /**
     * @param  Builder<JewelCadRequest>  $query
     * @return Builder<JewelCadRequest>
     */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', 0);
    }

    /**
     * @return HasMany<JewelCadRequestDetail, $this>
     */
    public function details(): HasMany
    {
        return $this->hasMany(JewelCadRequestDetail::class, 'row_id', 'row_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trans_date' => 'date',
            'is_from_new_system' => 'integer',
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
            'deleted_date' => 'datetime',
        ];
    }
}
