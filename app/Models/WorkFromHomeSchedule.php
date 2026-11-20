<?php

namespace App\Models;

use App\Models\SalesCrm\Employee;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'working_days',
    'requested_date',
    'status',
    'effective_from',
    'effective_to',
    'created_by',
])]
class WorkFromHomeSchedule extends Model
{
    protected $table = 'employee_wfh_schedules';

    protected function casts(): array
    {
        return [
            'working_days' => 'integer',
            'requested_date' => 'date',
            'status' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
