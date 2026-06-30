<?php

namespace App\Http\Controllers;

use App\Models\DtrDayOverride;
use App\Models\DtrEditRequest;
use App\Models\DtrMonthlyShare;
use App\Models\DtrUser;
use App\Models\DtrSetting;
use App\Models\GlobalHoliday;
use App\Models\IclockTransaction;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DtrController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $settings = DtrSetting::getSettings();
        $settings = $this->backupOriginalSchedule($settings);
        $settings = $this->applyFourDaySettings($settings);

        $isSupervisor = $this->checkIsSupervisor($user);
        $sectionSupervisorIds = [];
        $officeSupervisorIds = [];
        $canViewAll = $user->is_super;

        if ($canViewAll) {
            $employees = DtrUser::where('is_active', true)
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get();
        } else {
            if ($user->is_coa && $request->has('month') && $request->has('year')) {
                $cm = (int) $request->month;
                $cy = (int) $request->year;
                $isShared = DtrMonthlyShare::where('month', $cm)->where('year', $cy)->exists();
                if ($isShared) {
                    $canViewAll = true;
                    $employees = DtrUser::where('is_active', true)
                        ->orderBy('first_name')
                        ->orderBy('last_name')
                        ->get();
                }
            }

            if (!$canViewAll) {
                $ahUserId = $settings['agency_head_user_id'] ?? null;

                if ($ahUserId && (int) $ahUserId === $user->id) {
                    $osIds = \App\Models\Office::whereNotNull('supervisor_id')->pluck('supervisor_id')->toArray();
                    $ahDtr = DtrUser::where('emp_code', $user->emp_code)->first();
                    $ahOfficeId = $ahDtr ? $ahDtr->office_id : null;
                    $employees = DtrUser::where('is_active', true)
                        ->where(function ($q) use ($osIds, $ahOfficeId) {
                            $q->whereIn('id', $osIds);
                            if ($ahOfficeId) {
                                $q->orWhere('office_id', $ahOfficeId);
                            }
                        })
                        ->orderBy('first_name')
                        ->orderBy('last_name')
                        ->get();
                    $canViewAll = true;
                } else {
                    $dtrUser = DtrUser::where('emp_code', $user->emp_code)->first();

                    if ($dtrUser) {
                        $sectionSupervisorIds = Section::where('supervisor_id', $dtrUser->id)->pluck('id')->toArray();
                        $sectionOicIds = Section::where('oic_id', $dtrUser->id)->pluck('id')->toArray();
                        $officeSupervisorIds = \App\Models\Office::where('supervisor_id', $dtrUser->id)->pluck('id')->toArray();
                        $seniorManagerOicOfficeIds = \App\Models\Office::where('senior_manager_oic_id', $dtrUser->id)->pluck('id')->toArray();
                        $oicOfficeIds = \App\Models\Office::where('oic_id', $dtrUser->id)->pluck('id')->toArray();
                    }

                    $employeeQuery = DtrUser::where('is_active', true);

                    if (!empty($sectionSupervisorIds) || !empty($sectionOicIds) || !empty($officeSupervisorIds) || !empty($seniorManagerOicOfficeIds) || !empty($oicOfficeIds)) {
                        $employeeQuery->where(function ($q) use ($sectionSupervisorIds, $sectionOicIds, $officeSupervisorIds, $seniorManagerOicOfficeIds, $oicOfficeIds) {
                            if (!empty($sectionSupervisorIds)) {
                                $q->whereIn('section_id', $sectionSupervisorIds);
                            }
                            if (!empty($sectionOicIds)) {
                                $q->orWhereIn('section_id', $sectionOicIds);
                            }
                            if (!empty($officeSupervisorIds)) {
                                $q->orWhereIn('office_id', $officeSupervisorIds);
                            }
                            if (!empty($seniorManagerOicOfficeIds)) {
                                $q->orWhereIn('office_id', $seniorManagerOicOfficeIds);
                            }
                            if (!empty($oicOfficeIds)) {
                                $q->orWhereIn('office_id', $oicOfficeIds);
                            }
                        });
                        $canViewAll = true;
                    } else {
                        $employeeQuery->where('emp_code', $user->emp_code);
                    }

                    $employees = $employeeQuery->orderBy('first_name')->orderBy('last_name')->get();
                }
            }
        }

        $dtrData = null;
        $month = null;
        $year = null;
        $monthName = null;
        $daysInMonth = null;
        $presentDays = null;
        $totalMinutes = null;
        $totalLate = null;
        $totalUndertime = null;
        $employee = null;
        $approvedRequests = collect();

        if ($request->has('month') && $request->has('year')) {
            $month = (int) $request->month;
            $year = (int) $request->year;

            if ($user->is_coa) {
                $isShared = DtrMonthlyShare::where('month', $month)->where('year', $year)->exists();

                if (!$isShared) {
                    return redirect()->route('dtr.index')
                        ->with('coa_not_shared', 'DTRs for ' . date('F Y', mktime(0, 0, 0, $month, 1, $year)) . ' have not been shared with COA yet.');
                }
            }

            $bioId = $request->emp;
            if (!$bioId && !$canViewAll) {
                $bioId = $user->emp_code;
            }
            if ($bioId) {
                $employee = DtrUser::where('emp_code', $bioId)
                    ->where('is_active', true)
                    ->first();

                if ($employee) {
                    $targetUser = \App\Models\User::where('emp_code', $bioId)->first();
                    $targetIsAgencyHead = $targetUser && ($settings['agency_head_user_id'] ?? null) && (int) $settings['agency_head_user_id'] === $targetUser->id;
                    if (!$targetIsAgencyHead && $targetUser) {
                        $ahName = $settings['agency_head_name'] ?? '';
                        if ($ahName) {
                            $nameWords = array_filter(explode(' ', preg_replace('/[^a-zA-Z0-9 ]/', '', $ahName)), function ($w) { return strlen(trim($w)) > 2; });
                            $match = true;
                            foreach ($nameWords as $word) {
                                if (stripos($targetUser->name, trim($word)) === false) { $match = false; break; }
                            }
                            $targetIsAgencyHead = $match;
                        }
                    }
                    if ($targetUser && ($targetUser->is_super || $targetUser->is_coa || $targetIsAgencyHead)) {
                        return redirect()->route('dtr.index', ['month' => $month, 'year' => $year])
                            ->with('error', 'DTR for this employee is restricted and cannot be viewed.');
                    }

                    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                    $monthName = date('F', mktime(0, 0, 0, $month, 1));

                    $dtrData = $this->computeDtr($bioId, $year, $month, $settings, $employee->default_work_week ?? null);

                    $empDefaultWW = $employee->default_work_week ?? (($settings['four_day_work_week'] ?? '0') === '1' ? '4-day' : '5-day');

                    $dtrData = $this->applyGlobalHolidays($dtrData, $year, $month, $empDefaultWW);

                    $approvedEdits = DtrEditRequest::with('employee')
                        ->forEmployee($bioId)
                        ->forPeriod($year, $month)
                        ->approved()
                        ->get();

                    foreach ($approvedEdits as $edit) {
                        $dayNum = (int) $edit->target_date->format('j');
                        $r = $dtrData[$dayNum]['remarks'] ?? '';
                        if (!isset($dtrData[$dayNum])) {
                            $dtrData[$dayNum] = [
                                'am_in' => '', 'am_out' => '', 'pm_in' => '', 'pm_out' => '',
                                'total_hours' => '', 'remarks' => '', 'has_punch' => false,
                                'edited_fields' => [],
                            ];
                        }
                        $dtrData[$dayNum]['is_edited'] = true;

                        switch ($edit->type) {
                            case 'time_correction':
                                $dtrData[$dayNum][$edit->field] = $edit->new_value;
                                $dtrData[$dayNum]['has_punch'] = true;
                                $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], [$edit->field]);
                                break;
                            case 'absent':
                                $dtrData[$dayNum]['am_in'] = 'ABSENT';
                                $dtrData[$dayNum]['am_out'] = 'ABSENT';
                                $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                                $dtrData[$dayNum]['pm_out'] = 'ABSENT';
                                $dtrData[$dayNum]['total_hours'] = '';
                                $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | Absent' : 'Absent';
                                $dtrData[$dayNum]['has_punch'] = true;
                                $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out', 'pm_in', 'pm_out']);
                                break;
                            case 'halfday_am':
                                $dtrData[$dayNum]['am_in'] = 'ABSENT';
                                $dtrData[$dayNum]['am_out'] = 'ABSENT';
                                if ($edit->field === 'am_out' && $edit->new_value) {
                                    $dtrData[$dayNum]['am_out'] = $edit->new_value;
                                }
                                $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | Halfday (AM)' : 'Halfday (AM)';
                                $dtrData[$dayNum]['has_punch'] = true;
                                $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                                break;
                            case 'halfday_pm':
                                $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                                $dtrData[$dayNum]['pm_out'] = 'ABSENT';
                                if ($edit->field === 'pm_in' && $edit->new_value) {
                                    $dtrData[$dayNum]['pm_in'] = $edit->new_value;
                                }
                                $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | Halfday (PM)' : 'Halfday (PM)';
                                $dtrData[$dayNum]['has_punch'] = true;
                                $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                                break;
                            case 'on_leave':
                                $leaveField = $edit->field ?: 'whole_day';
                                $leaveTypeLabel = $edit->reason ? ' (' . $edit->reason . ')' : '';
                                if ($leaveField === 'am') {
                                    $dtrData[$dayNum]['am_in'] = 'ON LEAVE';
                                    $dtrData[$dayNum]['am_out'] = 'ON LEAVE';
                                    $dtrData[$dayNum]['leave_credit_minutes'] = max(1, (int) ($edit->new_value ?: '4')) * 60;
                                    $dtrData[$dayNum]['remarks'] = ($r && strpos($r, 'On Leave') !== false) ? $r : (($r ?? '') ? $r . ' | On Leave (AM)' . $leaveTypeLabel : 'On Leave (AM)' . $leaveTypeLabel);
                                    $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                                } elseif ($leaveField === 'pm') {
                                    $dtrData[$dayNum]['pm_in'] = 'ON LEAVE';
                                    $dtrData[$dayNum]['pm_out'] = 'ON LEAVE';
                                    $dtrData[$dayNum]['leave_credit_minutes'] = max(1, (int) ($edit->new_value ?: '4')) * 60;
                                    $dtrData[$dayNum]['remarks'] = ($r && strpos($r, 'On Leave') !== false) ? $r : (($r ?? '') ? $r . ' | On Leave (PM)' . $leaveTypeLabel : 'On Leave (PM)' . $leaveTypeLabel);
                                    $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                                } else {
                                    $dtrData[$dayNum]['am_in'] = 'ON LEAVE';
                                    $dtrData[$dayNum]['am_out'] = 'ON LEAVE';
                                    $dtrData[$dayNum]['pm_in'] = 'ON LEAVE';
                                    $dtrData[$dayNum]['pm_out'] = 'ON LEAVE';
                                    $dtrData[$dayNum]['total_hours'] = sprintf('%02d:00', max(1, (int) ($edit->new_value ?: '8')));
                                    $dtrData[$dayNum]['remarks'] = ($r && strpos($r, 'On Leave') !== false) ? $r : (($r ?? '') ? $r . ' | On Leave' . $leaveTypeLabel : 'On Leave' . $leaveTypeLabel);
                                    $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out', 'pm_in', 'pm_out']);
                                }
                                $dtrData[$dayNum]['has_punch'] = true;
                                break;
                            case 'holiday':
                                $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | Holiday' : 'Holiday';
                                $dtrData[$dayNum]['has_punch'] = true;
                                $dtrData[$dayNum]['is_holiday'] = true;
                                break;
                            case 'wfh':
                                $dtrData[$dayNum]['has_punch'] = true;
                                $wfhType = $edit->new_value ?: 'whole_day';
                                if ($wfhType === 'am') {
                                    $dtrData[$dayNum]['am_in'] = 'WFH';
                                    $dtrData[$dayNum]['am_out'] = 'WFH';
                                    $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | WFH (AM)' : 'WFH (AM)';
                                    $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                                    $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                                } elseif ($wfhType === 'pm') {
                                    $dtrData[$dayNum]['pm_in'] = 'WFH';
                                    $dtrData[$dayNum]['pm_out'] = 'WFH';
                                    $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | WFH (PM)' : 'WFH (PM)';
                                    $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                                    $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                                } else {
                                    $dtrData[$dayNum]['am_in'] = 'WFH';
                                    $dtrData[$dayNum]['am_out'] = 'WFH';
                                    $dtrData[$dayNum]['pm_in'] = 'WFH';
                                    $dtrData[$dayNum]['pm_out'] = 'WFH';
                                    $dtrData[$dayNum]['is_wfh'] = true;
                                    $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | WFH' : 'WFH';
                                    $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '10:00' : '08:00';
                                    $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out', 'pm_in', 'pm_out']);
                                }
                                break;
                            case 'special_order':
                                $soType = $edit->field ?: 'whole_day';
                                $soNum = $edit->new_value;
                                $dtrData[$dayNum]['has_punch'] = true;
                                if ($soType === 'am') {
                                    $dtrData[$dayNum]['am_in'] = 'SO: ' . $soNum;
                                    $dtrData[$dayNum]['am_out'] = 'SO: ' . $soNum;
                                    $dtrData[$dayNum]['so_number'] = $soNum;
                                    $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | ' . 'Special Order' . ($soNum ? ': ' . $soNum : '') . ' (AM)' : 'Special Order' . ($soNum ? ': ' . $soNum : '') . ' (AM)';
                                    $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                                    $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                                } elseif ($soType === 'pm') {
                                    $dtrData[$dayNum]['pm_in'] = 'SO: ' . $soNum;
                                    $dtrData[$dayNum]['pm_out'] = 'SO: ' . $soNum;
                                    $dtrData[$dayNum]['so_number'] = $soNum;
                                    $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | ' . 'Special Order' . ($soNum ? ': ' . $soNum : '') . ' (PM)' : 'Special Order' . ($soNum ? ': ' . $soNum : '') . ' (PM)';
                                    $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                                    $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                                } else {
                                    $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | ' . 'Special Order' . ($soNum ? ': ' . $soNum : '') : 'Special Order' . ($soNum ? ': ' . $soNum : '');
                                    $dtrData[$dayNum]['so_number'] = $soNum;
                                    $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '10:00' : '08:00';
                                }
                                break;
                            case 'travel_order':
                                $toType = $edit->field ?: 'whole_day';
                                $toNum = $edit->new_value;
                                $dtrData[$dayNum]['has_punch'] = true;
                                if ($toType === 'am') {
                                    $dtrData[$dayNum]['am_in'] = 'TO: ' . $toNum;
                                    $dtrData[$dayNum]['am_out'] = 'TO: ' . $toNum;
                                    $dtrData[$dayNum]['to_number'] = $toNum;
                                    $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | ' . 'Travel Order' . ($toNum ? ': ' . $toNum : '') . ' (AM)' : 'Travel Order' . ($toNum ? ': ' . $toNum : '') . ' (AM)';
                                    $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                                    $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                                } elseif ($toType === 'pm') {
                                    $dtrData[$dayNum]['pm_in'] = 'TO: ' . $toNum;
                                    $dtrData[$dayNum]['pm_out'] = 'TO: ' . $toNum;
                                    $dtrData[$dayNum]['to_number'] = $toNum;
                                    $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | ' . 'Travel Order' . ($toNum ? ': ' . $toNum : '') . ' (PM)' : 'Travel Order' . ($toNum ? ': ' . $toNum : '') . ' (PM)';
                                    $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                                    $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                                } else {
                                    $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | ' . 'Travel Order' . ($toNum ? ': ' . $toNum : '') : 'Travel Order' . ($toNum ? ': ' . $toNum : '');
                                    $dtrData[$dayNum]['to_number'] = $toNum;
                                    $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '10:00' : '08:00';
                                }
                                break;
                            case 'official_business':
                                $obType = $edit->field ?: 'whole_day';
                                $obNum = $edit->new_value;
                                $dtrData[$dayNum]['has_punch'] = true;
                                if ($obType === 'am') {
                                    $dtrData[$dayNum]['ob_number'] = $obNum;
                                    $dtrData[$dayNum]['am_in'] = 'OB: ' . $obNum;
                                    $dtrData[$dayNum]['am_out'] = 'OB: ' . $obNum;
                                    $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | ' . 'Official Business' . ($obNum ? ': ' . $obNum : '') . ($edit->reason ? ' - ' . $edit->reason : '') . ' (AM)' : 'Official Business' . ($obNum ? ': ' . $obNum : '') . ($edit->reason ? ' - ' . $edit->reason : '') . ' (AM)';
                                    $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                                    $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                                } elseif ($obType === 'pm') {
                                    $dtrData[$dayNum]['ob_number'] = $obNum;
                                    $dtrData[$dayNum]['pm_in'] = 'OB: ' . $obNum;
                                    $dtrData[$dayNum]['pm_out'] = 'OB: ' . $obNum;
                                    $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | ' . 'Official Business' . ($obNum ? ': ' . $obNum : '') . ($edit->reason ? ' - ' . $edit->reason : '') . ' (PM)' : 'Official Business' . ($obNum ? ': ' . $obNum : '') . ($edit->reason ? ' - ' . $edit->reason : '') . ' (PM)';
                                    $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                                    $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                                } else {
                                    $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | ' . 'Official Business' . ($obNum ? ': ' . $obNum : '') . ($edit->reason ? ' - ' . $edit->reason : '') : 'Official Business' . ($obNum ? ': ' . $obNum : '') . ($edit->reason ? ' - ' . $edit->reason : '');
                                    $dtrData[$dayNum]['ob_number'] = $obNum;
                                    $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '10:00' : '08:00';
                                }
                                break;
                            case 'work_suspension':
                                $wsType = $edit->new_value ?: 'whole_day';
                                if ($wsType === 'am') {
                                    $dtrData[$dayNum]['am_in'] = 'ABSENT';
                                    $dtrData[$dayNum]['am_out'] = 'ABSENT';
                                    $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | Work Suspension (AM)' : 'Work Suspension (AM)';
                                    $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                                } elseif ($wsType === 'pm') {
                                    $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                                    $dtrData[$dayNum]['pm_out'] = 'ABSENT';
                                    $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | Work Suspension (PM)' : 'Work Suspension (PM)';
                                    $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                                } else {
                                    $dtrData[$dayNum]['am_in'] = 'ABSENT';
                                    $dtrData[$dayNum]['am_out'] = 'ABSENT';
                                    $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                                    $dtrData[$dayNum]['pm_out'] = 'ABSENT';
                                    $dtrData[$dayNum]['total_hours'] = '';
                                    $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | Work Suspension' : 'Work Suspension';
                                    $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out', 'pm_in', 'pm_out']);
                                }
                                $dtrData[$dayNum]['has_punch'] = true;
                                break;
                            case 'locator_slip':
                                $lsType = $edit->field ?: 'official';
                                $whereabouts = $edit->new_value;
                                $typeLabel = $lsType === 'personal' ? 'Personal' : 'Official';
                                $timeLeft = $edit->ls_time_left ? date('h:i A', strtotime($edit->ls_time_left)) : '';
                                $timeReturned = $edit->ls_no_return ? 'No Return' : ($edit->ls_time_returned ? date('h:i A', strtotime($edit->ls_time_returned)) : '');
                                $dtrData[$dayNum]['has_punch'] = true;
                                $details = array_filter([$whereabouts, $timeLeft ? "Left: $timeLeft" : null, $timeReturned ? "Ret: $timeReturned" : null]);
                                if ($lsType === 'personal') {
                                    $lsDuration = 0;
                                    if ($edit->ls_time_left && $edit->ls_time_returned && !$edit->ls_no_return) {
                                        $lsDuration = (strtotime($edit->ls_time_returned) - strtotime($edit->ls_time_left)) / 60;
                                    } elseif ($edit->ls_no_return && $edit->ls_time_left) {
                                        $pmEnd = $settings['pm_end'] ?? '17:00';
                                        $timeLeftTs = strtotime($edit->ls_time_left);
                                        $pmEndTs = strtotime($pmEnd);
                                        $lsDuration = ($pmEndTs - $timeLeftTs) / 60;
                                    }
                                    $existingTotal = $dtrData[$dayNum]['total_hours'] ?? '00:00';
                                    $parts = explode(':', $existingTotal);
                                    $existingMins = (int)($parts[0] ?? 0) * 60 + (int)($parts[1] ?? 0);
                                    $remainingMins = max(0, $existingMins - $lsDuration);
                                    $hours = floor($remainingMins / 60);
                                    $mins = round($remainingMins % 60);
                                    $dtrData[$dayNum]['total_hours'] = sprintf('%02d:%02d', $hours, $mins);
                                    $lsRemark = 'LS: ' . implode(' | ', $details) . ' (Personal)';
                                    if ($lsDuration > 0) {
                                        $lsRemark .= ' | LS Deduction: ' . gmdate('H:i', $lsDuration * 60);
                                    }
                                    $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | ' . $lsRemark : $lsRemark;
                                } else {
                                    $lsRemark = 'LS: ' . implode(' | ', $details) . ' (Official)';
                                    $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | ' . $lsRemark : $lsRemark;
                                    if ($edit->ls_no_return && $edit->ls_time_left) {
                                        $pmEnd = $settings['pm_end'] ?? '17:00';
                                        $timeLeftTs = strtotime($edit->ls_time_left);
                                        $pmEndTs = strtotime($pmEnd);
                                        $remainingMins = max(0, ($pmEndTs - $timeLeftTs) / 60);
                                        $existingTotal = $dtrData[$dayNum]['total_hours'] ?? '00:00';
                                        $parts = explode(':', $existingTotal);
                                        $existingMins = (int)($parts[0] ?? 0) * 60 + (int)($parts[1] ?? 0);
                                        $totalMins = $existingMins + $remainingMins;
                                        $hours = floor($totalMins / 60);
                                        $mins = round($totalMins % 60);
                                        $dtrData[$dayNum]['total_hours'] = sprintf('%02d:%02d', $hours, $mins);
                                    }
                                }
                                break;
                        }

                        if (in_array($edit->type, ['time_correction', 'halfday_am', 'halfday_pm'])) {
                            $day = $dtrData[$dayNum];
                            $hasSo = !empty($day['so_number']);
                            $hasTo = !empty($day['to_number']);
                            $hasOb = !empty($day['ob_number']);
                            $hasLs = strpos($day['remarks'] ?? '', 'LS:') !== false;
                            $isOnLeave = ($day['am_in'] ?? '') === 'ON LEAVE' || ($day['pm_in'] ?? '') === 'ON LEAVE';
                            if ($edit->type === 'time_correction') {
                                $dtrData[$dayNum]['total_hours'] = $this->recalcHours($day, $settings);
                                if (!$hasSo && !$hasTo && !$hasOb && !$hasLs && !$isOnLeave) {
                                    $dtrData[$dayNum]['remarks'] = $this->recalcRemarks($day, $settings);
                                }
                            } else {
                                $dtrData[$dayNum]['total_hours'] = $this->recalcHours($day, $settings);
                            }
                        }

                        if (in_array($edit->type, ['halfday_am', 'halfday_pm']) && strpos($dtrData[$dayNum]['remarks'] ?? '', 'Halfday') !== false) {
                            $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                        }
                    }

                    foreach ($dtrData as $dayNum => &$day) {
                        $isHalfday = strpos($day['remarks'] ?? '', '(AM)') !== false || strpos($day['remarks'] ?? '', '(PM)') !== false;
                        if (!$isHalfday) continue;
                        if (strpos($day['remarks'] ?? '', 'Halfday') === 0) continue;
                        $isAm = strpos($day['remarks'] ?? '', '(AM)') !== false;
                        $lateUt = '';
                        if ($isAm) {
                            if (!empty($day['pm_in']) && preg_match('/^\d/', $day['pm_in'])) {
                                $pmStartTS = strtotime($settings['pm_start'] ?? '13:00');
                                $pmInTS = strtotime($day['pm_in']);
                                if ($pmInTS > $pmStartTS) {
                                    $lateMins = ($pmInTS - $pmStartTS) / 60;
                                    $lateUt = 'Late: PM ' . gmdate('H:i', $lateMins * 60);
                                }
                            }
                        } else {
                            if (!empty($day['am_in']) && preg_match('/^\d/', $day['am_in'])) {
                                $amStartTS = strtotime($settings['am_start'] ?? '07:00');
                                $amLateThreshold = $amStartTS + ((int)($settings['am_start_flexi'] ?? 120)) * 60;
                                $amInTS = strtotime($day['am_in']);
                                if ($amInTS > $amLateThreshold) {
                                    $lateMins = ($amInTS - $amLateThreshold) / 60;
                                    $lateUt = 'Late: AM ' . gmdate('H:i', $lateMins * 60);
                                }
                            }
                        }
                        if (strpos($day['remarks'] ?? '', 'WFH') === false) {
                            $actualMins = $isAm
                                ? $this->computePunchMinutes($day['pm_in'] ?? '', $day['pm_out'] ?? '')
                                : $this->computePunchMinutes($day['am_in'] ?? '', $day['am_out'] ?? '');
                            $ww = $day['work_week_type'] ?? $empDefaultWW;
                            $expectedHalfMins = $ww === '4-day' ? 300 : 240;
                            $utMins = $expectedHalfMins - $actualMins;
                            if ($utMins > 0) {
                                $utStr = 'UT: ' . gmdate('H:i', $utMins * 60);
                                $lateUt = $lateUt ? $lateUt . ' | ' . $utStr : $utStr;
                            }
                        }
                        if ($lateUt) {
                            $day['remarks'] .= ' | ' . $lateUt;
                        }
                    }
                    unset($day);

                    $dayOverrides = DtrDayOverride::where('employee_id', $employee->id)
                        ->whereBetween('target_date', ["$year-$month-01", "$year-$month-$daysInMonth"])
                        ->get();

                    foreach ($dayOverrides as $override) {
                        $dayNum = (int) $override->target_date->format('j');
                        if (!isset($dtrData[$dayNum])) {
                            $dtrData[$dayNum] = ['am_in' => '', 'am_out' => '', 'pm_in' => '', 'pm_out' => '', 'total_hours' => '', 'remarks' => '', 'has_punch' => false];
                        }
                        $dtrData[$dayNum]['work_week_type'] = $override->work_week_type;
                    }

                    foreach ($dtrData as $dayNum => &$day) {
                        if (!isset($day['work_week_type'])) continue;
                        $schedule = $this->getScheduleForWorkWeek($day['work_week_type'], $settings);

                        $isSpecial = !empty($day['is_wfh']) || !empty($day['so_number']) || !empty($day['to_number']) || !empty($day['ob_number']) || !empty($day['is_holiday']) || !empty($day['is_work_suspension']) || (isset($day['remarks']) && (strpos($day['remarks'], 'LS:') !== false || strpos($day['remarks'], 'WFH') === 0 || strpos($day['remarks'], 'On Leave') !== false));

                        if (!$isSpecial) {
                            $day['remarks'] = $this->recalcRemarks($day, $settings, $schedule);
                        }

                        if ($isSpecial) {
                            if (isset($day['remarks']) && strpos($day['remarks'], 'LS:') !== false) {
                                // LS total_hours already set by LS case block
                            } elseif (isset($day['remarks']) && strpos($day['remarks'], 'On Leave') !== false) {
                                // On Leave: total_hours handled by recomputeHalfdayHours or set in switch
                            } else {
                                $isHalfday = strpos($day['remarks'] ?? '', '(AM)') !== false || strpos($day['remarks'] ?? '', '(PM)') !== false;
                                if ($isHalfday) {
                                    $day['total_hours'] = $day['work_week_type'] === '4-day' ? '05:00' : '04:00';
                                } else {
                                    $day['total_hours'] = $day['work_week_type'] === '4-day' ? '10:00' : '08:00';
                                }
                            }
                        } elseif (strpos($day['remarks'] ?? '', 'Halfday') !== false) {
                            $day['total_hours'] = $day['work_week_type'] === '4-day' ? '05:00' : '04:00';
                        }

                        $wfhLabel = $this->resolveWfhLabel($day);
                        if ($wfhLabel && !$isSpecial) {
                            $day['remarks'] = !empty($day['remarks']) ? $wfhLabel . ' | ' . $day['remarks'] : $wfhLabel;
                        }

                        if (!$isSpecial && (strpos($wfhLabel, '(AM)') !== false || strpos($wfhLabel, '(PM)') !== false)) {
                            $day['total_hours'] = $day['work_week_type'] === '4-day' ? '05:00' : '04:00';
                        }
                    }
                    unset($day);

                    $this->recomputeHalfdayHours($dtrData, $employee, $settings);

                    foreach ($dtrData as $dayNum => &$day) {
                        $ww = $day['work_week_type'] ?? $empDefaultWW;
                        $expectedMins = $ww === '4-day' ? 600 : 480;
                        $totalMins = 0;
                        if (!empty($day['total_hours'])) {
                            $parts = explode(':', $day['total_hours']);
                            $totalMins = (int)($parts[0] ?? 0) * 60 + (int)($parts[1] ?? 0);
                        }
                        $utMins = $totalMins > 0 ? max(0, $expectedMins - $totalMins) : 0;
                        $remarks = trim(preg_replace('/(?:^|\s*\|\s*)UT:\s*\d+:\d+/', '', $day['remarks'] ?? ''), ' |');
                        $parts = [];
                        if ($remarks !== '') $parts[] = $remarks;
                        if ($utMins > 0) $parts[] = 'UT: ' . gmdate('H:i', $utMins * 60);
                        $day['remarks'] = implode(' | ', $parts);
                    }
                    unset($day);

                    $presentDays = 0;
                    $totalMinutes = 0;
                    $totalLate = 0;
                    $totalUndertime = 0;

                    foreach ($dtrData as $dayNum => $day) {
                        $dow = date('N', strtotime(sprintf('%04d-%02d-%02d', $year, $month, $dayNum)));
                        $dayMaxDow = isset($day['work_week_type']) ? ($day['work_week_type'] === '4-day' ? 4 : 5) : ((isset($employee) && $employee->default_work_week === '4-day') ? 4 : (($settings['four_day_work_week'] ?? '0') === '1' ? 4 : ($settings['max_dow'] ?? 5)));
                        if (!empty($day['has_punch']) && $dow <= $dayMaxDow) {
                            $presentDays++;
                            if (!empty($day['total_hours'])) {
                                $parts = explode(':', $day['total_hours']);
                                $totalMinutes += (int) $parts[0] * 60 + (int) $parts[1];
                            }
                            if (!empty($day['remarks']) && strpos($day['remarks'], 'Late:') !== false) {
                                preg_match_all('/(?:AM|PM)\s+(\d+):(\d+)/', $day['remarks'], $m);
                                for ($i = 0; $i < count($m[0]); $i++) {
                                    $totalLate += (int) $m[1][$i] * 60 + (int) $m[2][$i];
                                }
                            }
                            if (!empty($day['remarks']) && strpos($day['remarks'], 'UT:') !== false) {
                                preg_match('/UT: (\d+):(\d+)/', $day['remarks'], $u);
                                if (isset($u[1])) {
                                    $totalUndertime += (int) $u[1] * 60 + (int) $u[2];
                                }
                            }
                        }
                    }

                    $approvedRequests = $approvedEdits->sortBy('target_date');
                }
            }
        }

        $isOwnDtr = $employee && isset($bioId) && $bioId === $user->emp_code;

        return view('dtr.index', compact(
            'employees', 'dtrData', 'month', 'year', 'monthName',
            'daysInMonth', 'presentDays', 'totalMinutes', 'totalLate',
            'totalUndertime', 'employee', 'settings', 'isOwnDtr', 'isSupervisor',
            'approvedRequests', 'canViewAll'
        ));
    }

    public function show(Request $request)
    {
        $user = auth()->user();
        $settings = DtrSetting::getSettings();
        $settings = $this->backupOriginalSchedule($settings);
        $settings = $this->applyFourDaySettings($settings);

        $dtrUser = DtrUser::where('emp_code', $user->emp_code)->first();
        $sectionSupervisorIds = $dtrUser
            ? Section::where('supervisor_id', $dtrUser->id)->pluck('id')->toArray()
            : [];
        $sectionOicIds = $dtrUser
            ? Section::where('oic_id', $dtrUser->id)->pluck('id')->toArray()
            : [];
        $officeSupervisorIds = $dtrUser
            ? \App\Models\Office::where('supervisor_id', $dtrUser->id)->pluck('id')->toArray()
            : [];
        $seniorManagerOicOfficeIds = $dtrUser
            ? \App\Models\Office::where('senior_manager_oic_id', $dtrUser->id)->pluck('id')->toArray()
            : [];
        $oicOfficeIds = $dtrUser
            ? \App\Models\Office::where('oic_id', $dtrUser->id)->pluck('id')->toArray()
            : [];
        $canViewAll = $user->is_super || !empty($sectionSupervisorIds) || !empty($sectionOicIds) || !empty($officeSupervisorIds) || !empty($seniorManagerOicOfficeIds) || !empty($oicOfficeIds);

        $ahUserId = $settings['agency_head_user_id'] ?? null;
        if (!$canViewAll && $ahUserId && (int) $ahUserId === $user->id) {
            $canViewAll = true;
        }

        if ($canViewAll) {
            $request->validate([
                'emp' => 'required|string',
            ]);
            $bioId = $request->emp;
        } else {
            $bioId = $user->emp_code;
        }

        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|between:2000,2100',
        ]);
        $month = (int) $request->month;
        $year = (int) $request->year;

        $employee = DtrUser::where('emp_code', $bioId)
            ->where('is_active', true)
            ->firstOrFail();

        if (!$user->is_super && $ahUserId && (int) $ahUserId === $user->id) {
            $osIds = \App\Models\Office::whereNotNull('supervisor_id')->pluck('supervisor_id')->toArray();
            $ahDtr = DtrUser::where('emp_code', $user->emp_code)->first();
            $ahOfficeId = $ahDtr ? $ahDtr->office_id : null;
            $allowed = in_array($employee->id, $osIds) || ($ahOfficeId && $employee->office_id == $ahOfficeId);
            if (!$allowed) {
                abort(403);
            }
        }

        if ($user->is_coa) {
            $isShared = DtrMonthlyShare::where('month', $month)->where('year', $year)->exists();

            if (!$isShared) {
                return redirect()->route('dtr.index')
                    ->with('coa_not_shared', 'DTRs for ' . date('F Y', mktime(0, 0, 0, $month, 1, $year)) . ' have not been shared with COA yet.');
            }
        }

        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $monthName = date('F', mktime(0, 0, 0, $month, 1));

        $dtrData = $this->computeDtr($bioId, $year, $month, $settings, $employee->default_work_week ?? null);

        $empDefaultWW = $employee->default_work_week ?? (($settings['four_day_work_week'] ?? '0') === '1' ? '4-day' : '5-day');

        $dtrData = $this->applyGlobalHolidays($dtrData, $year, $month, $empDefaultWW);

        $approvedEdits = DtrEditRequest::with('employee')
            ->forEmployee($bioId)
            ->forPeriod($year, $month)
            ->approved()
            ->get();

        foreach ($approvedEdits as $edit) {
            $dayNum = (int) $edit->target_date->format('j');
            $r = $dtrData[$dayNum]['remarks'] ?? '';
            if (!isset($dtrData[$dayNum])) {
                    $dtrData[$dayNum] = [
                        'am_in' => '', 'am_out' => '', 'pm_in' => '', 'pm_out' => '',
                        'total_hours' => '', 'remarks' => '', 'has_punch' => false, 'edited_fields' => [],
                    ];
                }
                $dtrData[$dayNum]['is_edited'] = true;

                switch ($edit->type) {
                    case 'time_correction':
                        $dtrData[$dayNum][$edit->field] = $edit->new_value;
                        $dtrData[$dayNum]['has_punch'] = true;
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], [$edit->field]);
                        break;
                    case 'absent':
                    $dtrData[$dayNum]['am_in'] = 'ABSENT';
                    $dtrData[$dayNum]['am_out'] = 'ABSENT';
                    $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                    $dtrData[$dayNum]['pm_out'] = 'ABSENT';
                    $dtrData[$dayNum]['total_hours'] = '';
                    $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | Absent' : 'Absent';
                    $dtrData[$dayNum]['has_punch'] = true;
                    $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out', 'pm_in', 'pm_out']);
                    break;
                case 'halfday_am':
                    $dtrData[$dayNum]['am_in'] = 'ABSENT';
                    $dtrData[$dayNum]['am_out'] = 'ABSENT';
                    if ($edit->field === 'am_out' && $edit->new_value) {
                        $dtrData[$dayNum]['am_out'] = $edit->new_value;
                    }
                    $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | Halfday (AM)' : 'Halfday (AM)';
                    $dtrData[$dayNum]['has_punch'] = true;
                    $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                    break;
                case 'halfday_pm':
                    $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                    $dtrData[$dayNum]['pm_out'] = 'ABSENT';
                    if ($edit->field === 'pm_in' && $edit->new_value) {
                        $dtrData[$dayNum]['pm_in'] = $edit->new_value;
                    }
                    $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | Halfday (PM)' : 'Halfday (PM)';
                    $dtrData[$dayNum]['has_punch'] = true;
                    $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                    break;
                case 'on_leave':
                    $leaveField = $edit->field ?: 'whole_day';
                    $leaveTypeLabel = $edit->reason ? ' (' . $edit->reason . ')' : '';
                    if ($leaveField === 'am') {
                        $dtrData[$dayNum]['am_in'] = 'ON LEAVE';
                        $dtrData[$dayNum]['am_out'] = 'ON LEAVE';
                        $dtrData[$dayNum]['leave_credit_minutes'] = max(1, (int) ($edit->new_value ?: '4')) * 60;
                        $dtrData[$dayNum]['remarks'] = ($r && strpos($r, 'On Leave') !== false) ? $r : (($r ?? '') ? $r . ' | On Leave (AM)' . $leaveTypeLabel : 'On Leave (AM)' . $leaveTypeLabel);
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                    } elseif ($leaveField === 'pm') {
                        $dtrData[$dayNum]['pm_in'] = 'ON LEAVE';
                        $dtrData[$dayNum]['pm_out'] = 'ON LEAVE';
                        $dtrData[$dayNum]['leave_credit_minutes'] = max(1, (int) ($edit->new_value ?: '4')) * 60;
                        $dtrData[$dayNum]['remarks'] = ($r && strpos($r, 'On Leave') !== false) ? $r : (($r ?? '') ? $r . ' | On Leave (PM)' . $leaveTypeLabel : 'On Leave (PM)' . $leaveTypeLabel);
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                    } else {
                        $dtrData[$dayNum]['am_in'] = 'ON LEAVE';
                        $dtrData[$dayNum]['am_out'] = 'ON LEAVE';
                        $dtrData[$dayNum]['pm_in'] = 'ON LEAVE';
                        $dtrData[$dayNum]['pm_out'] = 'ON LEAVE';
                        $dtrData[$dayNum]['total_hours'] = sprintf('%02d:00', max(1, (int) ($edit->new_value ?: '8')));
                        $dtrData[$dayNum]['remarks'] = ($r && strpos($r, 'On Leave') !== false) ? $r : (($r ?? '') ? $r . ' | On Leave' . $leaveTypeLabel : 'On Leave' . $leaveTypeLabel);
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out', 'pm_in', 'pm_out']);
                    }
                    $dtrData[$dayNum]['has_punch'] = true;
                    break;
                case 'holiday':
                    $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | Holiday' : 'Holiday';
                    $dtrData[$dayNum]['has_punch'] = true;
                    $dtrData[$dayNum]['is_holiday'] = true;
                    $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], []);
                    break;
                case 'wfh':
                    $dtrData[$dayNum]['has_punch'] = true;
                    $wfhType = $edit->new_value ?: 'whole_day';
                    if ($wfhType === 'am') {
                        $dtrData[$dayNum]['am_in'] = 'WFH';
                        $dtrData[$dayNum]['am_out'] = 'WFH';
                        $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | WFH (AM)' : 'WFH (AM)';
                        $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                    } elseif ($wfhType === 'pm') {
                        $dtrData[$dayNum]['pm_in'] = 'WFH';
                        $dtrData[$dayNum]['pm_out'] = 'WFH';
                        $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | WFH (PM)' : 'WFH (PM)';
                        $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                    } else {
                        $dtrData[$dayNum]['am_in'] = 'WFH';
                        $dtrData[$dayNum]['am_out'] = 'WFH';
                        $dtrData[$dayNum]['pm_in'] = 'WFH';
                        $dtrData[$dayNum]['pm_out'] = 'WFH';
                        $dtrData[$dayNum]['is_wfh'] = true;
                        $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | WFH' : 'WFH';
                        $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '10:00' : '08:00';
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out', 'pm_in', 'pm_out']);
                    }
                    break;
                case 'special_order':
                    $soType = $edit->field ?: 'whole_day';
                    $soNum = $edit->new_value;
                    $dtrData[$dayNum]['has_punch'] = true;
                    if ($soType === 'am') {
                        $dtrData[$dayNum]['am_in'] = 'SO: ' . $soNum;
                        $dtrData[$dayNum]['am_out'] = 'SO: ' . $soNum;
                        $dtrData[$dayNum]['so_number'] = $soNum;
                        $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | ' . 'Special Order' . ($soNum ? ': ' . $soNum : '') . ' (AM)' : 'Special Order' . ($soNum ? ': ' . $soNum : '') . ' (AM)';
                        $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                    } elseif ($soType === 'pm') {
                        $dtrData[$dayNum]['pm_in'] = 'SO: ' . $soNum;
                        $dtrData[$dayNum]['pm_out'] = 'SO: ' . $soNum;
                        $dtrData[$dayNum]['so_number'] = $soNum;
                        $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | ' . 'Special Order' . ($soNum ? ': ' . $soNum : '') . ' (PM)' : 'Special Order' . ($soNum ? ': ' . $soNum : '') . ' (PM)';
                        $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                    } else {
                        $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | ' . 'Special Order' . ($soNum ? ': ' . $soNum : '') : 'Special Order' . ($soNum ? ': ' . $soNum : '');
                        $dtrData[$dayNum]['so_number'] = $soNum;
                        $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '10:00' : '08:00';
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], []);
                    }
                    break;
                case 'travel_order':
                    $toType = $edit->field ?: 'whole_day';
                    $toNum = $edit->new_value;
                    $dtrData[$dayNum]['has_punch'] = true;
                    if ($toType === 'am') {
                        $dtrData[$dayNum]['am_in'] = 'TO: ' . $toNum;
                        $dtrData[$dayNum]['am_out'] = 'TO: ' . $toNum;
                        $dtrData[$dayNum]['to_number'] = $toNum;
                        $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | ' . 'Travel Order' . ($toNum ? ': ' . $toNum : '') . ' (AM)' : 'Travel Order' . ($toNum ? ': ' . $toNum : '') . ' (AM)';
                        $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                    } elseif ($toType === 'pm') {
                        $dtrData[$dayNum]['pm_in'] = 'TO: ' . $toNum;
                        $dtrData[$dayNum]['pm_out'] = 'TO: ' . $toNum;
                        $dtrData[$dayNum]['to_number'] = $toNum;
                        $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | ' . 'Travel Order' . ($toNum ? ': ' . $toNum : '') . ' (PM)' : 'Travel Order' . ($toNum ? ': ' . $toNum : '') . ' (PM)';
                        $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                    } else {
                        $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | ' . 'Travel Order' . ($toNum ? ': ' . $toNum : '') : 'Travel Order' . ($toNum ? ': ' . $toNum : '');
                        $dtrData[$dayNum]['to_number'] = $toNum;
                        $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '10:00' : '08:00';
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], []);
                    }
                    break;
                case 'official_business':
                    $obType = $edit->field ?: 'whole_day';
                    $obNum = $edit->new_value;
                    $dtrData[$dayNum]['has_punch'] = true;
                    if ($obType === 'am') {
                        $dtrData[$dayNum]['ob_number'] = $obNum;
                        $dtrData[$dayNum]['am_in'] = 'OB: ' . $obNum;
                        $dtrData[$dayNum]['am_out'] = 'OB: ' . $obNum;
                        $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | ' . 'Official Business' . ($obNum ? ': ' . $obNum : '') . ($edit->reason ? ' - ' . $edit->reason : '') . ' (AM)' : 'Official Business' . ($obNum ? ': ' . $obNum : '') . ($edit->reason ? ' - ' . $edit->reason : '') . ' (AM)';
                        $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                    } elseif ($obType === 'pm') {
                        $dtrData[$dayNum]['ob_number'] = $obNum;
                        $dtrData[$dayNum]['pm_in'] = 'OB: ' . $obNum;
                        $dtrData[$dayNum]['pm_out'] = 'OB: ' . $obNum;
                        $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | ' . 'Official Business' . ($obNum ? ': ' . $obNum : '') . ($edit->reason ? ' - ' . $edit->reason : '') . ' (PM)' : 'Official Business' . ($obNum ? ': ' . $obNum : '') . ($edit->reason ? ' - ' . $edit->reason : '') . ' (PM)';
                        $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                    } else {
                        $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | ' . 'Official Business' . ($obNum ? ': ' . $obNum : '') . ($edit->reason ? ' - ' . $edit->reason : '') : 'Official Business' . ($obNum ? ': ' . $obNum : '') . ($edit->reason ? ' - ' . $edit->reason : '');
                        $dtrData[$dayNum]['ob_number'] = $obNum;
                        $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '10:00' : '08:00';
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], []);
                    }
                    break;
                case 'work_suspension':
                    $wsType = $edit->new_value ?: 'whole_day';
                    if ($wsType === 'am') {
                        $dtrData[$dayNum]['am_in'] = 'ABSENT';
                        $dtrData[$dayNum]['am_out'] = 'ABSENT';
                        $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | Work Suspension (AM)' : 'Work Suspension (AM)';
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                    } elseif ($wsType === 'pm') {
                        $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                        $dtrData[$dayNum]['pm_out'] = 'ABSENT';
                        $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | Work Suspension (PM)' : 'Work Suspension (PM)';
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                    } else {
                        $dtrData[$dayNum]['am_in'] = 'ABSENT';
                        $dtrData[$dayNum]['am_out'] = 'ABSENT';
                        $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                        $dtrData[$dayNum]['pm_out'] = 'ABSENT';
                        $dtrData[$dayNum]['total_hours'] = '';
                        $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | Work Suspension' : 'Work Suspension';
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out', 'pm_in', 'pm_out']);
                    }
                    $dtrData[$dayNum]['has_punch'] = true;
                    break;
                case 'locator_slip':
                    $lsType = $edit->field ?: 'official';
                    $whereabouts = $edit->new_value;
                    $typeLabel = $lsType === 'personal' ? 'Personal' : 'Official';
                    $timeLeft = $edit->ls_time_left ? date('h:i A', strtotime($edit->ls_time_left)) : '';
                    $timeReturned = $edit->ls_no_return ? 'No Return' : ($edit->ls_time_returned ? date('h:i A', strtotime($edit->ls_time_returned)) : '');
                    $dtrData[$dayNum]['has_punch'] = true;
                    $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], []);
                    $details = array_filter([$whereabouts, $timeLeft ? "Left: $timeLeft" : null, $timeReturned ? "Ret: $timeReturned" : null]);
                    if ($lsType === 'personal') {
                        $lsDuration = 0;
                        if ($edit->ls_time_left && $edit->ls_time_returned && !$edit->ls_no_return) {
                            $lsDuration = (strtotime($edit->ls_time_returned) - strtotime($edit->ls_time_left)) / 60;
                        } elseif ($edit->ls_no_return && $edit->ls_time_left) {
                            $pmEnd = $settings['pm_end'] ?? '17:00';
                            $timeLeftTs = strtotime($edit->ls_time_left);
                            $pmEndTs = strtotime($pmEnd);
                            $lsDuration = ($pmEndTs - $timeLeftTs) / 60;
                        }
                        $existingTotal = $dtrData[$dayNum]['total_hours'] ?? '00:00';
                        $parts = explode(':', $existingTotal);
                        $existingMins = (int)($parts[0] ?? 0) * 60 + (int)($parts[1] ?? 0);
                        $remainingMins = max(0, $existingMins - $lsDuration);
                        $hours = floor($remainingMins / 60);
                        $mins = round($remainingMins % 60);
                        $dtrData[$dayNum]['total_hours'] = sprintf('%02d:%02d', $hours, $mins);
                        $lsRemark = 'LS: ' . implode(' | ', $details) . ' (Personal)';
                        if ($lsDuration > 0) {
                            $lsRemark .= ' | LS Deduction: ' . gmdate('H:i', $lsDuration * 60);
                        }
                        $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | ' . $lsRemark : $lsRemark;
                    } else {
                        $lsRemark = 'LS: ' . implode(' | ', $details) . ' (Official)';
                        $dtrData[$dayNum]['remarks'] = ($r ?? '') ? $r . ' | ' . $lsRemark : $lsRemark;
                        if ($edit->ls_no_return && $edit->ls_time_left) {
                            $pmEnd = $settings['pm_end'] ?? '17:00';
                            $timeLeftTs = strtotime($edit->ls_time_left);
                            $pmEndTs = strtotime($pmEnd);
                            $remainingMins = max(0, ($pmEndTs - $timeLeftTs) / 60);
                            $existingTotal = $dtrData[$dayNum]['total_hours'] ?? '00:00';
                            $parts = explode(':', $existingTotal);
                            $existingMins = (int)($parts[0] ?? 0) * 60 + (int)($parts[1] ?? 0);
                            $totalMins = $existingMins + $remainingMins;
                            $hours = floor($totalMins / 60);
                            $mins = round($totalMins % 60);
                            $dtrData[$dayNum]['total_hours'] = sprintf('%02d:%02d', $hours, $mins);
                        }
                    }
                    break;
            }
        }

        foreach ($dtrData as $dayNum => &$day) {
            $isHalfday = strpos($day['remarks'] ?? '', '(AM)') !== false || strpos($day['remarks'] ?? '', '(PM)') !== false;
            if (!$isHalfday) continue;
            if (strpos($day['remarks'] ?? '', 'Halfday') === 0) continue;
            $isAm = strpos($day['remarks'] ?? '', '(AM)') !== false;
            $lateUt = '';
            if ($isAm) {
                if (!empty($day['pm_in']) && preg_match('/^\d/', $day['pm_in'])) {
                    $pmStartTS = strtotime($settings['pm_start'] ?? '13:00');
                    $pmInTS = strtotime($day['pm_in']);
                    if ($pmInTS > $pmStartTS) {
                        $lateMins = ($pmInTS - $pmStartTS) / 60;
                        $lateUt = 'Late: PM ' . gmdate('H:i', $lateMins * 60);
                    }
                }
                $actualMins = $this->computePunchMinutes($day['pm_in'] ?? '', $day['pm_out'] ?? '');
            } else {
                if (!empty($day['am_in']) && preg_match('/^\d/', $day['am_in'])) {
                    $amStartTS = strtotime($settings['am_start'] ?? '07:00');
                    $amLateThreshold = $amStartTS + ((int)($settings['am_start_flexi'] ?? 120)) * 60;
                    $amInTS = strtotime($day['am_in']);
                    if ($amInTS > $amLateThreshold) {
                        $lateMins = ($amInTS - $amLateThreshold) / 60;
                        $lateUt = 'Late: AM ' . gmdate('H:i', $lateMins * 60);
                    }
                }
                $actualMins = $this->computePunchMinutes($day['am_in'] ?? '', $day['am_out'] ?? '');
            }
            $ww = $day['work_week_type'] ?? $empDefaultWW;
            $expectedHalfMins = $ww === '4-day' ? 300 : 240;
            $utMins = $expectedHalfMins - $actualMins;
            if ($utMins > 0) {
                $utStr = 'UT: ' . gmdate('H:i', $utMins * 60);
                $lateUt = $lateUt ? $lateUt . ' | ' . $utStr : $utStr;
            }
            if ($lateUt) {
                $day['remarks'] .= ' | ' . $lateUt;
            }
        }
        unset($day);

        foreach ($approvedEdits as $edit) {
            if (!in_array($edit->type, ['time_correction', 'halfday_am', 'halfday_pm'])) continue;
            $dayNum = (int) $edit->target_date->format('j');
            $day = $dtrData[$dayNum];
            $hasSo = !empty($day['so_number']);
            $hasTo = !empty($day['to_number']);
            $hasOb = !empty($day['ob_number']);
            $hasLs = strpos($day['remarks'] ?? '', 'LS:') !== false;
            $isOnLeave = ($day['am_in'] ?? '') === 'ON LEAVE' || ($day['pm_in'] ?? '') === 'ON LEAVE';
            if ($edit->type === 'time_correction') {
                $dtrData[$dayNum]['total_hours'] = $this->recalcHours($day, $settings);
                if (!$hasSo && !$hasTo && !$hasOb && !$hasLs && !$isOnLeave) {
                    $dtrData[$dayNum]['remarks'] = $this->recalcRemarks($day, $settings);
                }
            } else {
                $dtrData[$dayNum]['total_hours'] = $this->recalcHours($day, $settings);
            }
        }

        foreach ($approvedEdits as $edit) {
            if (!in_array($edit->type, ['halfday_am', 'halfday_pm'])) continue;
            $dayNum = (int) $edit->target_date->format('j');
            if (strpos($dtrData[$dayNum]['remarks'] ?? '', 'Halfday') !== false) {
                $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
            }
        }

        $dayOverrides = DtrDayOverride::where('employee_id', $employee->id)
            ->whereBetween('target_date', ["$year-$month-01", "$year-$month-$daysInMonth"])
            ->get();

        foreach ($dayOverrides as $override) {
            $dayNum = (int) $override->target_date->format('j');
            if (!isset($dtrData[$dayNum])) {
                $dtrData[$dayNum] = ['am_in' => '', 'am_out' => '', 'pm_in' => '', 'pm_out' => '', 'total_hours' => '', 'remarks' => '', 'has_punch' => false];
            }
            $dtrData[$dayNum]['work_week_type'] = $override->work_week_type;
        }

        foreach ($dtrData as $dayNum => &$day) {
            if (!isset($day['work_week_type'])) continue;
            $schedule = $this->getScheduleForWorkWeek($day['work_week_type'], $settings);

            $isSpecial = !empty($day['is_wfh']) || !empty($day['so_number']) || !empty($day['to_number']) || !empty($day['ob_number']) || !empty($day['is_holiday']) || !empty($day['is_work_suspension']) || (isset($day['remarks']) && (strpos($day['remarks'], 'LS:') !== false || strpos($day['remarks'], 'WFH') === 0 || strpos($day['remarks'], 'On Leave') !== false));

            if (!$isSpecial) {
                $day['remarks'] = $this->recalcRemarks($day, $settings, $schedule);
            }

            if ($isSpecial) {
                if (isset($day['remarks']) && strpos($day['remarks'], 'LS:') !== false) {
                    // LS total_hours already set by LS case block
                } elseif (isset($day['remarks']) && strpos($day['remarks'], 'On Leave') !== false) {
                    // On Leave: total_hours handled by recomputeHalfdayHours or set in switch
                } else {
                    $isHalfday = strpos($day['remarks'] ?? '', '(AM)') !== false || strpos($day['remarks'] ?? '', '(PM)') !== false;
                    if ($isHalfday) {
                        $day['total_hours'] = $day['work_week_type'] === '4-day' ? '05:00' : '04:00';
                    } else {
                        $day['total_hours'] = $day['work_week_type'] === '4-day' ? '10:00' : '08:00';
                    }
                }
            } elseif (strpos($day['remarks'] ?? '', 'Halfday') !== false) {
                $day['total_hours'] = $day['work_week_type'] === '4-day' ? '05:00' : '04:00';
            }

            $wfhLabel = $this->resolveWfhLabel($day);
            if ($wfhLabel && !$isSpecial) {
                $day['remarks'] = !empty($day['remarks']) ? $wfhLabel . ' | ' . $day['remarks'] : $wfhLabel;
            }

            if (!$isSpecial && (strpos($wfhLabel, '(AM)') !== false || strpos($wfhLabel, '(PM)') !== false)) {
                $day['total_hours'] = $day['work_week_type'] === '4-day' ? '05:00' : '04:00';
            }
        }
        unset($day);

        $this->recomputeHalfdayHours($dtrData, $employee, $settings);

        foreach ($dtrData as $dayNum => &$day) {
            $ww = $day['work_week_type'] ?? $empDefaultWW;
            $expectedMins = $ww === '4-day' ? 600 : 480;
            $totalMins = 0;
            if (!empty($day['total_hours'])) {
                $parts = explode(':', $day['total_hours']);
                $totalMins = (int)($parts[0] ?? 0) * 60 + (int)($parts[1] ?? 0);
            }
            $utMins = $totalMins > 0 ? max(0, $expectedMins - $totalMins) : 0;
            $remarks = trim(preg_replace('/(?:^|\s*\|\s*)UT:\s*\d+:\d+/', '', $day['remarks'] ?? ''), ' |');
            $parts = [];
            if ($remarks !== '') $parts[] = $remarks;
            if ($utMins > 0) $parts[] = 'UT: ' . gmdate('H:i', $utMins * 60);
            $day['remarks'] = implode(' | ', $parts);
        }
        unset($day);

        $presentDays = 0;
        $totalMinutes = 0;
        $totalLate = 0;
        $totalUndertime = 0;

        foreach ($dtrData as $dayNum => $day) {
            $dow = date('N', strtotime(sprintf('%04d-%02d-%02d', $year, $month, $dayNum)));
            $dayMaxDow = isset($day['work_week_type']) ? ($day['work_week_type'] === '4-day' ? 4 : 5) : ((isset($employee) && $employee->default_work_week === '4-day') ? 4 : (($settings['four_day_work_week'] ?? '0') === '1' ? 4 : ($settings['max_dow'] ?? 5)));
            if (!empty($day['has_punch']) && $dow <= $dayMaxDow) {
                $presentDays++;
                if (!empty($day['total_hours'])) {
                    $parts = explode(':', $day['total_hours']);
                    $totalMinutes += (int) $parts[0] * 60 + (int) $parts[1];
                }
                if (!empty($day['remarks']) && strpos($day['remarks'], 'Late:') !== false) {
                    preg_match_all('/(?:AM|PM)\s+(\d+):(\d+)/', $day['remarks'], $m);
                    for ($i = 0; $i < count($m[0]); $i++) {
                        $totalLate += (int) $m[1][$i] * 60 + (int) $m[2][$i];
                    }
                }
                if (!empty($day['remarks']) && strpos($day['remarks'], 'UT:') !== false) {
                    preg_match('/UT: (\d+):(\d+)/', $day['remarks'], $u);
                    if (isset($u[1])) {
                        $totalUndertime += (int) $u[1] * 60 + (int) $u[2];
                    }
                }
            }
        }

        $isOwnDtr = $bioId === $user->emp_code;
        $isSupervisor = $user->is_super;
        if (!$isSupervisor && $dtrUser) {
            $isSupervisor = Section::where('supervisor_id', $dtrUser->id)
                ->whereHas('dtrUsers', function ($q) use ($employee) {
                    $q->where('id', $employee->id);
                })->exists()
                || Section::where('oic_id', $dtrUser->id)
                    ->whereHas('dtrUsers', function ($q) use ($employee) {
                        $q->where('id', $employee->id);
                    })->exists()
                || \App\Models\Office::where('supervisor_id', $dtrUser->id)
                    ->whereHas('dtrUsers', function ($q) use ($employee) {
                        $q->where('id', $employee->id);
                    })->exists()
                || \App\Models\Office::where('senior_manager_oic_id', $dtrUser->id)
                    ->whereHas('dtrUsers', function ($q) use ($employee) {
                        $q->where('id', $employee->id);
                    })->exists()
                || \App\Models\Office::where('oic_id', $dtrUser->id)
                    ->whereHas('dtrUsers', function ($q) use ($employee) {
                        $q->where('id', $employee->id);
                    })->exists();
        }

        $allEmpRequests = DtrEditRequest::with('employee')
            ->forEmployee($bioId)
            ->forPeriod($year, $month)
            ->get();

        $pendingRequests = $allEmpRequests->where('status', 'pending')->sortByDesc('created_at');
        $approvedRequests = $allEmpRequests->where('status', 'approved')->sortBy('target_date');
        $rejectedRequests = $allEmpRequests->where('status', 'rejected')->sortByDesc('created_at');

        return view('dtr.show', compact(
            'employee', 'settings', 'dtrData', 'month', 'year',
            'daysInMonth', 'monthName', 'presentDays',
            'totalMinutes', 'totalLate', 'totalUndertime',
            'isOwnDtr', 'isSupervisor', 'pendingRequests', 'approvedRequests', 'rejectedRequests'
        ));
    }

    public function dashboard(Request $request)
    {
        $user = auth()->user();
        $settings = DtrSetting::getSettings();
        $settings = $this->backupOriginalSchedule($settings);
        $settings = $this->applyFourDaySettings($settings);

        $month = (int) $request->input('month', date('m'));
        $year = (int) $request->input('year', date('Y'));

        $employee = DtrUser::where('emp_code', $user->emp_code)->first();

        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $monthName = date('F', mktime(0, 0, 0, $month, 1));

        $dtrData = [];
        $presentDays = 0;
        $totalMinutes = 0;

        if ($employee) {
            $bioId = $employee->emp_code;
            $empDefaultWW = $employee->default_work_week ?? (($settings['four_day_work_week'] ?? '0') === '1' ? '4-day' : '5-day');

            $dtrData = $this->computeDtr($bioId, $year, $month, $settings, $employee->default_work_week ?? null);
            $dtrData = $this->applyGlobalHolidays($dtrData, $year, $month, $empDefaultWW);

            $approvedEdits = DtrEditRequest::with('employee')
                ->forEmployee($bioId)
                ->forPeriod($year, $month)
                ->approved()
                ->get();

            foreach ($approvedEdits as $edit) {
                $dayNum = (int) $edit->target_date->format('j');
                if (!isset($dtrData[$dayNum])) {
                    $dtrData[$dayNum] = [
                        'am_in' => '', 'am_out' => '', 'pm_in' => '', 'pm_out' => '',
                        'total_hours' => '', 'remarks' => '', 'has_punch' => false, 'edited_fields' => [],
                    ];
                }
                $dtrData[$dayNum]['is_edited'] = true;

                switch ($edit->type) {
                    case 'time_correction':
                        $dtrData[$dayNum][$edit->field] = $edit->new_value;
                        $dtrData[$dayNum]['has_punch'] = true;
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], [$edit->field]);
                        break;
                    case 'absent':
                        $dtrData[$dayNum]['am_in'] = 'ABSENT';
                        $dtrData[$dayNum]['am_out'] = 'ABSENT';
                        $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                        $dtrData[$dayNum]['pm_out'] = 'ABSENT';
                        $dtrData[$dayNum]['total_hours'] = '';
                        $dtrData[$dayNum]['remarks'] = 'Absent';
                        $dtrData[$dayNum]['has_punch'] = true;
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out', 'pm_in', 'pm_out']);
                        break;
                    case 'halfday_am':
                        $dtrData[$dayNum]['am_in'] = 'ABSENT';
                        $dtrData[$dayNum]['am_out'] = 'ABSENT';
                        if ($edit->field === 'am_out' && $edit->new_value) {
                            $dtrData[$dayNum]['am_out'] = $edit->new_value;
                        }
                        $dtrData[$dayNum]['remarks'] = 'Halfday (AM)';
                        $dtrData[$dayNum]['has_punch'] = true;
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                        break;
                    case 'halfday_pm':
                        $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                        $dtrData[$dayNum]['pm_out'] = 'ABSENT';
                        if ($edit->field === 'pm_in' && $edit->new_value) {
                            $dtrData[$dayNum]['pm_in'] = $edit->new_value;
                        }
                        $dtrData[$dayNum]['remarks'] = 'Halfday (PM)';
                        $dtrData[$dayNum]['has_punch'] = true;
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                        break;
                    case 'on_leave':
                        $leaveField = $edit->field ?: 'whole_day';
                        $leaveTypeLabel = $edit->reason ? ' (' . $edit->reason . ')' : '';
                        if ($leaveField === 'am') {
                            $dtrData[$dayNum]['am_in'] = 'ON LEAVE';
                            $dtrData[$dayNum]['am_out'] = 'ON LEAVE';
                            $dtrData[$dayNum]['leave_credit_minutes'] = max(1, (int) ($edit->new_value ?: '4')) * 60;
                            $dtrData[$dayNum]['remarks'] = ($r && strpos($r, 'On Leave') !== false) ? $r : (($r ?? '') ? $r . ' | On Leave (AM)' . $leaveTypeLabel : 'On Leave (AM)' . $leaveTypeLabel);
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                        } elseif ($leaveField === 'pm') {
                            $dtrData[$dayNum]['pm_in'] = 'ON LEAVE';
                            $dtrData[$dayNum]['pm_out'] = 'ON LEAVE';
                            $dtrData[$dayNum]['leave_credit_minutes'] = max(1, (int) ($edit->new_value ?: '4')) * 60;
                            $dtrData[$dayNum]['remarks'] = 'On Leave (PM)' . $leaveTypeLabel;
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                        } else {
                            $dtrData[$dayNum]['am_in'] = 'ON LEAVE';
                            $dtrData[$dayNum]['am_out'] = 'ON LEAVE';
                            $dtrData[$dayNum]['pm_in'] = 'ON LEAVE';
                            $dtrData[$dayNum]['pm_out'] = 'ON LEAVE';
                            $dtrData[$dayNum]['total_hours'] = sprintf('%02d:00', max(1, (int) ($edit->new_value ?: '8')));
                            $dtrData[$dayNum]['remarks'] = 'On Leave' . $leaveTypeLabel;
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out', 'pm_in', 'pm_out']);
                        }
                        $dtrData[$dayNum]['has_punch'] = true;
                        break;
                    case 'holiday':
                        $dtrData[$dayNum]['remarks'] = 'Holiday';
                        $dtrData[$dayNum]['has_punch'] = true;
                        $dtrData[$dayNum]['is_holiday'] = true;
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], []);
                        break;
                    case 'wfh':
                        $dtrData[$dayNum]['has_punch'] = true;
                        $wfhType = $edit->new_value ?: 'whole_day';
                        if ($wfhType === 'am') {
                            $dtrData[$dayNum]['am_in'] = 'WFH';
                            $dtrData[$dayNum]['am_out'] = 'WFH';
                            $dtrData[$dayNum]['remarks'] = 'WFH (AM)';
                            $dtrData[$dayNum]['total_hours'] = '04:00';
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                        } elseif ($wfhType === 'pm') {
                            $dtrData[$dayNum]['pm_in'] = 'WFH';
                            $dtrData[$dayNum]['pm_out'] = 'WFH';
                            $dtrData[$dayNum]['remarks'] = 'WFH (PM)';
                            $dtrData[$dayNum]['total_hours'] = '04:00';
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                        } else {
                            $dtrData[$dayNum]['am_in'] = 'WFH';
                            $dtrData[$dayNum]['am_out'] = 'WFH';
                            $dtrData[$dayNum]['pm_in'] = 'WFH';
                            $dtrData[$dayNum]['pm_out'] = 'WFH';
                            $dtrData[$dayNum]['is_wfh'] = true;
                            $dtrData[$dayNum]['remarks'] = 'WFH';
                            $dtrData[$dayNum]['total_hours'] = '08:00';
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out', 'pm_in', 'pm_out']);
                        }
                        break;
                    case 'special_order':
                        $soType = $edit->field ?: 'whole_day';
                        $soNum = $edit->new_value;
                        $dtrData[$dayNum]['has_punch'] = true;
                        if ($soType === 'am') {
                            $dtrData[$dayNum]['am_in'] = 'SO: ' . $soNum;
                            $dtrData[$dayNum]['am_out'] = 'SO: ' . $soNum;
                            $dtrData[$dayNum]['so_number'] = $soNum;
                            $dtrData[$dayNum]['remarks'] = 'Special Order' . ($soNum ? ': ' . $soNum : '') . ' (AM)';
                            $dtrData[$dayNum]['total_hours'] = '04:00';
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                        } elseif ($soType === 'pm') {
                            $dtrData[$dayNum]['pm_in'] = 'SO: ' . $soNum;
                            $dtrData[$dayNum]['pm_out'] = 'SO: ' . $soNum;
                            $dtrData[$dayNum]['so_number'] = $soNum;
                            $dtrData[$dayNum]['remarks'] = 'Special Order' . ($soNum ? ': ' . $soNum : '') . ' (PM)';
                            $dtrData[$dayNum]['total_hours'] = '04:00';
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                        } else {
                            $dtrData[$dayNum]['remarks'] = 'Special Order' . ($soNum ? ': ' . $soNum : '');
                            $dtrData[$dayNum]['so_number'] = $soNum;
                            $dtrData[$dayNum]['total_hours'] = '08:00';
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], []);
                        }
                        break;
                    case 'travel_order':
                        $toType = $edit->field ?: 'whole_day';
                        $toNum = $edit->new_value;
                        $dtrData[$dayNum]['has_punch'] = true;
                        if ($toType === 'am') {
                            $dtrData[$dayNum]['am_in'] = 'TO: ' . $toNum;
                            $dtrData[$dayNum]['am_out'] = 'TO: ' . $toNum;
                            $dtrData[$dayNum]['to_number'] = $toNum;
                            $dtrData[$dayNum]['remarks'] = 'Travel Order' . ($toNum ? ': ' . $toNum : '') . ' (AM)';
                            $dtrData[$dayNum]['total_hours'] = '04:00';
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                        } elseif ($toType === 'pm') {
                            $dtrData[$dayNum]['pm_in'] = 'TO: ' . $toNum;
                            $dtrData[$dayNum]['pm_out'] = 'TO: ' . $toNum;
                            $dtrData[$dayNum]['to_number'] = $toNum;
                            $dtrData[$dayNum]['remarks'] = 'Travel Order' . ($toNum ? ': ' . $toNum : '') . ' (PM)';
                            $dtrData[$dayNum]['total_hours'] = '04:00';
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                        } else {
                            $dtrData[$dayNum]['remarks'] = 'Travel Order' . ($toNum ? ': ' . $toNum : '');
                            $dtrData[$dayNum]['to_number'] = $toNum;
                            $dtrData[$dayNum]['total_hours'] = '08:00';
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], []);
                        }
                        break;
                    case 'official_business':
                        $obType = $edit->field ?: 'whole_day';
                        $obNum = $edit->new_value;
                        $dtrData[$dayNum]['has_punch'] = true;
                        if ($obType === 'am') {
                            $dtrData[$dayNum]['ob_number'] = $obNum;
                            $dtrData[$dayNum]['am_in'] = 'OB: ' . $obNum;
                            $dtrData[$dayNum]['am_out'] = 'OB: ' . $obNum;
                            $dtrData[$dayNum]['remarks'] = 'Official Business' . ($obNum ? ': ' . $obNum : '') . ($edit->reason ? ' - ' . $edit->reason : '') . ' (AM)';
                            $dtrData[$dayNum]['total_hours'] = '04:00';
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                        } elseif ($obType === 'pm') {
                            $dtrData[$dayNum]['ob_number'] = $obNum;
                            $dtrData[$dayNum]['pm_in'] = 'OB: ' . $obNum;
                            $dtrData[$dayNum]['pm_out'] = 'OB: ' . $obNum;
                            $dtrData[$dayNum]['remarks'] = 'Official Business' . ($obNum ? ': ' . $obNum : '') . ($edit->reason ? ' - ' . $edit->reason : '') . ' (PM)';
                            $dtrData[$dayNum]['total_hours'] = '04:00';
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                        } else {
                            $dtrData[$dayNum]['remarks'] = 'Official Business' . ($obNum ? ': ' . $obNum : '') . ($edit->reason ? ' - ' . $edit->reason : '');
                            $dtrData[$dayNum]['ob_number'] = $obNum;
                            $dtrData[$dayNum]['total_hours'] = '08:00';
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], []);
                        }
                        break;
                }
            }

            foreach ($dtrData as $dayNum => &$day) {
                $isHalfday = strpos($day['remarks'] ?? '', '(AM)') !== false || strpos($day['remarks'] ?? '', '(PM)') !== false;
                if (!$isHalfday) continue;
                if (strpos($day['remarks'] ?? '', 'Halfday') === 0) continue;
                $isAm = strpos($day['remarks'] ?? '', '(AM)') !== false;
                $lateUt = '';
                if ($isAm) {
                    if (!empty($day['pm_in']) && preg_match('/^\d/', $day['pm_in'])) {
                        $pmStartTS = strtotime($settings['pm_start'] ?? '13:00');
                        $pmInTS = strtotime($day['pm_in']);
                        if ($pmInTS > $pmStartTS) {
                            $lateMins = ($pmInTS - $pmStartTS) / 60;
                            $lateUt = 'Late: PM ' . gmdate('H:i', $lateMins * 60);
                        }
                    }
                    $actualMins = $this->computePunchMinutes($day['pm_in'] ?? '', $day['pm_out'] ?? '');
                } else {
                    if (!empty($day['am_in']) && preg_match('/^\d/', $day['am_in'])) {
                        $amStartTS = strtotime($settings['am_start'] ?? '07:00');
                        $amLateThreshold = $amStartTS + ((int)($settings['am_start_flexi'] ?? 120)) * 60;
                        $amInTS = strtotime($day['am_in']);
                        if ($amInTS > $amLateThreshold) {
                            $lateMins = ($amInTS - $amLateThreshold) / 60;
                            $lateUt = 'Late: AM ' . gmdate('H:i', $lateMins * 60);
                        }
                    }
                    $actualMins = $this->computePunchMinutes($day['am_in'] ?? '', $day['am_out'] ?? '');
                }
                $ww = $day['work_week_type'] ?? $empDefaultWW;
                $expectedHalfMins = $ww === '4-day' ? 300 : 240;
                $utMins = $expectedHalfMins - $actualMins;
                if ($utMins > 0) {
                    $utStr = 'UT: ' . gmdate('H:i', $utMins * 60);
                    $lateUt = $lateUt ? $lateUt . ' | ' . $utStr : $utStr;
                }
                if ($lateUt) {
                    $day['remarks'] .= ' | ' . $lateUt;
                }
            }
            unset($day);

            $this->recomputeHalfdayHours($dtrData, $employee, $settings);

            foreach ($dtrData as $dayNum => &$day) {
                if (!isset($day['work_week_type'])) continue;
                $schedule = $this->getScheduleForWorkWeek($day['work_week_type'], $settings);

                $isSpecial = !empty($day['is_wfh']) || !empty($day['so_number']) || !empty($day['to_number']) || !empty($day['ob_number']) || !empty($day['is_holiday']) || !empty($day['is_work_suspension']) || (isset($day['remarks']) && (strpos($day['remarks'], 'LS:') !== false || strpos($day['remarks'], 'WFH') === 0 || strpos($day['remarks'], 'On Leave') !== false));

                if (!$isSpecial) {
                    $day['remarks'] = $this->recalcRemarks($day, $settings, $schedule);
                }

                if ($isSpecial) {
                    if (isset($day['remarks']) && strpos($day['remarks'], 'LS:') !== false) {
                        // LS total_hours already set by LS case block
                    } elseif (isset($day['remarks']) && strpos($day['remarks'], 'On Leave') !== false) {
                        // On Leave: total_hours handled by recomputeHalfdayHours or set in switch
                    } else {
                        $isHalfday = strpos($day['remarks'] ?? '', '(AM)') !== false || strpos($day['remarks'] ?? '', '(PM)') !== false;
                        if ($isHalfday) {
                            $day['total_hours'] = $day['work_week_type'] === '4-day' ? '05:00' : '04:00';
                        } else {
                            $day['total_hours'] = $day['work_week_type'] === '4-day' ? '10:00' : '08:00';
                        }
                    }
                }

                $wfhLabel = $this->resolveWfhLabel($day);
                if ($wfhLabel && !$isSpecial) {
                    $day['remarks'] = !empty($day['remarks']) ? $wfhLabel . ' | ' . $day['remarks'] : $wfhLabel;
                }

                if (!$isSpecial && (strpos($wfhLabel, '(AM)') !== false || strpos($wfhLabel, '(PM)') !== false)) {
                    $day['total_hours'] = $day['work_week_type'] === '4-day' ? '05:00' : '04:00';
                }
            }
            unset($day);

            $this->recomputeHalfdayHours($dtrData, $employee, $settings);

            foreach ($dtrData as $dayNum => &$day) {
                $ww = $day['work_week_type'] ?? $empDefaultWW;
                $expectedMins = $ww === '4-day' ? 600 : 480;
                $totalMins = 0;
                if (!empty($day['total_hours'])) {
                    $parts = explode(':', $day['total_hours']);
                    $totalMins = (int)($parts[0] ?? 0) * 60 + (int)($parts[1] ?? 0);
                }
                $utMins = $totalMins > 0 ? max(0, $expectedMins - $totalMins) : 0;
                $remarks = trim(preg_replace('/(?:^|\s*\|\s*)UT:\s*\d+:\d+/', '', $day['remarks'] ?? ''), ' |');
                $parts = [];
                if ($remarks !== '') $parts[] = $remarks;
                if ($utMins > 0) $parts[] = 'UT: ' . gmdate('H:i', $utMins * 60);
                $day['remarks'] = implode(' | ', $parts);
            }
            unset($day);

            $lateDaysCount = 0;
            $undertimeDaysCount = 0;
            $absentDaysCount = 0;
            foreach ($dtrData as $dayNum => $day) {
                $dow = date('N', strtotime(sprintf('%04d-%02d-%02d', $year, $month, $dayNum)));
                $dayMaxDow = $empDefaultWW === '4-day' ? 4 : 5;
                if (!empty($day['has_punch']) && $dow <= $dayMaxDow) {
                    $presentDays++;
                    if (!empty($day['total_hours'])) {
                        $parts = explode(':', $day['total_hours']);
                        $totalMinutes += (int) $parts[0] * 60 + (int) $parts[1];
                    }
                }
                $remarks = $day['remarks'] ?? '';
                if (strpos($remarks, 'Late:') !== false) {
                    $lateDaysCount++;
                }
                if (strpos($remarks, 'UT:') !== false) {
                    $undertimeDaysCount++;
                }
                if (strpos($remarks, 'Absent') !== false) {
                    $absentDaysCount++;
                }
            }
        }

        if (!isset($empDefaultWW)) {
            $empDefaultWW = ($settings['four_day_work_week'] ?? '0') === '1' ? '4-day' : '5-day';
        }

        $holidays = \App\Models\GlobalHoliday::whereBetween('target_date', ["$year-$month-01", "$year-$month-$daysInMonth"])
            ->orderBy('target_date')
            ->get()
            ->keyBy(function ($item) {
                return $item->target_date->format('Y-m-d');
            });

        $firstDayOfWeek = date('N', strtotime("$year-$month-01"));
        $weeks = [];
        $day = 1;
        $totalCells = ceil(($firstDayOfWeek + $daysInMonth - 1) / 7) * 7;
        for ($i = 0; $i < $totalCells; $i++) {
            $weekIndex = intdiv($i, 7);
            if (!isset($weeks[$weekIndex])) $weeks[$weekIndex] = [];
            if ($i < $firstDayOfWeek - 1 || $day > $daysInMonth) {
                $weeks[$weekIndex][] = null;
            } else {
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $dayData = $dtrData[$day] ?? [];
                $weeks[$weekIndex][] = [
                    'day' => $day,
                    'date' => $dateStr,
                    'holiday' => $holidays->get($dateStr),
                    'dtr' => $dayData,
                ];
                $day++;
            }
        }

        $totalHoursFormatted = sprintf('%02d:%02d', floor($totalMinutes / 60), $totalMinutes % 60);

        $dtrUser = DtrUser::where('emp_code', $user->emp_code)->first();
        $isSupervisor = $user->is_super;
        if (!$isSupervisor && $dtrUser) {
            $isSupervisor = \App\Models\Section::where('supervisor_id', $dtrUser->id)->exists()
                || \App\Models\Section::where('oic_id', $dtrUser->id)->exists()
                || \App\Models\Office::where('supervisor_id', $dtrUser->id)->exists()
                || \App\Models\Office::where('senior_manager_oic_id', $dtrUser->id)->exists()
                || \App\Models\Office::where('oic_id', $dtrUser->id)->exists();
        }

        return view('dtr.dashboard', compact(
            'employee', 'settings', 'dtrData', 'month', 'year',
            'daysInMonth', 'monthName', 'presentDays', 'totalHoursFormatted',
            'weeks', 'empDefaultWW', 'isSupervisor', 'lateDaysCount', 'undertimeDaysCount', 'absentDaysCount'
        ));
    }

    public function getEmployeeMonthlyStats($bioId, $year, $month)
    {
        $settings = DtrSetting::getSettings();
        $settings = $this->backupOriginalSchedule($settings);
        $settings = $this->applyFourDaySettings($settings);

        $employee = DtrUser::where('emp_code', $bioId)->first();
        if (!$employee) {
            return null;
        }

        $empDefaultWW = $employee->default_work_week ?? (($settings['four_day_work_week'] ?? '0') === '1' ? '4-day' : '5-day');

        $dtrData = $this->computeDtr($bioId, $year, $month, $settings, $employee->default_work_week ?? null);
        $dtrData = $this->applyGlobalHolidays($dtrData, $year, $month, $empDefaultWW);

        $approvedEdits = DtrEditRequest::with('employee')
            ->forEmployee($bioId)
            ->forPeriod($year, $month)
            ->approved()
            ->get();

        foreach ($approvedEdits as $edit) {
            $dayNum = (int) $edit->target_date->format('j');
            if (!isset($dtrData[$dayNum])) {
                $dtrData[$dayNum] = [
                    'am_in' => '', 'am_out' => '', 'pm_in' => '', 'pm_out' => '',
                    'total_hours' => '', 'remarks' => '', 'has_punch' => false, 'edited_fields' => [],
                ];
            }
            $dtrData[$dayNum]['is_edited'] = true;

            switch ($edit->type) {
                case 'time_correction':
                    $dtrData[$dayNum][$edit->field] = $edit->new_value;
                    $dtrData[$dayNum]['has_punch'] = true;
                    $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], [$edit->field]);
                    break;
                case 'absent':
                    $dtrData[$dayNum]['am_in'] = 'ABSENT';
                    $dtrData[$dayNum]['am_out'] = 'ABSENT';
                    $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                    $dtrData[$dayNum]['pm_out'] = 'ABSENT';
                    $dtrData[$dayNum]['total_hours'] = '';
                    $dtrData[$dayNum]['remarks'] = 'Absent';
                    $dtrData[$dayNum]['has_punch'] = true;
                    $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out', 'pm_in', 'pm_out']);
                    break;
                case 'halfday_am':
                    $dtrData[$dayNum]['am_in'] = 'ABSENT';
                    $dtrData[$dayNum]['am_out'] = 'ABSENT';
                    if ($edit->field === 'am_out' && $edit->new_value) {
                        $dtrData[$dayNum]['am_out'] = $edit->new_value;
                    }
                    $dtrData[$dayNum]['remarks'] = 'Halfday (AM)';
                    $dtrData[$dayNum]['has_punch'] = true;
                    $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                    break;
                case 'halfday_pm':
                    $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                    $dtrData[$dayNum]['pm_out'] = 'ABSENT';
                    if ($edit->field === 'pm_in' && $edit->new_value) {
                        $dtrData[$dayNum]['pm_in'] = $edit->new_value;
                    }
                    $dtrData[$dayNum]['remarks'] = 'Halfday (PM)';
                    $dtrData[$dayNum]['has_punch'] = true;
                    $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                    break;
                case 'on_leave':
                    $leaveField = $edit->field ?: 'whole_day';
                    if ($leaveField === 'am') {
                        $dtrData[$dayNum]['am_in'] = 'ON LEAVE';
                        $dtrData[$dayNum]['am_out'] = 'ON LEAVE';
                        $dtrData[$dayNum]['remarks'] = 'On Leave (AM)';
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                    } elseif ($leaveField === 'pm') {
                        $dtrData[$dayNum]['pm_in'] = 'ON LEAVE';
                        $dtrData[$dayNum]['pm_out'] = 'ON LEAVE';
                        $dtrData[$dayNum]['remarks'] = 'On Leave (PM)';
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                    } else {
                        $dtrData[$dayNum]['remarks'] = 'On Leave';
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], []);
                    }
                    $dtrData[$dayNum]['has_punch'] = true;
                    break;
                case 'wfh':
                    $dtrData[$dayNum]['has_punch'] = true;
                    $wfhType = $edit->new_value ?: 'whole_day';
                    if ($wfhType === 'am') {
                        $dtrData[$dayNum]['remarks'] = 'WFH (AM)';
                    } elseif ($wfhType === 'pm') {
                        $dtrData[$dayNum]['remarks'] = 'WFH (PM)';
                    } else {
                        $dtrData[$dayNum]['remarks'] = 'WFH';
                    }
                    break;
                case 'holiday':
                    $dtrData[$dayNum]['remarks'] = 'Holiday';
                    $dtrData[$dayNum]['has_punch'] = true;
                    $dtrData[$dayNum]['is_holiday'] = true;
                    break;
                case 'work_suspension':
                    $dtrData[$dayNum]['remarks'] = 'Work Suspension';
                    $dtrData[$dayNum]['has_punch'] = true;
                    $dtrData[$dayNum]['is_work_suspension'] = true;
                    break;
                case 'special_order':
                    $dtrData[$dayNum]['has_punch'] = true;
                    $dtrData[$dayNum]['so_number'] = $edit->new_value;
                    $dtrData[$dayNum]['remarks'] = 'Special Order';
                    break;
                case 'travel_order':
                    $dtrData[$dayNum]['has_punch'] = true;
                    $dtrData[$dayNum]['to_number'] = $edit->new_value;
                    $dtrData[$dayNum]['remarks'] = 'Travel Order';
                    break;
                case 'official_business':
                    $dtrData[$dayNum]['has_punch'] = true;
                    $dtrData[$dayNum]['ob_number'] = $edit->new_value;
                    $dtrData[$dayNum]['remarks'] = 'Official Business';
                    break;
            }
        }

        foreach ($dtrData as $dayNum => &$day) {
            $isSpecial = !empty($day['is_wfh']) || !empty($day['so_number']) || !empty($day['to_number']) || !empty($day['ob_number']) || !empty($day['is_holiday']) || !empty($day['is_work_suspension']) || (isset($day['remarks']) && (strpos($day['remarks'], 'LS:') !== false || strpos($day['remarks'], 'WFH') === 0 || strpos($day['remarks'], 'On Leave') !== false));
            if (!$isSpecial) {
                $schedule = $this->getScheduleForWorkWeek($day['work_week_type'] ?? $empDefaultWW, $settings);
                $day['remarks'] = $this->recalcRemarks($day, $settings, $schedule);
            }
        }
        unset($day);

        $lateCount = 0;
        $undertimeCount = 0;
        $absentCount = 0;

        foreach ($dtrData as $dayNum => $day) {
            $remarks = $day['remarks'] ?? '';
            $hasLate = strpos($remarks, 'Late:') !== false;
            $hasUt = strpos($remarks, 'UT:') !== false;
            if ($hasLate) $lateCount++;
            if ($hasUt) $undertimeCount++;
            if (strpos($remarks, 'Absent') !== false) $absentCount++;
        }
        $lateUndertimeCount = $lateCount + $undertimeCount;

        return [
            'employee' => $employee,
            'late_days' => $lateCount,
            'undertime_days' => $undertimeCount,
            'late_undertime_days' => $lateUndertimeCount,
            'absent_days' => $absentCount,
        ];
    }

    public function printAll(Request $request)
    {
        $user = auth()->user();
        if (!$user->is_super) {
            if (!$user->is_coa) {
                abort(403);
            }
            $request->validate([
                'month' => 'required|integer|between:1,12',
                'year' => 'required|integer|between:2000,2100',
            ]);
            $cm = (int) $request->month;
            $cy = (int) $request->year;
            $isShared = DtrMonthlyShare::where('month', $cm)->where('year', $cy)->exists();
            if (!$isShared) {
                abort(403, 'DTRs for this month have not been shared with COA yet.');
            }
        }

        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|between:2000,2100',
        ]);
        $month = (int) $request->month;
        $year = (int) $request->year;

        $settings = DtrSetting::getSettings();
        $settings = $this->backupOriginalSchedule($settings);
        $settings = $this->applyFourDaySettings($settings);

        $protectedBioIds = \App\Models\User::where(function ($q) {
                $q->where('is_super', true)->orWhere('is_coa', true);
            })
            ->whereNotNull('emp_code')
            ->pluck('emp_code')
            ->toArray();

        $ahUserId = $settings['agency_head_user_id'] ?? null;
        if ($ahUserId) {
            $ahUser = \App\Models\User::find($ahUserId);
            if ($ahUser && $ahUser->emp_code) {
                $protectedBioIds[] = $ahUser->emp_code;
            }
        }
        $ahName = $settings['agency_head_name'] ?? '';
        if ($ahName) {
            $nameWords = array_filter(explode(' ', preg_replace('/[^a-zA-Z0-9 ]/', '', $ahName)), function ($w) { return strlen(trim($w)) > 2; });
            $query = \App\Models\User::whereNotNull('emp_code');
            foreach ($nameWords as $word) {
                $query->where('name', 'like', '%' . trim($word) . '%');
            }
            $ahUser = $query->first();
            if ($ahUser && $ahUser->emp_code) {
                $protectedBioIds[] = $ahUser->emp_code;
            }
        }

        $protectedBioIds = array_values(array_unique(array_filter($protectedBioIds)));

        $employees = DtrUser::where('is_active', true)
            ->whereNotIn('emp_code', $protectedBioIds)
            ->orderBy('office')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $monthName = date('F', mktime(0, 0, 0, $month, 1));

        // Batch load all approved edit requests for ALL employees this month to avoid N+1
        $empIds = $employees->pluck('id');
        $allApprovedEdits = DtrEditRequest::with('employee')
            ->whereIn('employee_id', $empIds)
            ->whereBetween('target_date', ["$year-$month-01", "$year-$month-$daysInMonth"])
            ->approved()
            ->get()
            ->groupBy(function ($edit) {
                return $edit->employee->emp_code;
            });

        $allDayOverrides = DtrDayOverride::whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('target_date', ["$year-$month-01", "$year-$month-$daysInMonth"])
            ->get()
            ->groupBy('employee_id');

        $allDtrs = [];
        foreach ($employees as $employee) {
            $dtrData = $this->computeDtr($employee->emp_code, $year, $month, $settings, $employee->default_work_week ?? null);

            $empDefaultWW = $employee->default_work_week ?? (($settings['four_day_work_week'] ?? '0') === '1' ? '4-day' : '5-day');

            $dtrData = $this->applyGlobalHolidays($dtrData, $year, $month, $empDefaultWW);

            $approvedEdits = $allApprovedEdits->get($employee->emp_code, collect());

            foreach ($approvedEdits as $edit) {
                $dayNum = (int) $edit->target_date->format('j');
                if (!isset($dtrData[$dayNum])) {
                    $dtrData[$dayNum] = [
                        'am_in' => '', 'am_out' => '', 'pm_in' => '', 'pm_out' => '',
                        'total_hours' => '', 'remarks' => '', 'has_punch' => false, 'edited_fields' => [],
                    ];
                }
                $dtrData[$dayNum]['is_edited'] = true;

                switch ($edit->type) {
                    case 'time_correction':
                        $dtrData[$dayNum][$edit->field] = $edit->new_value;
                        $dtrData[$dayNum]['has_punch'] = true;
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], [$edit->field]);
                        break;
                    case 'absent':
                        $dtrData[$dayNum]['am_in'] = 'ABSENT';
                        $dtrData[$dayNum]['am_out'] = 'ABSENT';
                        $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                        $dtrData[$dayNum]['pm_out'] = 'ABSENT';
                        $dtrData[$dayNum]['total_hours'] = '';
                        $dtrData[$dayNum]['remarks'] = 'Absent';
                        $dtrData[$dayNum]['has_punch'] = true;
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out', 'pm_in', 'pm_out']);
                        break;
                    case 'halfday_am':
                        $dtrData[$dayNum]['am_in'] = 'ABSENT';
                        $dtrData[$dayNum]['am_out'] = 'ABSENT';
                        if ($edit->field === 'am_out' && $edit->new_value) {
                            $dtrData[$dayNum]['am_out'] = $edit->new_value;
                        }
                        $dtrData[$dayNum]['remarks'] = 'Halfday (AM)';
                        $dtrData[$dayNum]['has_punch'] = true;
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                        break;
                    case 'halfday_pm':
                        $dtrData[$dayNum]['pm_in'] = 'ABSENT';
                        $dtrData[$dayNum]['pm_out'] = 'ABSENT';
                        if ($edit->field === 'pm_in' && $edit->new_value) {
                            $dtrData[$dayNum]['pm_in'] = $edit->new_value;
                        }
                        $dtrData[$dayNum]['remarks'] = 'Halfday (PM)';
                        $dtrData[$dayNum]['has_punch'] = true;
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                        break;
                    case 'on_leave':
                        $leaveField = $edit->field ?: 'whole_day';
                        $leaveTypeLabel = $edit->reason ? ' (' . $edit->reason . ')' : '';
                        if ($leaveField === 'am') {
                            $dtrData[$dayNum]['am_in'] = 'ON LEAVE';
                            $dtrData[$dayNum]['am_out'] = 'ON LEAVE';
                            $dtrData[$dayNum]['leave_credit_minutes'] = max(1, (int) ($edit->new_value ?: '4')) * 60;
                            $dtrData[$dayNum]['remarks'] = ($r && strpos($r, 'On Leave') !== false) ? $r : (($r ?? '') ? $r . ' | On Leave (AM)' . $leaveTypeLabel : 'On Leave (AM)' . $leaveTypeLabel);
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                        } elseif ($leaveField === 'pm') {
                            $dtrData[$dayNum]['pm_in'] = 'ON LEAVE';
                            $dtrData[$dayNum]['pm_out'] = 'ON LEAVE';
                            $dtrData[$dayNum]['leave_credit_minutes'] = max(1, (int) ($edit->new_value ?: '4')) * 60;
                            $dtrData[$dayNum]['remarks'] = 'On Leave (PM)' . $leaveTypeLabel;
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                        } else {
                            $dtrData[$dayNum]['am_in'] = 'ON LEAVE';
                            $dtrData[$dayNum]['am_out'] = 'ON LEAVE';
                            $dtrData[$dayNum]['pm_in'] = 'ON LEAVE';
                            $dtrData[$dayNum]['pm_out'] = 'ON LEAVE';
                            $dtrData[$dayNum]['total_hours'] = sprintf('%02d:00', max(1, (int) ($edit->new_value ?: '8')));
                            $dtrData[$dayNum]['remarks'] = 'On Leave' . $leaveTypeLabel;
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out', 'pm_in', 'pm_out']);
                        }
                        $dtrData[$dayNum]['has_punch'] = true;
                        break;
                    case 'holiday':
                        $dtrData[$dayNum]['remarks'] = 'Holiday';
                        $dtrData[$dayNum]['has_punch'] = true;
                        $dtrData[$dayNum]['is_holiday'] = true;
                        $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], []);
                        break;
                    case 'wfh':
                        $dtrData[$dayNum]['has_punch'] = true;
                        $wfhType = $edit->new_value ?: 'whole_day';
                        if ($wfhType === 'am') {
                            $dtrData[$dayNum]['am_in'] = 'WFH';
                            $dtrData[$dayNum]['am_out'] = 'WFH';
                            $dtrData[$dayNum]['remarks'] = 'WFH (AM)';
                            $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                        } elseif ($wfhType === 'pm') {
                            $dtrData[$dayNum]['pm_in'] = 'WFH';
                            $dtrData[$dayNum]['pm_out'] = 'WFH';
                            $dtrData[$dayNum]['remarks'] = 'WFH (PM)';
                            $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                        } else {
                            $dtrData[$dayNum]['am_in'] = 'WFH';
                            $dtrData[$dayNum]['am_out'] = 'WFH';
                            $dtrData[$dayNum]['pm_in'] = 'WFH';
                            $dtrData[$dayNum]['pm_out'] = 'WFH';
                            $dtrData[$dayNum]['is_wfh'] = true;
                            $dtrData[$dayNum]['remarks'] = 'WFH';
                            $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '10:00' : '08:00';
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out', 'pm_in', 'pm_out']);
                        }
                        break;
                    case 'special_order':
                        $soType = $edit->field ?: 'whole_day';
                        $soNum = $edit->new_value;
                        $dtrData[$dayNum]['has_punch'] = true;
                        if ($soType === 'am') {
                            $dtrData[$dayNum]['am_in'] = 'SO: ' . $soNum;
                            $dtrData[$dayNum]['am_out'] = 'SO: ' . $soNum;
                            $dtrData[$dayNum]['so_number'] = $soNum;
                            $dtrData[$dayNum]['remarks'] = 'Special Order' . ($soNum ? ': ' . $soNum : '') . ' (AM)';
                            $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                        } elseif ($soType === 'pm') {
                            $dtrData[$dayNum]['pm_in'] = 'SO: ' . $soNum;
                            $dtrData[$dayNum]['pm_out'] = 'SO: ' . $soNum;
                            $dtrData[$dayNum]['so_number'] = $soNum;
                            $dtrData[$dayNum]['remarks'] = 'Special Order' . ($soNum ? ': ' . $soNum : '') . ' (PM)';
                            $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                        } else {
                            $dtrData[$dayNum]['remarks'] = 'Special Order' . ($soNum ? ': ' . $soNum : '');
                            $dtrData[$dayNum]['so_number'] = $soNum;
                            $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '10:00' : '08:00';
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], []);
                        }
                        break;
                    case 'travel_order':
                        $toType = $edit->field ?: 'whole_day';
                        $toNum = $edit->new_value;
                        $dtrData[$dayNum]['has_punch'] = true;
                        if ($toType === 'am') {
                            $dtrData[$dayNum]['am_in'] = 'TO: ' . $toNum;
                            $dtrData[$dayNum]['am_out'] = 'TO: ' . $toNum;
                            $dtrData[$dayNum]['to_number'] = $toNum;
                            $dtrData[$dayNum]['remarks'] = 'Travel Order' . ($toNum ? ': ' . $toNum : '') . ' (AM)';
                            $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                        } elseif ($toType === 'pm') {
                            $dtrData[$dayNum]['pm_in'] = 'TO: ' . $toNum;
                            $dtrData[$dayNum]['pm_out'] = 'TO: ' . $toNum;
                            $dtrData[$dayNum]['to_number'] = $toNum;
                            $dtrData[$dayNum]['remarks'] = 'Travel Order' . ($toNum ? ': ' . $toNum : '') . ' (PM)';
                            $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                        } else {
                            $dtrData[$dayNum]['remarks'] = 'Travel Order' . ($toNum ? ': ' . $toNum : '');
                            $dtrData[$dayNum]['to_number'] = $toNum;
                            $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '10:00' : '08:00';
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], []);
                        }
                        break;
                    case 'official_business':
                        $obType = $edit->field ?: 'whole_day';
                        $obNum = $edit->new_value;
                        $dtrData[$dayNum]['has_punch'] = true;
                        if ($obType === 'am') {
                            $dtrData[$dayNum]['ob_number'] = $obNum;
                            $dtrData[$dayNum]['am_in'] = 'OB: ' . $obNum;
                            $dtrData[$dayNum]['am_out'] = 'OB: ' . $obNum;
                            $dtrData[$dayNum]['remarks'] = 'Official Business' . ($obNum ? ': ' . $obNum : '') . ($edit->reason ? ' - ' . $edit->reason : '') . ' (AM)';
                            $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['am_in', 'am_out']);
                        } elseif ($obType === 'pm') {
                            $dtrData[$dayNum]['ob_number'] = $obNum;
                            $dtrData[$dayNum]['pm_in'] = 'OB: ' . $obNum;
                            $dtrData[$dayNum]['pm_out'] = 'OB: ' . $obNum;
                            $dtrData[$dayNum]['remarks'] = 'Official Business' . ($obNum ? ': ' . $obNum : '') . ($edit->reason ? ' - ' . $edit->reason : '') . ' (PM)';
                            $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], ['pm_in', 'pm_out']);
                        } else {
                            $dtrData[$dayNum]['remarks'] = 'Official Business' . ($obNum ? ': ' . $obNum : '') . ($edit->reason ? ' - ' . $edit->reason : '');
                            $dtrData[$dayNum]['ob_number'] = $obNum;
                            $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '10:00' : '08:00';
                            $dtrData[$dayNum]['edited_fields'] = array_merge($dtrData[$dayNum]['edited_fields'] ?? [], []);
                        }
                        break;
                }
            }

            foreach ($approvedEdits as $edit) {
                if (!in_array($edit->type, ['time_correction', 'halfday_am', 'halfday_pm'])) continue;
                $dayNum = (int) $edit->target_date->format('j');
                $day = $dtrData[$dayNum];
                $hasSo = !empty($day['so_number']);
                $hasTo = !empty($day['to_number']);
                $hasOb = !empty($day['ob_number']);
                $isOnLeave = ($day['am_in'] ?? '') === 'ON LEAVE' || ($day['pm_in'] ?? '') === 'ON LEAVE';
                if ($edit->type === 'time_correction') {
                    $dtrData[$dayNum]['total_hours'] = $this->recalcHours($day, $settings);
                    if (!$hasSo && !$hasTo && !$hasOb && !$isOnLeave) {
                        $dtrData[$dayNum]['remarks'] = $this->recalcRemarks($day, $settings);
                    }
                } else {
                    $dtrData[$dayNum]['total_hours'] = $this->recalcHours($day, $settings);
                }
            }

            foreach ($approvedEdits as $edit) {
                if (!in_array($edit->type, ['halfday_am', 'halfday_pm'])) continue;
                $dayNum = (int) $edit->target_date->format('j');
                if (strpos($dtrData[$dayNum]['remarks'] ?? '', 'Halfday') !== false) {
                    $dtrData[$dayNum]['total_hours'] = $empDefaultWW === '4-day' ? '05:00' : '04:00';
                }
            }

            foreach ($dtrData as $dayNum => &$day) {
                $isHalfday = strpos($day['remarks'] ?? '', '(AM)') !== false || strpos($day['remarks'] ?? '', '(PM)') !== false;
                if (!$isHalfday) continue;
                if (strpos($day['remarks'] ?? '', 'Halfday') === 0) continue;
                $isAm = strpos($day['remarks'] ?? '', '(AM)') !== false;
                $lateUt = '';
                if ($isAm) {
                    if (!empty($day['pm_in']) && preg_match('/^\d/', $day['pm_in'])) {
                        $pmStartTS = strtotime($settings['pm_start'] ?? '13:00');
                        $pmInTS = strtotime($day['pm_in']);
                        if ($pmInTS > $pmStartTS) {
                            $lateMins = ($pmInTS - $pmStartTS) / 60;
                            $lateUt = 'Late: PM ' . gmdate('H:i', $lateMins * 60);
                        }
                    }
                    $actualMins = $this->computePunchMinutes($day['pm_in'] ?? '', $day['pm_out'] ?? '');
                } else {
                    if (!empty($day['am_in']) && preg_match('/^\d/', $day['am_in'])) {
                        $amStartTS = strtotime($settings['am_start'] ?? '07:00');
                        $amLateThreshold = $amStartTS + ((int)($settings['am_start_flexi'] ?? 120)) * 60;
                        $amInTS = strtotime($day['am_in']);
                        if ($amInTS > $amLateThreshold) {
                            $lateMins = ($amInTS - $amLateThreshold) / 60;
                            $lateUt = 'Late: AM ' . gmdate('H:i', $lateMins * 60);
                        }
                    }
                    $actualMins = $this->computePunchMinutes($day['am_in'] ?? '', $day['am_out'] ?? '');
                }
                $ww = $day['work_week_type'] ?? $empDefaultWW;
                $expectedHalfMins = $ww === '4-day' ? 300 : 240;
                $utMins = $expectedHalfMins - $actualMins;
                if ($utMins > 0) {
                    $utStr = 'UT: ' . gmdate('H:i', $utMins * 60);
                    $lateUt = $lateUt ? $lateUt . ' | ' . $utStr : $utStr;
                }
                if ($lateUt) {
                    $day['remarks'] .= ' | ' . $lateUt;
                }
            }
            unset($day);

            $empOverrides = $allDayOverrides->get($employee->id, collect());
            foreach ($empOverrides as $override) {
                $dayNum = (int) $override->target_date->format('j');
                if (!isset($dtrData[$dayNum])) {
                    $dtrData[$dayNum] = ['am_in' => '', 'am_out' => '', 'pm_in' => '', 'pm_out' => '', 'total_hours' => '', 'remarks' => '', 'has_punch' => false];
                }
                $dtrData[$dayNum]['work_week_type'] = $override->work_week_type;
            }

            foreach ($dtrData as $dayNum => &$day) {
                if (!isset($day['work_week_type'])) continue;
                $schedule = $this->getScheduleForWorkWeek($day['work_week_type'], $settings);

                $isSpecial = !empty($day['is_wfh']) || !empty($day['so_number']) || !empty($day['to_number']) || !empty($day['ob_number']) || !empty($day['is_holiday']) || !empty($day['is_work_suspension']) || (isset($day['remarks']) && (strpos($day['remarks'], 'LS:') !== false || strpos($day['remarks'], 'WFH') === 0 || strpos($day['remarks'], 'On Leave') !== false));

                if (!$isSpecial) {
                    $day['remarks'] = $this->recalcRemarks($day, $settings, $schedule);
                }

                if ($isSpecial) {
                    if (isset($day['remarks']) && strpos($day['remarks'], 'LS:') !== false) {
                        // LS total_hours already set by LS case block
                    } elseif (isset($day['remarks']) && strpos($day['remarks'], 'On Leave') !== false) {
                        // On Leave: total_hours handled by recomputeHalfdayHours or set in switch
                    } else {
                        $isHalfday = strpos($day['remarks'] ?? '', '(AM)') !== false || strpos($day['remarks'] ?? '', '(PM)') !== false;
                        if ($isHalfday) {
                            $day['total_hours'] = $day['work_week_type'] === '4-day' ? '05:00' : '04:00';
                        } else {
                            $day['total_hours'] = $day['work_week_type'] === '4-day' ? '10:00' : '08:00';
                        }
                    }
                } elseif (strpos($day['remarks'] ?? '', 'Halfday') !== false) {
                    $day['total_hours'] = $day['work_week_type'] === '4-day' ? '05:00' : '04:00';
                }

                $wfhLabel = $this->resolveWfhLabel($day);
                if ($wfhLabel && !$isSpecial) {
                    $day['remarks'] = !empty($day['remarks']) ? $wfhLabel . ' | ' . $day['remarks'] : $wfhLabel;
                }

                if (!$isSpecial && (strpos($wfhLabel, '(AM)') !== false || strpos($wfhLabel, '(PM)') !== false)) {
                    $day['total_hours'] = $day['work_week_type'] === '4-day' ? '05:00' : '04:00';
                }
            }
            unset($day);

            $this->recomputeHalfdayHours($dtrData, $employee, $settings);

            foreach ($dtrData as $dayNum => &$day) {
                $ww = $day['work_week_type'] ?? $empDefaultWW;
                $expectedMins = $ww === '4-day' ? 600 : 480;
                $totalMins = 0;
                if (!empty($day['total_hours'])) {
                    $parts = explode(':', $day['total_hours']);
                    $totalMins = (int)($parts[0] ?? 0) * 60 + (int)($parts[1] ?? 0);
                }
                $utMins = $totalMins > 0 ? max(0, $expectedMins - $totalMins) : 0;
                $remarks = trim(preg_replace('/(?:^|\s*\|\s*)UT:\s*\d+:\d+/', '', $day['remarks'] ?? ''), ' |');
                $parts = [];
                if ($remarks !== '') $parts[] = $remarks;
                if ($utMins > 0) $parts[] = 'UT: ' . gmdate('H:i', $utMins * 60);
                $day['remarks'] = implode(' | ', $parts);
            }
            unset($day);

            $allDtrs[] = [
                'employee' => $employee,
                'dtrData' => $dtrData,
            ];
        }

        $isShared = DtrMonthlyShare::where('month', $month)->where('year', $year)->exists();

        $pendingCount = DtrEditRequest::whereBetween('target_date', ["$year-$month-01", "$year-$month-$daysInMonth"])
            ->where('status', 'pending')
            ->count();

        return view('dtr.print-all', compact('allDtrs', 'month', 'year', 'monthName', 'daysInMonth', 'settings', 'isShared', 'pendingCount'));
    }

    public function toggleShare(Request $request)
    {
        $user = auth()->user();
        if (!$user->is_super) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Only super admins can manage COA access.'], 403);
            }
            abort(403, 'Only super admins can manage COA access.');
        }

        $data = $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|between:2000,2100',
        ]);

        $month = (int) $data['month'];
        $year = (int) $data['year'];

        $existing = DtrMonthlyShare::where('month', $month)->where('year', $year)->first();

        if ($existing) {
            $existing->delete();
            $message = "COA access removed for " . date('F Y', mktime(0, 0, 0, $month, 1, $year)) . ".";
        } else {
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $start = sprintf('%04d-%02d-01', $year, $month);
            $end = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

            $pendingCount = DtrEditRequest::whereBetween('target_date', [$start, $end])
                ->where('status', 'pending')
                ->count();

            if ($pendingCount > 0) {
                $message = "Cannot share with COA. There are {$pendingCount} pending edit request(s) for {$start} to {$end} that must be resolved first.";
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $message], 422);
                }
                return redirect()->route('dtr.print-all', ['month' => $month, 'year' => $year])
                    ->with('error', $message);
            }

            DtrMonthlyShare::create([
                'shared_by' => auth()->id(),
                'month' => $month,
                'year' => $year,
            ]);
            $message = "DTRs for " . date('F Y', mktime(0, 0, 0, $month, 1, $year)) . " shared with COA.";
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'shared' => !$existing]);
        }

        return redirect()->route('dtr.print-all', ['month' => $month, 'year' => $year])
            ->with('success', $message);
    }

    private function applyFourDaySettings($settings)
    {
        if (($settings['four_day_work_week'] ?? '0') === '1') {
            $settings['am_start'] = $settings['fdww_am_start'] ?? '07:00';
            $settings['am_end'] = $settings['fdww_am_end'] ?? '12:00';
            $settings['pm_start'] = $settings['fdww_pm_start'] ?? '13:00';
            $settings['pm_end'] = $settings['fdww_pm_end'] ?? '19:00';
            $settings['am_start_flexi'] = $settings['fdww_am_start_flexi'] ?? '60';
            $settings['pm_end_flexi'] = $settings['fdww_pm_end_flexi'] ?? '60';
            $settings['max_dow'] = 4;
        } else {
            $settings['max_dow'] = 5;
        }
        return $settings;
    }

    private function backupOriginalSchedule($settings)
    {
        $settings['original_am_start'] = $settings['am_start'] ?? '07:00';
        $settings['original_am_end'] = $settings['am_end'] ?? '12:00';
        $settings['original_pm_start'] = $settings['pm_start'] ?? '13:00';
        $settings['original_pm_end'] = $settings['pm_end'] ?? '17:00';
        return $settings;
    }

    private function applyWorkWeekSettings($settings, $workWeekType)
    {
        if ($workWeekType === '4-day') {
            $settings['am_start'] = $settings['fdww_am_start'] ?? '07:00';
            $settings['am_end'] = $settings['fdww_am_end'] ?? '12:00';
            $settings['pm_start'] = $settings['fdww_pm_start'] ?? '13:00';
            $settings['pm_end'] = $settings['fdww_pm_end'] ?? '19:00';
            $settings['am_start_flexi'] = $settings['fdww_am_start_flexi'] ?? '60';
            $settings['pm_end_flexi'] = $settings['fdww_pm_end_flexi'] ?? '60';
            $settings['max_dow'] = 4;
        } else {
            $settings['am_start'] = '07:00';
            $settings['am_end'] = $settings['original_am_end'] ?? '12:00';
            $settings['pm_start'] = $settings['original_pm_start'] ?? '13:00';
            $settings['pm_end'] = $settings['original_pm_end'] ?? '17:00';
            $settings['am_start_flexi'] = '120';
            $settings['pm_end_flexi'] = $settings['pm_end_flexi'] ?? '60';
            $settings['max_dow'] = 5;
        }
        return $settings;
    }

    private function getScheduleForWorkWeek($workWeekType, $settings)
    {
        $local = $this->applyWorkWeekSettings($settings, $workWeekType);
        return [
            'am_start' => $local['am_start'],
            'am_end' => $local['am_end'],
            'pm_start' => $local['pm_start'],
            'pm_end' => $local['pm_end'],
            'am_start_flexi' => ((int)($local['am_start_flexi'] ?? 60)) * 60,
            'pm_end_flexi' => ((int)($local['pm_end_flexi'] ?? 60)) * 60,
            'max_dow' => $local['max_dow'],
        ];
    }

    private function checkIsSupervisor($user)
    {
        if ($user->is_super) return true;
        $dtrU = DtrUser::where('emp_code', $user->emp_code)->first();
        if (!$dtrU) return false;
        return Section::where('supervisor_id', $dtrU->id)->exists()
            || \App\Models\Office::where('supervisor_id', $dtrU->id)->exists();
    }

    private function recalcHours($day, $settings)
    {
        $amIn = $day['am_in'] ?? '';
        $amOut = $day['am_out'] ?? '';
        $pmIn = $day['pm_in'] ?? '';
        $pmOut = $day['pm_out'] ?? '';

        $hasAmIn = $amIn !== '' && preg_match('/^\d/', $amIn);
        $hasAmOut = $amOut !== '' && preg_match('/^\d/', $amOut);
        $hasPmIn = $pmIn !== '' && preg_match('/^\d/', $pmIn);
        $hasPmOut = $pmOut !== '' && preg_match('/^\d/', $pmOut);

        $hasAm = $hasAmIn || $hasAmOut;
        $hasPm = $hasPmIn || $hasPmOut;

        if (!$hasAm && !$hasPm) return '';

        if ($hasAmIn && $hasPmOut) {
            $amInTS = strtotime($amIn);
            $pmOutTS = strtotime($pmOut);
            $totalMinutesWorked = ($pmOutTS - $amInTS) / 60;
            $totalMins = (int)round($totalMinutesWorked - 60);
            if ($totalMins < 0) $totalMins = 0;
            $hours = floor($totalMins / 60);
            $mins = $totalMins % 60;
            return sprintf('%02d:%02d', $hours, $mins);
        }

        if ($hasPmIn && $hasPmOut) {
            $pmInTS = strtotime($pmIn);
            $pmOutTS = strtotime($pmOut);
            $totalMins = (int)round(($pmOutTS - $pmInTS) / 60);
            if ($totalMins < 0) $totalMins = 0;
            $hours = floor($totalMins / 60);
            $mins = $totalMins % 60;
            return sprintf('%02d:%02d', $hours, $mins);
        }

        if ($hasAmIn && $hasAmOut) {
            $amInTS = strtotime($amIn);
            $amOutTS = strtotime($amOut);
            $totalMins = (int)round(($amOutTS - $amInTS) / 60);
            if ($totalMins < 0) $totalMins = 0;
            $hours = floor($totalMins / 60);
            $mins = $totalMins % 60;
            return sprintf('%02d:%02d', $hours, $mins);
        }

        return '--:--';
    }

    private function recalcRemarks($day, $settings, $scheduleOverride = null)
    {
        if ($scheduleOverride) {
            $settingsAmStart = $scheduleOverride['am_start'];
            $settingsPmStart = $scheduleOverride['pm_start'];
            $settingsPmEnd = $scheduleOverride['pm_end'];
            $amStartFlexi = $scheduleOverride['am_start_flexi'];
            $pmEndFlexi = $scheduleOverride['pm_end_flexi'];
        } else {
            $settings = $this->applyFourDaySettings($settings);
            $settingsAmStart = $settings['am_start'] ?? '07:00';
            $settingsPmStart = $settings['pm_start'] ?? '13:00';
            $settingsPmEnd = $settings['pm_end'] ?? '17:00';
            $amStartFlexi = ((int)($settings['am_start_flexi'] ?? 120)) * 60;
            $pmEndFlexi = ((int)($settings['pm_end_flexi'] ?? 60)) * 60;
        }

        $amIn = $day['am_in'] ?? '';
        $amOut = $day['am_out'] ?? '';
        $pmIn = $day['pm_in'] ?? '';
        $pmOut = $day['pm_out'] ?? '';

        $remarks = [];

        $lateAM = 0;
        if ($amIn !== '' && preg_match('/^\d/', $amIn)) {
            $amStartTS = strtotime($settingsAmStart);
            $amLateThreshold = $amStartTS + $amStartFlexi;
            $amInTS = strtotime($amIn);
            if ($amInTS > $amLateThreshold) {
                $lateAM = ($amInTS - $amLateThreshold) / 60;
            }
        }
        $latePM = 0;
        if ($pmIn !== '' && preg_match('/^\d/', $pmIn)) {
            $pmStartTS = strtotime($settingsPmStart);
            $pmInTS = strtotime($pmIn);
            if ($pmInTS > $pmStartTS) {
                $latePM = ($pmInTS - $pmStartTS) / 60;
            }
        }
        if ($lateAM > 0 || $latePM > 0) {
            $parts = [];
            if ($lateAM > 0) $parts[] = 'AM ' . gmdate('H:i', $lateAM * 60);
            if ($latePM > 0) $parts[] = 'PM ' . gmdate('H:i', $latePM * 60);
            $remarks[] = 'Late: ' . implode(' + ', $parts);
        }

        $expectedDailyMins = 480;
        if ($scheduleOverride && isset($scheduleOverride['max_dow'])) {
            if ($scheduleOverride['max_dow'] === 4) {
                $expectedDailyMins = 600;
            }
        } elseif (($day['work_week_type'] ?? '') === '4-day') {
            $expectedDailyMins = 600;
        }

        $totalMins = 0;
        if (!empty($day['total_hours'])) {
            $parts = explode(':', $day['total_hours']);
            $totalMins = (int)($parts[0] ?? 0) * 60 + (int)($parts[1] ?? 0);
        }
        if ($totalMins > 0) {
            $undertimeMinutes = max(0, $expectedDailyMins - $totalMins);
            if ($undertimeMinutes > 0) {
                $remarks[] = 'UT: ' . gmdate('H:i', $undertimeMinutes * 60);
            }
        }

        return implode(' | ', $remarks);
    }

    private function computeDtr($bioId, $year, $month, $settings, $defaultWorkWeek = null)
    {
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $firstDay = "$year-$month-01";
        $lastDay = "$year-$month-$daysInMonth";

        $punches = IclockTransaction::forEmployee($bioId)
            ->forPeriod($firstDay, $lastDay)
            ->ordered()
            ->get();

        $settings = $this->applyFourDaySettings($settings);
        if ($defaultWorkWeek) {
            $settings = $this->applyWorkWeekSettings($settings, $defaultWorkWeek);
        }
        $settingsAmStart = $settings['am_start'] ?? '07:00';
        $settingsAmEnd = $settings['am_end'] ?? '12:00';
        $settingsPmStart = $settings['pm_start'] ?? '13:00';
        $settingsPmEnd = $settings['pm_end'] ?? '17:00';
        $amStartFlexi = ((int)($settings['am_start_flexi'] ?? 120)) * 60;
        $pmEndFlexi = ((int)($settings['pm_end_flexi'] ?? 60)) * 60;

        $seen = [];
        $daily = [];
        foreach ($punches as $p) {
            $key = $p->punch_time->format('Y-m-d H:i') . '|' . $p->punch_state;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $date = $p->punch_time->format('Y-m-d');
            $daily[$date][] = $p;
        }

        $dtr = [];
        foreach ($daily as $date => $dayPunches) {
            $dayNum = (int) date('j', strtotime($date));
            $dayOfWeek = date('l', strtotime($date));

            $amStart = strtotime($date . ' ' . $settingsAmStart);
            $amEnd = strtotime($date . ' ' . $settingsAmEnd);
            $pmStart = strtotime($date . ' ' . $settingsPmStart);
            $pmEnd = strtotime($date . ' ' . $settingsPmEnd);

            $amIn = null;
            $amOut = null;
            $pmIn = null;
            $pmOut = null;

            $pmPunches = [];

            foreach ($dayPunches as $p) {
                $t = $p->punch_time->timestamp;

                if ($t <= $amEnd) {
                    if (abs($t - $amStart) <= abs($t - $amEnd)) {
                        if ($amIn === null || $t < $amIn) $amIn = $t;
                    } else {
                        if ($amOut === null || $t > $amOut) $amOut = $t;
                    }
                } else {
                    $pmPunches[] = $t;
                }
            }

            sort($pmPunches);
            $pmCount = count($pmPunches);

            if ($pmCount === 1 && $pmPunches[0] > strtotime($date . ' ' . $settingsPmStart . ' +1 hour')) {
                $pmOut = $pmPunches[0];
            } elseif ($pmCount >= 1) {
                $pmIn = $pmPunches[0];
            }
            if ($pmCount >= 2) {
                $pmOut = $pmPunches[$pmCount - 1];
            }

            $remarks = [];

            $lateAM = 0;
            $amLateThreshold = $amStart + $amStartFlexi;
            if ($amIn && $amIn > $amLateThreshold) {
                $lateAM = ($amIn - $amLateThreshold) / 60;
            }
            $latePM = 0;
            if ($pmIn && $pmIn > $pmStart) {
                $latePM = ($pmIn - $pmStart) / 60;
            }
            if ($lateAM > 0 || $latePM > 0) {
                $parts = [];
                if ($lateAM > 0) $parts[] = 'AM ' . gmdate('H:i', $lateAM * 60);
                if ($latePM > 0) $parts[] = 'PM ' . gmdate('H:i', $latePM * 60);
                $remarks[] = 'Late: ' . implode(' + ', $parts);
            }

            $totalHours = 0;
            $totalMins = 0;
            if ($amIn && $pmOut) {
                $totalMinutesWorked = ($pmOut - $amIn) / 60;
                $totalMins = (int)round($totalMinutesWorked - 60);
                if ($totalMins < 0) $totalMins = 0;
                $hours = floor($totalMins / 60);
                $mins = $totalMins % 60;
                $totalHours = sprintf('%02d:%02d', $hours, $mins);
            } elseif ($amIn || $pmOut) {
                $totalHours = '--:--';
            }

            $expectedDailyMins = $defaultWorkWeek === '4-day' ? 600 : 480;
            if ($totalHours !== '' && $totalHours !== '--:--' && $totalMins > 0) {
                $undertimeMinutes = max(0, $expectedDailyMins - $totalMins);
                if ($undertimeMinutes > 0) {
                    $remarks[] = 'UT: ' . gmdate('H:i', $undertimeMinutes * 60);
                }
            }

            $dtr[$dayNum] = [
                'am_in' => $amIn ? date('H:i', $amIn) : '',
                'am_out' => $amOut ? date('H:i', $amOut) : '',
                'pm_in' => $pmIn ? date('H:i', $pmIn) : '',
                'pm_out' => $pmOut ? date('H:i', $pmOut) : '',
                'total_hours' => $totalHours,
                'remarks' => implode(' | ', $remarks),
                'has_punch' => $amIn !== null || $amOut !== null || $pmIn !== null || $pmOut !== null,
            ];
        }

        return $dtr;
    }

    public function toggleWorkWeek(Request $request)
    {
        $setting = DtrSetting::where('setting_key', 'four_day_work_week')->first();
        if ($setting) {
            $val = $request->input('value');
            if (!in_array($val, ['0', '1'])) {
                return response()->json(['success' => false], 400);
            }
            $setting->update(['setting_value' => $val]);
            return response()->json(['success' => true, 'value' => $val]);
        }
        return response()->json(['success' => false], 400);
    }

    public function toggleDayWorkWeek(Request $request)
    {
        $user = auth()->user();
        $employee = DtrUser::where('emp_code', $user->emp_code)->firstOrFail();

        $data = $request->validate([
            'target_date' => 'required|date',
            'work_week_type' => 'required|in:5-day,4-day',
        ]);

        DtrDayOverride::updateOrCreate(
            [
                'employee_id' => $employee->id,
                'target_date' => $data['target_date'],
            ],
            [
                'work_week_type' => $data['work_week_type'],
            ]
        );

        return response()->json(['success' => true]);
    }

    private function applyGlobalHolidays($dtrData, $year, $month, $empDefaultWW)
    {
        $globalHolidays = Cache::remember('global_holidays.' . $year . '.' . $month, 1440, function () use ($year, $month) {
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            return GlobalHoliday::whereBetween('target_date', ["$year-$month-01", "$year-$month-$daysInMonth"])
                ->orderBy('target_date')
                ->get();
        });

        foreach ($globalHolidays as $holiday) {
            $dayNum = (int) $holiday->target_date->format('j');

            if (!isset($dtrData[$dayNum])) {
                $dtrData[$dayNum] = [
                    'am_in' => '', 'am_out' => '', 'pm_in' => '', 'pm_out' => '',
                    'total_hours' => '', 'remarks' => '', 'has_punch' => false,
                ];
            }

            $desc = $holiday->description ? ' (' . $holiday->description . ')' : '';

            if ($holiday->type === 'holiday') {
                if ($holiday->value === 'am') {
                    $dtrData[$dayNum]['am_in'] = 'HOLIDAY';
                    $dtrData[$dayNum]['am_out'] = 'HOLIDAY';
                    $dtrData[$dayNum]['remarks'] = 'Holiday (AM)' . $desc;
                } elseif ($holiday->value === 'pm') {
                    $dtrData[$dayNum]['pm_in'] = 'HOLIDAY';
                    $dtrData[$dayNum]['pm_out'] = 'HOLIDAY';
                    $dtrData[$dayNum]['remarks'] = 'Holiday (PM)' . $desc;
                } else {
                    $dtrData[$dayNum]['am_in'] = 'HOLIDAY';
                    $dtrData[$dayNum]['am_out'] = 'HOLIDAY';
                    $dtrData[$dayNum]['pm_in'] = 'HOLIDAY';
                    $dtrData[$dayNum]['pm_out'] = 'HOLIDAY';
                    $dtrData[$dayNum]['is_holiday'] = true;
                    $dtrData[$dayNum]['remarks'] = 'Holiday' . $desc;
                }
                $dtrData[$dayNum]['has_punch'] = true;
            } elseif ($holiday->type === 'work_suspension') {
                if ($holiday->value === 'am') {
                    $dtrData[$dayNum]['am_in'] = 'WORK SUSPENSION';
                    $dtrData[$dayNum]['am_out'] = 'WORK SUSPENSION';
                    $dtrData[$dayNum]['remarks'] = 'Work Suspension (AM)' . $desc;
                } elseif ($holiday->value === 'pm') {
                    $dtrData[$dayNum]['pm_in'] = 'WORK SUSPENSION';
                    $dtrData[$dayNum]['pm_out'] = 'WORK SUSPENSION';
                    $dtrData[$dayNum]['remarks'] = 'Work Suspension (PM)' . $desc;
                } else {
                    $dtrData[$dayNum]['am_in'] = 'WORK SUSPENSION';
                    $dtrData[$dayNum]['am_out'] = 'WORK SUSPENSION';
                    $dtrData[$dayNum]['pm_in'] = 'WORK SUSPENSION';
                    $dtrData[$dayNum]['pm_out'] = 'WORK SUSPENSION';
                    $dtrData[$dayNum]['total_hours'] = '';
                    $dtrData[$dayNum]['remarks'] = 'Work Suspension' . $desc;
                }
                $dtrData[$dayNum]['has_punch'] = true;
            }
        }

        return $dtrData;
    }

    private function resolveWfhLabel($day)
    {
        if (!empty($day['is_wfh'])) return 'WFH';
        if (!empty($day['so_number'])) return 'Special Order' . (!empty($day['so_number']) ? ': ' . $day['so_number'] : '');
        if (!empty($day['to_number'])) return 'Travel Order' . (!empty($day['to_number']) ? ': ' . $day['to_number'] : '');
        if (!empty($day['ob_number'])) return 'Official Business' . (!empty($day['ob_number']) ? ': ' . $day['ob_number'] : '');

        $am = $day['am_in'] ?? '';
        $pm = $day['pm_in'] ?? '';

        if (strpos($am, 'SO:') === 0 && strpos($pm, 'SO:') === 0) return 'Special Order: ' . substr($am, 4);
        if (strpos($am, 'SO:') === 0) return 'Special Order: ' . substr($am, 4) . ' (AM)';
        if (strpos($pm, 'SO:') === 0) return 'Special Order: ' . substr($pm, 4) . ' (PM)';

        if (strpos($am, 'TO:') === 0 && strpos($pm, 'TO:') === 0) return 'Travel Order: ' . substr($am, 4);
        if (strpos($am, 'TO:') === 0) return 'Travel Order: ' . substr($am, 4) . ' (AM)';
        if (strpos($pm, 'TO:') === 0) return 'Travel Order: ' . substr($pm, 4) . ' (PM)';

        if (strpos($am, 'OB:') === 0 && strpos($pm, 'OB:') === 0) return 'Official Business: ' . substr($am, 4);
        if (strpos($am, 'OB:') === 0) return 'Official Business: ' . substr($am, 4) . ' (AM)';
        if (strpos($pm, 'OB:') === 0) return 'Official Business: ' . substr($pm, 4) . ' (PM)';

        if ($am === 'WFH' && $pm === 'WFH') return 'WFH';
        if ($am === 'WFH') return 'WFH (AM)';
        if ($pm === 'WFH') return 'WFH (PM)';

        if (strpos($am, 'LS:') === 0 && strpos($pm, 'LS:') === 0) return 'Locator Slip: ' . substr($am, 4);
        if (strpos($am, 'LS:') === 0) return 'Locator Slip: ' . substr($am, 4) . ' (AM)';
        if (strpos($pm, 'LS:') === 0) return 'Locator Slip: ' . substr($pm, 4) . ' (PM)';

        return '';
    }

    private function computePunchMinutes($in, $out)
    {
        if ($in !== '' && $out !== '' && preg_match('/^\d/', $in) && preg_match('/^\d/', $out)) {
            $diff = (strtotime($out) - strtotime($in)) / 60;
            return $diff > 0 ? $diff : 0;
        }
        return 0;
    }

    private function recomputeHalfdayHours(array &$dtrData, $employee, $settings)
    {
        $empDefaultWW = $employee->default_work_week ?? (($settings['four_day_work_week'] ?? '0') === '1' ? '4-day' : '5-day');
        foreach ($dtrData as $dayNum => &$day) {
            $isHalfday = strpos($day['remarks'] ?? '', '(AM)') !== false || strpos($day['remarks'] ?? '', '(PM)') !== false;
            if (!$isHalfday) continue;

            if (strpos($day['remarks'] ?? '', 'Halfday') !== 0) {
                $ww = $day['work_week_type'] ?? $empDefaultWW;
                $schedule = $this->getScheduleForWorkWeek($ww, $settings);

                if (strpos($day['remarks'] ?? '', 'Late:') === false && strpos($day['remarks'] ?? '', 'UT:') === false && strpos($day['remarks'] ?? '', 'WFH') === false) {
                    $amIn = $day['am_in'] ?? '';
                    $amOut = $day['am_out'] ?? '';
                    $pmIn = $day['pm_in'] ?? '';
                    $pmOut = $day['pm_out'] ?? '';

                    $remarks = [];

                    $lateAM = 0;
                    if ($amIn !== '' && preg_match('/^\d/', $amIn)) {
                        $amStartTS = strtotime($schedule['am_start']);
                        $amLateThreshold = $amStartTS + $schedule['am_start_flexi'];
                        $amInTS = strtotime($amIn);
                        if ($amInTS > $amLateThreshold) {
                            $lateAM = ($amInTS - $amLateThreshold) / 60;
                        }
                    }
                    $latePM = 0;
                    if ($pmIn !== '' && preg_match('/^\d/', $pmIn)) {
                        $pmStartTS = strtotime($schedule['pm_start']);
                        $pmInTS = strtotime($pmIn);
                        if ($pmInTS > $pmStartTS) {
                            $latePM = ($pmInTS - $pmStartTS) / 60;
                        }
                    }
                    if ($lateAM > 0 || $latePM > 0) {
                        $parts = [];
                        if ($lateAM > 0) $parts[] = 'AM ' . gmdate('H:i', $lateAM * 60);
                        if ($latePM > 0) $parts[] = 'PM ' . gmdate('H:i', $latePM * 60);
                        $remarks[] = 'Late: ' . implode(' + ', $parts);
                    }

                    $undertimeMinutes = 0;
                    if ($pmIn && preg_match('/^\d/', $pmIn) && $pmOut) {
                        $pmEndTS = strtotime($schedule['pm_end']);
                        $pmUndertimeThreshold = $pmEndTS - $schedule['pm_end_flexi'];
                        $pmOutTS = strtotime($pmOut);
                        if ($pmOutTS < $pmUndertimeThreshold) {
                            $undertimeMinutes = ($pmEndTS - $pmOutTS) / 60;
                        }
                    }
                    if ($undertimeMinutes > 0) {
                        $remarks[] = 'UT: ' . gmdate('H:i', $undertimeMinutes * 60);
                    }

                    $lateUt = implode(' | ', $remarks);
                    if ($lateUt) {
                        $day['remarks'] = ($day['remarks'] ?? '') . ' | ' . $lateUt;
                    }
                }
            }

            $ww = $day['work_week_type'] ?? $empDefaultWW;
            $halfdayMinutes = $day['leave_credit_minutes'] ?? ($ww === '4-day' ? 300 : 240);

            if (strpos($day['remarks'] ?? '', '(AM)') !== false) {
                $punchMinutes = $this->computePunchMinutes($day['pm_in'] ?? '', $day['pm_out'] ?? '');
            } else {
                $punchMinutes = $this->computePunchMinutes($day['am_in'] ?? '', $day['am_out'] ?? '');
            }

            $totalMin = $punchMinutes + $halfdayMinutes;
            $hours = floor($totalMin / 60);
            $mins = round($totalMin % 60);
            $day['total_hours'] = sprintf('%02d:%02d', $hours, $mins);
        }
        unset($day);
    }
}



