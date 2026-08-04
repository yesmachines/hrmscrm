<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['org_name', 'short_name', 'logo', 'status'])]
class Organisation extends Model
{
    public $timestamps = false;

    public function officeLocations(): HasMany
    {
        return $this->hasMany(OfficeLocation::class);
    }
}
