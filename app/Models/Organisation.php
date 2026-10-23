<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['org_name', 'short_name', 'logo', 'status'])]
class Organisation extends Model
{
    use SoftDeletes;

    protected $connection = 'mysql';

    protected $table = 'organisations';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'integer',
        ];
    }

    public function officeLocations(): HasMany
    {
        return $this->hasMany(OfficeLocation::class);
    }

    public function documentTemplates(): HasMany
    {
        return $this->hasMany(DocumentTemplate::class);
    }
}
