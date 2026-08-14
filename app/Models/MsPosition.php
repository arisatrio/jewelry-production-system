<?php

namespace App\Models;

use Database\Factories\MsPositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nama
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'nama',
])]
class MsPosition extends Model
{
    /** @use HasFactory<MsPositionFactory> */
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
    protected $table = 'msposition';

    /**
     * Resolve position id from an existing id or a new nama.
     */
    public static function resolveId(?int $positionId, ?string $positionNama): ?int
    {
        $nama = trim((string) $positionNama);

        if ($nama !== '') {
            $position = static::query()->firstOrCreate(
                ['nama' => $nama],
            );

            return (int) $position->id;
        }

        return $positionId;
    }

    /**
     * @return HasMany<MsItemVarianceStone, $this>
     */
    public function varianceStones(): HasMany
    {
        return $this->hasMany(MsItemVarianceStone::class, 'position_id');
    }

    /**
     * @return HasMany<SpkStone, $this>
     */
    public function spkStones(): HasMany
    {
        return $this->hasMany(SpkStone::class, 'position_id');
    }
}
