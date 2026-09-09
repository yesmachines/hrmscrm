<?php

namespace App\Models;

use Database\Factories\DesignationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Designation in HRMS (`designations`).
 *
 * @property int $id
 * @property int $department_id
 * @property string $title
 * @property string $shortcode
 * @property int $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['department_id', 'title', 'shortcode', 'status'])]
class Designation extends Model
{
    /** @use HasFactory<DesignationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'department_id' => 'integer',
            'status' => 'integer',
        ];
    }
}
