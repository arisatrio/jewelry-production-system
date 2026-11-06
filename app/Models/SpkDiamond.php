<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $row_id
 * @property string $doc_no
 * @property Carbon $tanggal
 * @property int $spk_row_id
 * @property string|null $spk_no
 * @property int $jumlah_baris
 * @property string|null $status
 * @property int|null $diamondmounting_id
 * @property list<int>|null $allocation_row_ids
 * @property list<array<string, mixed>>|null $items
 * @property int $is_deleted
 * @property Carbon|null $created_date
 * @property string|null $created_by
 * @property Carbon|null $modified_date
 * @property string|null $modified_by
 * @property Carbon|null $deleted_date
 * @property string|null $deleted_by
 */
class SpkDiamond extends Model
{
    protected $connection = 'third';

    protected $table = 'spk_diamond';

    protected $primaryKey = 'row_id';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'doc_no',
        'tanggal',
        'spk_row_id',
        'spk_no',
        'jumlah_baris',
        'status',
        'diamondmounting_id',
        'allocation_row_ids',
        'items',
        'is_deleted',
        'created_date',
        'created_by',
        'modified_date',
        'modified_by',
        'deleted_date',
        'deleted_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'spk_row_id' => 'integer',
            'jumlah_baris' => 'integer',
            'diamondmounting_id' => 'integer',
            'allocation_row_ids' => 'array',
            'items' => 'array',
            'is_deleted' => 'integer',
            'created_date' => 'datetime',
            'modified_date' => 'datetime',
            'deleted_date' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Production, $this>
     */
    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class, 'spk_row_id', 'row_id');
    }
}
