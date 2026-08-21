<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $company_id
 * @property int|null $department_id
 * @property int|null $position_id
 * @property int|null $sales_id
 * @property string|null $nomor_pegawai
 * @property string|null $nama_lengkap
 * @property string|null $nama_panggilan
 * @property string|null $foto
 * @property string|null $penempatan
 * @property Carbon|null $tukar_off_eligible_from
 * @property int $is_deleted
 * @property string|null $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $created_by
 * @property string|null $updated_by
 */
#[Fillable([
    'company_id',
    'department_id',
    'position_id',
    'sales_id',
    'nomor_pegawai',
    'nama_lengkap',
    'nama_panggilan',
    'foto',
    'penempatan',
    'tukar_off_eligible_from',
    'is_deleted',
    'status',
    'created_by',
    'updated_by',
])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    public const DEPARTMENT_PRODUCTION = 7;

    public const STATUS_ACTIVE = 'active';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'employee';

    /**
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->where('is_deleted', 0);
    }

    /**
     * Active employees in the PRODUCTION department.
     *
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function scopeProductionActive(Builder $query): Builder
    {
        return $query
            ->notDeleted()
            ->where('department_id', self::DEPARTMENT_PRODUCTION)
            ->where('status', self::STATUS_ACTIVE);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'department_id' => 'integer',
            'position_id' => 'integer',
            'sales_id' => 'integer',
            'is_deleted' => 'integer',
            'tukar_off_eligible_from' => 'datetime',
        ];
    }
}
