<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DtrEditRequest extends Model
{
    protected $fillable = [
        'employee_id', 'type', 'target_date', 'field', 'old_value', 'new_value',
        'reason', 'status', 'reviewer_id', 'reviewed_at', 'rejection_reason',
        'ls_time_left', 'ls_time_returned', 'ls_no_return', 'deletion_of_request_id',
    ];

    protected $casts = [
        'target_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(DtrUser::class, 'employee_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(DtrUser::class, 'reviewer_id');
    }

    public function deletionOf()
    {
        return $this->belongsTo(self::class, 'deletion_of_request_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForEmployee($query, $empCode)
    {
        $employeeId = DtrUser::where('emp_code', $empCode)->value('id');
        return $employeeId ? $query->where('employee_id', $employeeId) : $query->whereRaw('0=1');
    }

    public function scopeForPeriod($query, $year, $month)
    {
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);
        return $query->whereBetween('target_date', [$start, $end]);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeDeletionRequests($query)
    {
        return $query->where('type', 'delete_request');
    }

    public function scopeNotDeletionRequests($query)
    {
        return $query->where('type', '!=', 'delete_request');
    }
}
