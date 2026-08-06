<?php

namespace App\Models\SalesCrm;

use App\Models\EmployeeProfile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sales CRM employee (`cm_employees`).
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $organisation_id
 * @property string $emp_num
 * @property string|null $employee_code
 * @property string|null $phone
 * @property string $designation
 * @property int|null $designation_id
 * @property string|null $employment_status
 * @property int|null $office_location_id
 * @property Carbon|null $joining_date
 * @property Carbon|null $resignation_date
 * @property string $division
 * @property string|null $image_url
 * @property int $status
 * @property bool $has_report
 * @property int|null $department_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Department|null $department
 */
#[Fillable([
    'user_id',
    'organisation_id',
    'emp_num',
    'employee_code',
    'phone',
    'designation',
    'designation_id',
    'employment_status',
    'office_location_id',
    'joining_date',
    'resignation_date',
    'division',
    'image_url',
    'status',
    'has_report',
    'department_id',
])]
class Employee extends Model
{
    protected $connection = 'salescrm';

    protected $table = 'employees';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joining_date' => 'date:Y-m-d',
            'resignation_date' => 'date:Y-m-d',
            'has_report' => 'boolean',
            'status' => 'integer',
            'organisation_id' => 'integer',
            'designation_id' => 'integer',
            'office_location_id' => 'integer',
            'department_id' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function profile(): ?EmployeeProfile
    {
        return EmployeeProfile::query()
            ->where('employee_id', $this->id)
            ->first();
    }

    /**
     * @param  Collection<int, self>|list<self>  $employees
     * @return Collection<int, EmployeeProfile>
     */
    public static function profilesFor(Collection|array $employees): Collection
    {
        $ids = collect($employees)->pluck('id')->filter()->all();

        if ($ids === []) {
            return collect();
        }

        return EmployeeProfile::query()
            ->whereIn('employee_id', $ids)
            ->get()
            ->keyBy('employee_id');
    }
}
