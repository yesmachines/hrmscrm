<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['employee_document_id', 'reminder_date', 'days_before', 'notification_sent'])]
class DocumentReminder extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'reminder_date' => 'datetime',
            'notification_sent' => 'datetime',
        ];
    }

    public function employeeDocument(): BelongsTo
    {
        return $this->belongsTo(EmployeeDocument::class);
    }
}
