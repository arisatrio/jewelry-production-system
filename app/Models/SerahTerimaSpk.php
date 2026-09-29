<?php

namespace App\Models;

use Database\Factories\SerahTerimaSpkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
 * @property list<array{spkRowId: int, spkNo: string, type: string, item: string, description: string, customer: string, targetDate: string}> $items
 * @property string|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SerahTerimaSpk extends Model
{
    /** @use HasFactory<SerahTerimaSpkFactory> */
    use HasFactory;

    /**
     * The connection name for the model.
     *
     * @var string|null
     */
    protected $connection = 'third';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'serah_terima_spk';

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
        'created_by',
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
