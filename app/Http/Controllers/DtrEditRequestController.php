<?php

namespace App\Http\Controllers;

use App\Models\DtrEditRequest;
use App\Models\DtrSetting;
use App\Models\DtrUser;
use App\Models\Office;
use App\Models\Section;
use App\Models\User;
use App\Notifications\EditRequestApproved;
use App\Notifications\EditRequestRejected;
use App\Notifications\EditRequestSubmitted;
use Illuminate\Http\Request;

class DtrEditRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:time_correction,absent,holiday,wfh,special_order,travel_order,official_business,work_suspension,locator_slip,on_leave',
            'target_date' => 'nullable|date',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'field' => 'nullable|in:am_in,am_out,pm_in,pm_out',
            'old_value' => 'nullable|string|max:10',
            'new_value' => 'nullable|string|max:10',
            'reason' => 'required|string|max:500',
            'wfh_type' => 'nullable|in:whole_day,am,pm',
            'so_type' => 'nullable|in:whole_day,am,pm',
            'to_type' => 'nullable|in:whole_day,am,pm',
            'ob_type' => 'nullable|in:whole_day,am,pm',
            'ws_type' => 'nullable|in:whole_day,am,pm',
            'ls_type' => 'nullable|in:official,personal',
            'ls_whereabouts' => 'nullable|string|max:255',
            'ls_time_left' => 'nullable|date_format:H:i',
            'ls_time_returned' => 'nullable|date_format:H:i',
            'ls_no_return' => 'nullable|boolean',
            'so_number' => 'nullable|string|max:100',
            'to_number' => 'nullable|string|max:100',
            'ob_number' => 'nullable|string|max:100',
            'leave_hours' => 'nullable|numeric|min:0.5|max:24',
            'leave_type' => 'nullable|in:whole_day,am,pm',
        ]);

        $user = auth()->user();
        $employee = DtrUser::where('emp_code', $user->emp_code)->firstOrFail();

        $targetDates = $this->resolveTargetDates($data);

        $basePayload = [
            'employee_id' => $employee->id,
            'type' => $data['type'],
            'reason' => $data['reason'],
        ];

        $firstRequest = null;
        foreach ($targetDates as $targetDate) {
            $payload = $basePayload;
            $payload['target_date'] = $targetDate;

            if ($data['type'] === 'time_correction') {
                $request->validate([
                    'field' => 'required|in:am_in,am_out,pm_in,pm_out',
                    'new_value' => 'required|string|max:10',
                ]);
                $payload['field'] = $data['field'];
                $payload['old_value'] = $data['old_value'];
                $payload['new_value'] = $data['new_value'];
            } elseif ($data['type'] === 'wfh') {
                $payload['field'] = '';
                $payload['new_value'] = $data['wfh_type'] ?? 'whole_day';
            } elseif ($data['type'] === 'special_order') {
                $soData = $request->validate([
                    'so_number' => 'required|string|max:100',
                ]);
                $payload['field'] = $data['so_type'] ?? 'whole_day';
                $payload['new_value'] = $soData['so_number'];
            } elseif ($data['type'] === 'travel_order') {
                $toData = $request->validate([
                    'to_number' => 'required|string|max:100',
                ]);
                $payload['field'] = $data['to_type'] ?? 'whole_day';
                $payload['new_value'] = $toData['to_number'];
            } elseif ($data['type'] === 'official_business') {
                $obData = $request->validate([
                    'ob_number' => 'required|string|max:100',
                ]);
                $payload['field'] = $data['ob_type'] ?? 'whole_day';
                $payload['new_value'] = $obData['ob_number'];
            } elseif ($data['type'] === 'work_suspension') {
                $payload['field'] = '';
                $payload['new_value'] = $data['ws_type'] ?? 'whole_day';
            } elseif ($data['type'] === 'on_leave') {
                $leaveType = $request->input('leave_type', 'whole_day');
                $payload['field'] = $leaveType === 'whole_day' ? '' : $leaveType;
                $payload['new_value'] = (string) ($request->input('leave_hours', $leaveType === 'whole_day' ? '8' : '4'));
            } elseif ($data['type'] === 'locator_slip') {
                $lsData = $request->validate([
                    'ls_whereabouts' => 'required|string|max:255',
                ]);
                $payload['field'] = $data['ls_type'] ?? 'official';
                $payload['new_value'] = $lsData['ls_whereabouts'];
                $payload['ls_time_left'] = ($data['ls_time_left'] ?? '') ?: null;
                $payload['ls_time_returned'] = !empty($data['ls_no_return']) ? null : (($data['ls_time_returned'] ?? '') ?: null);
                $payload['ls_no_return'] = !empty($data['ls_no_return']);
            } else {
                $payload['field'] = '';
                $payload['new_value'] = '';
            }

            $editRequest = DtrEditRequest::create($payload);
            if (!$firstRequest) {
                $firstRequest = $editRequest;
            }
        }

        if ($firstRequest) {
            $this->notifySupervisors($firstRequest, $employee);
        }

        return back()->with('success', 'Edit DTR request is successfully sent to the supervisor.');
    }

    private function resolveTargetDates($data)
    {
        if (in_array($data['type'], ['special_order', 'travel_order', 'official_business'])
            && !empty($data['from_date'])
            && !empty($data['to_date'])) {
            $dates = [];
            $current = new \DateTime($data['from_date']);
            $end = new \DateTime($data['to_date']);
            while ($current <= $end) {
                $dates[] = $current->format('Y-m-d');
                $current->modify('+1 day');
            }
            return $dates;
        }
        return [$data['target_date']];
    }

    public function approve(DtrEditRequest $editRequest, Request $request)
    {
        $this->ensureSupervisor($editRequest);
        $this->preventSelfApproval($editRequest);

        $isDeletion = $editRequest->type === 'delete_request' && $editRequest->deletion_of_request_id;

        $editRequest->update([
            'status' => 'approved',
            'reviewer_id' => $this->getSupervisorDtrUserId(),
            'reviewed_at' => now(),
        ]);

        if ($isDeletion) {
            $originalRequest = DtrEditRequest::find($editRequest->deletion_of_request_id);
            if ($originalRequest) {
                $originalRequest->delete();
            }
        }

        $this->notifyEmployee($editRequest, 'approved');

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }
        return back()->with('success', 'Edit request approved.');
    }

    public function batchApprove(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:dtr_edit_requests,id',
        ]);

        $approved = 0;
        $skipped = 0;
        foreach ($data['ids'] as $id) {
            $editRequest = DtrEditRequest::find($id);
            if (!$editRequest || $editRequest->status !== 'pending') continue;

            try {
                $this->ensureSupervisor($editRequest);
                $this->preventSelfApproval($editRequest);
            } catch (\Exception $e) {
                $skipped++;
                continue;
            }

            $isDeletion = $editRequest->type === 'delete_request' && $editRequest->deletion_of_request_id;

            $editRequest->update([
                'status' => 'approved',
                'reviewer_id' => $this->getSupervisorDtrUserId(),
                'reviewed_at' => now(),
            ]);

            if ($isDeletion) {
                $originalRequest = DtrEditRequest::find($editRequest->deletion_of_request_id);
                if ($originalRequest) {
                    $originalRequest->delete();
                }
            }

            $this->notifyEmployee($editRequest, 'approved');
            $approved++;
        }

        return back()->with('success', "$approved edit request(s) approved." . ($skipped > 0 ? " $skipped request(s) skipped (self-approval not allowed)." : ""));
    }

    public function reject(Request $request, DtrEditRequest $editRequest)
    {
        $this->ensureSupervisor($editRequest);
        $this->preventSelfApproval($editRequest);

        $data = $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $editRequest->update([
            'status' => 'rejected',
            'reviewer_id' => $this->getSupervisorDtrUserId(),
            'reviewed_at' => now(),
            'rejection_reason' => $request->input('rejection_reason'),
        ]);

        $this->notifyEmployee($editRequest, 'rejected');

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }
        return back()->with('success', 'Edit request rejected.');
    }

    public function requestDeletion(Request $request, DtrEditRequest $editRequest)
    {
        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $user = auth()->user();
        $employee = DtrUser::where('emp_code', $user->emp_code)->firstOrFail();

        if (!$employee || $editRequest->employee_id !== $employee->id) {
            abort(403, 'You can only request deletion of your own edit requests.');
        }

        if ($editRequest->status !== 'approved') {
            return back()->with('error', 'Only approved requests can be requested for deletion.');
        }

        $deletionRequest = DtrEditRequest::create([
            'employee_id' => $employee->id,
            'type' => 'delete_request',
            'target_date' => $editRequest->target_date,
            'field' => '',
            'old_value' => '',
            'new_value' => '',
            'reason' => $data['reason'],
            'deletion_of_request_id' => $editRequest->id,
        ]);

        $this->notifySupervisors($deletionRequest, $employee);

        return back()->with('success', 'Deletion request submitted for supervisor approval.');
    }

    public function destroy(DtrEditRequest $editRequest)
    {
        $user = auth()->user();

        if ($editRequest->status === 'approved' && !$user->is_super) {
            abort(403, 'Approved requests cannot be directly deleted. Use the deletion request process.');
        }

        $employee = DtrUser::where('emp_code', $user->emp_code)->first();

        if (!$user->is_super) {
            if (!$employee || $editRequest->employee_id !== $employee->id) {
                abort(403, 'You can only delete your own edit requests.');
            }
        }

        $editRequest->delete();

        return back()->with('success', 'Edit request deleted.');
    }

    public function markAsRead()
    {
        auth()->user()->unreadNotifications->markAsRead();
        return back();
    }

    public function markSingleAsRead($id)
    {
        $notification = auth()->user()->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
        }
        return response()->json(['success' => true]);
    }

    private function notifySupervisors(DtrEditRequest $editRequest, DtrUser $employee)
    {
        $section = Section::find($employee->section_id);
        if ($section) {
            if ($section->oic_id && $section->oic_id != $employee->id) {
                $oicDtr = DtrUser::find($section->oic_id);
                if ($oicDtr) {
                    $oicUser = User::where('emp_code', $oicDtr->emp_code)->first();
                    if ($oicUser) {
                        $oicUser->notify(new EditRequestSubmitted($editRequest));
                    }
                }
            } elseif ($section->supervisor_id && $section->supervisor_id != $employee->id) {
                $supervisorDtr = DtrUser::find($section->supervisor_id);
                if ($supervisorDtr) {
                    $supervisorUser = User::where('emp_code', $supervisorDtr->emp_code)->first();
                    if ($supervisorUser) {
                        $supervisorUser->notify(new EditRequestSubmitted($editRequest));
                    }
                }
            }
        }

        $office = Office::find($employee->office_id);
        if ($office) {
            if ($office->oic_id && $office->oic_id != $employee->id) {
                $oicDtr = DtrUser::find($office->oic_id);
                if ($oicDtr) {
                    $oicUser = User::where('emp_code', $oicDtr->emp_code)->first();
                    if ($oicUser) {
                        $oicUser->notify(new EditRequestSubmitted($editRequest));
                    }
                }
            } elseif ($office->supervisor_id && $office->supervisor_id != $employee->id) {
                $officeSupervisorDtr = DtrUser::find($office->supervisor_id);
                if ($officeSupervisorDtr) {
                    $officeSupervisorUser = User::where('emp_code', $officeSupervisorDtr->emp_code)->first();
                    if ($officeSupervisorUser) {
                        $officeSupervisorUser->notify(new EditRequestSubmitted($editRequest));
                    }
                }
            }
        }

        $isOfficeSupervisor = $office && Office::where('supervisor_id', $employee->id)->exists();

        if ($isOfficeSupervisor && $office) {
            if ($office->senior_manager_oic_id && $office->senior_manager_oic_id != $employee->id) {
                $smOicDtr = DtrUser::find($office->senior_manager_oic_id);
                if ($smOicDtr) {
                    $smOicUser = User::where('emp_code', $smOicDtr->emp_code)->first();
                    if ($smOicUser) {
                        $smOicUser->notify(new EditRequestSubmitted($editRequest));
                    }
                }
            } elseif ($office->senior_manager_id && $office->senior_manager_id != $employee->id) {
                $seniorManagerDtr = DtrUser::find($office->senior_manager_id);
                if ($seniorManagerDtr) {
                    $seniorManagerUser = User::where('emp_code', $seniorManagerDtr->emp_code)->first();
                    if ($seniorManagerUser) {
                        $seniorManagerUser->notify(new EditRequestSubmitted($editRequest));
                    }
                }
            }
        }

        if ($isOfficeSupervisor) {
            $ahUserId = DtrSetting::where('setting_key', 'agency_head_user_id')->value('setting_value');
            if ($ahUserId) {
                $ahUser = User::find($ahUserId);
                if ($ahUser && $ahUser->id != auth()->id()) {
                    $ahUser->notify(new EditRequestSubmitted($editRequest));
                }
            }
        }
    }

    private function notifyEmployee(DtrEditRequest $editRequest, $status)
    {
        $employeeUser = User::where('emp_code', $editRequest->employee->emp_code)->first();
        if (!$employeeUser) return;

        if ($status === 'approved') {
            $employeeUser->notify(new EditRequestApproved($editRequest));
        } else {
            $employeeUser->notify(new EditRequestRejected($editRequest));
        }
    }

    private function ensureSupervisor(DtrEditRequest $editRequest)
    {
        $user = auth()->user();
        if ($user->is_super) return;

        $supervisorId = $this->getSupervisorDtrUserId();
        if (!$supervisorId) {
            abort(403, 'You are not assigned as a supervisor.');
        }

        $employee = $editRequest->employee;

        $section = Section::find($employee->section_id);

        if ($section) {
            if ($section->oic_id) {
                if ($section->oic_id == $supervisorId) {
                    return;
                }
            } elseif ($section->supervisor_id == $supervisorId) {
                return;
            }
        }

        $office = Office::find($employee->office_id);

        if ($office) {
            if ($office->oic_id) {
                if ($office->oic_id == $supervisorId) {
                    return;
                }
            } elseif ($office->supervisor_id == $supervisorId) {
                return;
            }

            $isOfficeSupervisor = $office->supervisor_id == $employee->id;
            $isSectionSupervisor = $section && $section->supervisor_id == $employee->id;

            if ($isOfficeSupervisor || $isSectionSupervisor) {
                if ($office->senior_manager_oic_id) {
                    if ($office->senior_manager_oic_id == $supervisorId) {
                        return;
                    }
                } elseif ($office->senior_manager_id == $supervisorId) {
                    return;
                }
            }
        }

        abort(403, 'You are not the supervisor of this employee.');
    }

    private function preventSelfApproval(DtrEditRequest $editRequest)
    {
        $user = auth()->user();
        if ($user->is_super) return;

        $dtrUser = DtrUser::where('emp_code', $user->emp_code)->first();
        if ($dtrUser && $editRequest->employee_id === $dtrUser->id) {
            abort(403, 'You cannot approve or reject your own edit request.');
        }
    }

    private function getSupervisorDtrUserId()
    {
        $user = auth()->user();
        if ($user->is_super) return null;

        $dtrUser = DtrUser::where('emp_code', $user->emp_code)->first();
        return $dtrUser ? $dtrUser->id : null;
    }
}
