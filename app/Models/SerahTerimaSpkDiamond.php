<?php

namespace App\Models;

use Database\Factories\SerahTerimaSpkDiamondFactory;
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
 * @property int $jumlah_dokumen
 * @property list<int> $spk_diamond_row_ids
 * @property list<array{
 *     spkDiamondRowId: int,
 *     docNo: string,
 *     spkNo: string,
 *     item: string,
 *     lineCount: int,
 *     tanggal: string
 * }> $items
 * @property string|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $deleted_by
 */
class SerahTerimaSpkDiamond extends Model
{
    /** @use HasFactory<SerahTerimaSpkDiamondFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var string|null
     */
    protected $connection = 'third';

    /**
     * @var string
     */
    protected $table = 'serah_terima_spk_diamond';

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
        'jumlah_dokumen',
        'spk_diamond_row_ids',
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
            'jumlah_dokumen' => 'integer',
            'spk_diamond_row_ids' => 'array',
            'items' => 'array',
        ];
    }
}
