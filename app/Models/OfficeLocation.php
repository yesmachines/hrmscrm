<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['organisation_id', 'office_name', 'country_id', 'city', 'address', 'status'])]
class OfficeLocation extends Model
{
    use SoftDeletes;

    protected $connection = 'mysql';

    protected $table = 'office_locations';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'organisation_id' => 'integer',
            'country_id' => 'integer',
            'status' => 'integer',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
