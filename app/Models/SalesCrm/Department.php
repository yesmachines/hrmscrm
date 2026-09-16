<?php

namespace App\Models\SalesCrm;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sales CRM department (`cm_departments`).
 *
 * @property int $id
 * @property int|null $organisation_id
 * @property string $name
 * @property string $code
 * @property int $status
 */
#[Fillable(['organisation_id', 'name', 'code', 'status'])]
class Department extends Model
{
    protected $connection = 'salescrm';

    protected $table = 'departments';

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'organisation_id' => 'integer',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'department_id');
    }
}
