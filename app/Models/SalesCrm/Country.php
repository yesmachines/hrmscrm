<?php

namespace App\Models\SalesCrm;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Sales CRM country (`cm_countries`).
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property int $status
 */
#[Fillable(['name', 'code', 'status'])]
class Country extends Model
{
    protected $connection = 'salescrm';

    protected $table = 'countries';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'integer',
        ];
    }
}
