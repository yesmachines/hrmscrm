<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

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
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'dob_personal' => 'date',
            'dob_passport' => 'date',
            'total_experience' => 'float',
        ];
    }
}
