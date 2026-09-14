<?php

namespace App\Models\SalesCrm;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sales CRM employee manager mapping (`cm_employee_managers`).
 *
 * @property int $id
 * @property int $employee_id
 * @property int $manager_id
 * @property-read Employee|null $employee
 * @property-read Employee|null $manager
 */
#[Fillable([
    'employee_id',
    'manager_id',
])]
class EmployeeManager extends Model
{
    protected $connection = 'salescrm';

    protected $table = 'employee_managers';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employee_id' => 'integer',
            'manager_id' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }
}
