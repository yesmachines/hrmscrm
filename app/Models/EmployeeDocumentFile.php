<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_document_id',
    'version_no',
    'file_path',
    'uploaded_by',
    'uploaded_date',
    'change_notes',
])]
class EmployeeDocumentFile extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'uploaded_date' => 'datetime',
        ];
    }

    public function employeeDocument(): BelongsTo
    {
        return $this->belongsTo(EmployeeDocument::class);
    }
}
