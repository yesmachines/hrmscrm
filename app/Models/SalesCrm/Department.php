<?php

namespace App\Models\SalesCrm;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

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
}
