<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employee_document_id', 'action_type', 'remarks', 'done_by', 'action_on'])]
class EmployeeDocumentHistory extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'action_on' => 'datetime',
        ];
    }

    public function employeeDocument(): BelongsTo
    {
        return $this->belongsTo(EmployeeDocument::class);
    }
}
