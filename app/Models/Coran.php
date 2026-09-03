<?php

namespace App\Models;

use Database\Factories\CoranFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $row_id
 * @property string|null $doc_no
 * @property Carbon|null $trans_date
 * @property int|null $craftsman_id
 * @property string|null $submit_material_rosegold
 * @property string|null $submit_material_whitegold
 * @property string|null $submit_material_yellowgold
 * @property string|null $result_material_rosegold
 * @property string|null $result_material_whitegold
 * @property string|null $result_material_yellowgold
 * @property string|null $shrink
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
    'doc_no',
    'trans_date',
    'craftsman_id',
    'submit_material_rosegold',
    'submit_material_whitegold',
    'submit_material_yellowgold',
    'result_material_rosegold',
    'result_material_whitegold',
    'result_material_yellowgold',
    'shrink',
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
class Coran extends Model
{
    /** @use HasFactory<CoranFactory> */
    use HasFactory;

    public const STATUS_DONE = 'CORDONE';

    protected $connection = 'third';

    protected $table = 'coran';

    protected $primaryKey = 'row_id';

    public $timestamps = false;

    /**
     * @param  Builder<Coran>  $query
     * @return Builder<Coran>
     */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', 0);
    }

    /**
     * @return HasMany<CoranSpk, $this>
     */
    public function details(): HasMany
    {
        return $this->hasMany(CoranSpk::class, 'row_id', 'row_id');
    }

    public function isDone(): bool
    {
        return filled($this->status)
            && strtoupper(trim((string) $this->status)) === self::STATUS_DONE;
    }

    public function statusLabel(): string
    {
        if ($this->isDone()) {
            return 'Done';
        }

        return filled($this->status) ? trim((string) $this->status) : 'Open';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trans_date' => 'date',
            'craftsman_id' => 'integer',
            'submit_material_rosegold' => 'decimal:3',
            'submit_material_whitegold' => 'decimal:3',
            'submit_material_yellowgold' => 'decimal:3',
            'result_material_rosegold' => 'decimal:3',
            'result_material_whitegold' => 'decimal:3',
            'result_material_yellowgold' => 'decimal:3',
            'shrink' => 'decimal:3',
            'weight' => 'decimal:3',
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
            'deleted_date' => 'datetime',
        ];
    }
}
