<?php

namespace App\Models;

use Database\Factories\FinishingShrinkAllowanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $work_category
 * @property string $work_type
 * @property string $item_category
 * @property string $allowance_percent
 * @property string|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'work_category',
    'work_type',
    'item_category',
    'allowance_percent',
    'updated_by',
])]
class FinishingShrinkAllowance extends Model
{
    /** @use HasFactory<FinishingShrinkAllowanceFactory> */
    use HasFactory;

    protected $connection = 'third';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allowance_percent' => 'decimal:2',
        ];
    }
}
