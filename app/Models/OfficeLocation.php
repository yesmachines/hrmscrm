<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organisation_id', 'office_name', 'country_id', 'city', 'address'])]
class OfficeLocation extends Model
{
    public $timestamps = false;

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
