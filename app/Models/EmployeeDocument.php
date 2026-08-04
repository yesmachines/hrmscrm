<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'employee_id',
    'document_type_id',
    'document_number',
    'document_title',
    'issue_date',
    'expiry_date',
    'remarks',
    'current_version',
    'created_by',
    'status',
    'document_template_id',
])]
class EmployeeDocument extends Model
{
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function documentTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(EmployeeDocumentHistory::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(EmployeeDocumentFile::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(DocumentReminder::class);
    }

    public function requestDetails(): HasMany
    {
        return $this->hasMany(EmployeeRequestDetail::class);
    }
}
