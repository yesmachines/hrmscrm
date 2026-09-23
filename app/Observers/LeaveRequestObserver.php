<?php

namespace App\Observers;

use App\Models\LeaveRequest;
use App\Services\SystemEventSyncService;

class LeaveRequestObserver
{
    public function __construct(
        protected SystemEventSyncService $syncService
    ) {}

    /**
     * Handle the LeaveRequest "saved" event (created or updated).
     */
    public function saved(LeaveRequest $leaveRequest): void
    {
        if ($leaveRequest->wasChanged('status') || $leaveRequest->wasRecentlyCreated) {
            if ($leaveRequest->status === 'approved') {
                $this->syncService->syncSingleLeave($leaveRequest);
            } else {
                $this->syncService->removeLeaveEvent($leaveRequest);
            }
        }
    }

    /**
     * Handle the LeaveRequest "deleted" event.
     */
    public function deleted(LeaveRequest $leaveRequest): void
    {
        $this->syncService->removeLeaveEvent($leaveRequest);
    }
}
