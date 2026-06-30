<?php

namespace App\Notifications;

use App\Models\DtrEditRequest;
use Illuminate\Notifications\Notification;

class EditRequestRejected extends Notification
{
    public $editRequest;

    public function __construct(DtrEditRequest $editRequest)
    {
        $this->editRequest = $editRequest;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        $typeLabels = [
            'time_correction' => 'Time Correction',
            'absent' => 'Whole Day Absent',
            'halfday_am' => 'Halfday (AM)',
            'halfday_pm' => 'Halfday (PM)',
            'holiday' => 'Holiday',
            'wfh' => 'WFH',
            'special_order' => 'Special Order',
            'travel_order' => 'Travel Order',
            'delete_request' => 'Deletion Request',
        ];

        $employee = $this->editRequest->employee;

        return [
            'type' => 'edit_request_rejected',
            'edit_request_id' => $this->editRequest->id,
            'emp_code' => $employee->emp_code,
            'request_type' => $typeLabels[$this->editRequest->type] ?? $this->editRequest->type,
            'target_date' => $this->editRequest->target_date->format('Y-m-d'),
            'reason' => $this->editRequest->rejection_reason,
            'message' => 'Your ' . ($typeLabels[$this->editRequest->type] ?? $this->editRequest->type) . ' request for ' . $this->editRequest->target_date->format('M d, Y') . ' was rejected.' . ($this->editRequest->rejection_reason ? ' Reason: ' . $this->editRequest->rejection_reason : ''),
        ];
    }
}
