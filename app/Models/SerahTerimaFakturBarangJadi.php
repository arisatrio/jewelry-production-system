<?php

namespace App\Models;

use Database\Factories\SerahTerimaFakturBarangJadiFactory;
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
 * @property int $jumlah_spk
 * @property list<int> $spk_row_ids
 * @property list<array{
 *     spkRowId: int,
 *     spkNo: string,
 *     type: string,
 *     status: string,
 *     item: string,
 *     plu: string|null,
 *     location: string
 * }> $items
 * @property string $workflow_status
 * @property string|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $deleted_by
 */
class SerahTerimaFakturBarangJadi extends Model
{
    /** @use HasFactory<SerahTerimaFakturBarangJadiFactory> */
    use HasFactory, SoftDeletes;

    protected $connection = 'third';

    protected $table = 'serah_terima_faktur_barang_jadi';

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
        'jumlah_spk',
        'spk_row_ids',
        'items',
        'workflow_status',
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
            'jumlah_spk' => 'integer',
            'spk_row_ids' => 'array',
            'items' => 'array',
        ];
    }
}
