<?php

namespace App\Models;

use Database\Factories\EmployeeProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property string|null $gender
 * @property Carbon|null $dob_personal
 * @property string|null $marital_status
 * @property string|null $nationality
 * @property string|null $religion
 * @property string|null $blood_group
 * @property string|null $personal_email
 * @property string|null $personal_mobile
 * @property string|null $address_uae
 * @property string|null $emergency_contact_name
 * @property string|null $emergency_relation
 * @property string|null $emergency_mobile
 * @property int|null $home_country
 * @property string|null $address_home
 * @property string|null $home_mobile
 * @property string|null $home_emergency_name
 * @property string|null $home_emergency_relation
 * @property string|null $home_emergency_mobile
 * @property string|null $visa_type
 * @property string|null $visa_from
 * @property Carbon|null $dob_passport
 * @property float|null $total_experience
 * @property string|null $highest_education
 * @property-read User|null $employee
 */
#[Fillable([
    'employee_id',
    'gender',
    'dob_personal',
    'marital_status',
    'nationality',
    'religion',
    'blood_group',
    'personal_email',
    'personal_mobile',
    'address_uae',
    'emergency_contact_name',
    'emergency_relation',
    'emergency_mobile',
    'home_country',
    'address_home',
    'home_mobile',
    'home_emergency_name',
    'home_emergency_relation',
    'home_emergency_mobile',
    'visa_type',
    'visa_from',
    'dob_passport',
    'total_experience',
    'highest_education',
])]
class EmployeeProfile extends Model
{
    /** @use HasFactory<EmployeeProfileFactory> */
    use HasFactory;

    public $timestamps = false;

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dob_personal' => 'date:Y-m-d',
            'dob_passport' => 'date:Y-m-d',
            'total_experience' => 'float',
            'home_country' => 'integer',
        ];
    }
}
