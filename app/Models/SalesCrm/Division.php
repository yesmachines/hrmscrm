<?php

namespace App\Models\SalesCrm;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Sales CRM division (`cm_divisions`).
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property int $status
 */
#[Fillable(['name', 'code', 'status'])]
class Division extends Model
{
    protected $connection = 'salescrm';

    protected $table = 'divisions';
}
