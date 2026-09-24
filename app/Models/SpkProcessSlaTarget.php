<?php

namespace App\Models;

use Database\Factories\SpkProcessSlaTargetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $process_key
 * @property int $working_days
 * @property string|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SpkProcessSlaTarget extends Model
{
    /** @use HasFactory<SpkProcessSlaTargetFactory> */
    use HasFactory;

    /**
     * The connection name for the model.
     *
     * @var string|null
     */
    protected $connection = 'third';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'process_key',
        'working_days',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'working_days' => 'integer',
        ];
    }
}
