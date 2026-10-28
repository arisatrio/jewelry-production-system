<?php

namespace App\Models;

use Database\Factories\SerahTerimaComponentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $doc_no
 * @property Carbon $tanggal
 * @property string|null $dari
 * @property string|null $untuk
 * @property string|null $diserahkan_oleh
 * @property string|null $diterima_oleh
 * @property string|null $diketahui_oleh
 * @property int $spk_row_id
 * @property int $jumlah_baris
 * @property list<int> $spk_component_line_ids
 * @property list<array{
 *     spkComponentLineId: int,
 *     spkNo: string,
 *     category: string,
 *     item: string,
 *     color: string,
 *     unitNo: string,
 *     componentSize: string,
 *     weight: string,
 *     customer: string
 * }> $items
 * @property string|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $deleted_by
 */
class SerahTerimaComponent extends Model
{
    /** @use HasFactory<SerahTerimaComponentFactory> */
    use HasFactory, SoftDeletes;

    protected $connection = 'third';

    protected $table = 'serah_terima_component';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'doc_no',
        'tanggal',
        'dari',
        'untuk',
        'diserahkan_oleh',
        'diterima_oleh',
        'diketahui_oleh',
        'spk_row_id',
        'jumlah_baris',
        'spk_component_line_ids',
        'items',
        'created_by',
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
            'spk_component_line_ids' => 'array',
            'items' => 'array',
        ];
    }
}
