<?php

namespace App\Http\Controllers;

use App\Models\DtrEditRequest;
use App\Models\DtrMonthlyShare;
use App\Models\DtrSetting;
use App\Models\DtrUser;
use App\Models\GlobalHoliday;
use App\Models\Office;
use App\Models\PasswordResetRequest;
use App\Models\Section;
use App\Models\User;
use App\Models\Memo;
use App\Models\UserLog;
use App\Services\UserLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'super']);
    }

    public function dashboard()
    {
        $officeCount = Office::count();
        $sectionCount = Section::count();
        $employeeCount = DtrUser::count();
        $unassignedCount = DtrUser::whereNull('section_id')->count();
        return view('admin.dashboard', compact('officeCount', 'sectionCount', 'employeeCount', 'unassignedCount'));
    }

    public function offices(Request $request)
    {
        $offices = Office::with(['sections', 'supervisor', 'seniorManager', 'oic', 'seniorManagerOic'])->withCount('sections')->orderBy('name');
        if ($request->filled('search')) {
            $offices->where('name', 'like', '%' . $request->search . '%');
        }
        $offices = $offices->get();
        $employees = DtrUser::orderBy('last_name')->orderBy('first_name')->get();
        return view('admin.offices', compact('offices', 'employees'));
    }

    public function storeOffice(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:200|unique:offices']);
        Office::create($data);
        return redirect()->route('admin.offices')->with('success', 'Office created.');
    }

    public function deleteOffice(Office $office)
    {
        $office->delete();
        return redirect()->route('admin.offices')->with('success', 'Office deleted.');
    }

    public function assignOfficeSupervisor(Request $request, Office $office)
    {
        $data = $request->validate([
            'supervisor_id' => 'nullable|exists:dtr_users,id',
        ]);
        $office->update(['supervisor_id' => $data['supervisor_id']]);
        return redirect()->route('admin.offices')->with('success', 'Office supervisor assigned.');
    }

    public function assignSeniorManager(Request $request, Office $office)
    {
        $data = $request->validate([
            'senior_manager_id' => 'nullable|exists:dtr_users,id',
        ]);
        $office->update(['senior_manager_id' => $data['senior_manager_id']]);
        return redirect()->route('admin.offices')->with('success', 'Senior manager assigned.');
    }

    public function assignOic(Request $request, Office $office)
    {
        $data = $request->validate([
            'oic_id' => 'nullable|exists:dtr_users,id',
        ]);
        $office->update(['oic_id' => $data['oic_id']]);
        return redirect()->route('admin.offices')->with('success', 'OIC assigned.');
    }

    public function assignSeniorManagerOic(Request $request, Office $office)
    {
        $data = $request->validate([
            'senior_manager_oic_id' => 'nullable|exists:dtr_users,id',
        ]);
        $office->update(['senior_manager_oic_id' => $data['senior_manager_oic_id']]);
        return redirect()->route('admin.offices')->with('success', 'Senior Manager OIC assigned.');
    }

    public function sections(Request $request)
    {
        $offices = Office::with('sections')->orderBy('name')->get();
        $sections = Section::with(['office', 'supervisor', 'oic'])->orderBy('name');
        if ($request->filled('search')) {
            $search = $request->search;
            $sections->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhereHas('office', function ($oq) use ($search) {
                      $oq->where('name', 'like', '%' . $search . '%');
                  });
            });
        }
        $sections = $sections->get();
        $employees = DtrUser::orderBy('last_name')->orderBy('first_name')->get();
        return view('admin.sections', compact('offices', 'sections', 'employees'));
    }

    public function storeSection(Request $request)
    {
        $data = $request->validate([
            'office_id' => 'required|exists:offices,id',
            'name' => 'required|string|max:200',
        ]);
        Section::create($data);
        return redirect()->route('admin.sections')->with('success', 'Section created.');
    }

    public function deleteSection(Section $section)
    {
        $section->delete();
        return redirect()->route('admin.sections')->with('success', 'Section deleted.');
    }

    public function assignSectionSupervisor(Request $request, Section $section)
    {
        $data = $request->validate([
            'supervisor_id' => 'nullable|exists:dtr_users,id',
        ]);
        $section->update(['supervisor_id' => $data['supervisor_id']]);
        return redirect()->route('admin.sections')->with('success', 'Section supervisor assigned.');
    }

    public function assignSectionOic(Request $request, Section $section)
    {
        $data = $request->validate([
            'oic_id' => 'nullable|exists:dtr_users,id',
        ]);
        $section->update(['oic_id' => $data['oic_id']]);
        return redirect()->route('admin.sections')->with('success', 'Section OIC assigned.');
    }

    public function employees(Request $request)
    {
        $employees = DtrUser::with(['officeModel', 'sectionModel'])
            ->orderBy('last_name')
            ->orderBy('first_name');
        if ($request->filled('search')) {
            $search = $request->search;
            $employees->where(function ($q) use ($search) {
                $q->where('emp_code', 'like', '%' . $search . '%')
                  ->orWhere('last_name', 'like', '%' . $search . '%')
                  ->orWhere('first_name', 'like', '%' . $search . '%')
                  ->orWhere('office', 'like', '%' . $search . '%')
                  ->orWhere('section', 'like', '%' . $search . '%');
            });
        }
        $employees = $employees->get();
        $offices = Office::with('sections')->orderBy('name')->get();
        return view('admin.employees', compact('employees', 'offices'));
    }

    public function assignEmployee(Request $request, DtrUser $employee)
    {
        $data = $request->validate([
            'office_id' => 'nullable|exists:offices,id',
            'section_id' => 'nullable|exists:sections,id',
        ]);

        $office = $data['office_id'] ? Office::find($data['office_id']) : null;
        $section = $data['section_id'] ? Section::find($data['section_id']) : null;

        $employee->update([
            'office_id' => $data['office_id'],
            'section_id' => $data['section_id'],
            'office' => $office ? $office->name : '',
            'section' => $section ? $section->name : '',
        ]);

        return redirect()->route('admin.employees')->with('success', 'Employee assigned.');
    }

    public function resetPassword(DtrUser $employee)
    {
        $user = User::where('emp_code', $employee->emp_code)->first();

        if (!$user) {
            return redirect()->route('admin.employees')
                ->with('error', 'No user account found for this employee.');
        }

        $user->update(['password' => Hash::make('password')]);

        app(UserLogService::class)->log(auth()->id(), 'update', "Password reset for {$employee->full_name}", User::class, $user->id);

        return redirect()->route('admin.employees')
            ->with('success', "Password for {$employee->full_name} reset to \"password\".");
    }

    public function settings()
    {
        $order = ['system_name', 'logo_path', 'agency_head_name', 'agency_head_position', 'agency_head_user_id', 'agency_name', 'four_day_work_week', 'am_start', 'am_start_flexi', 'am_end', 'pm_start', 'pm_end', 'pm_end_flexi', 'fdww_am_start', 'fdww_am_start_flexi', 'fdww_am_end', 'fdww_pm_start', 'fdww_pm_end', 'fdww_pm_end_flexi'];
        $settingModels = DtrSetting::whereNotIn('setting_key', ['grace_period_minutes', 'office_name'])
            ->orderByRaw('FIELD(setting_key, "' . implode('","', $order) . '")')
            ->get();
        $users = User::orderBy('name')->get(['id', 'name', 'emp_code']);
        $gDriveConfigured = app(\App\Services\GoogleDriveService::class)->isConfigured();
        return view('admin.settings', compact('settingModels', 'users', 'gDriveConfigured'));
    }

    public function updateSettings(Request $request)
    {
        $keys = DtrSetting::pluck('setting_key')->toArray();
        foreach ($keys as $key) {
            if ($key === 'logo_path') {
                continue;
            }
            if ($request->has($key)) {
                DtrSetting::where('setting_key', $key)->update([
                    'setting_value' => $request->input($key),
                ]);
            }
        }

        if ($request->hasFile('logo')) {
            $old = DtrSetting::where('setting_key', 'logo_path')->value('setting_value');
            if ($old) {
                Storage::delete('public/' . $old);
            }
            $path = $request->file('logo')->store('public/logos');
            $relativePath = str_replace('public/', '', $path);
            DtrSetting::where('setting_key', 'logo_path')->update([
                'setting_value' => $relativePath,
            ]);
        }

        app(UserLogService::class)->log(auth()->id(), 'update', 'System settings updated', DtrSetting::class, null);

        return redirect()->route('admin.settings')->with('success', 'Settings updated.');
    }

    public function holidays(Request $request)
    {
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));
        $search = $request->input('search');

        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

        $holidays = GlobalHoliday::whereBetween('target_date', ["$year-$month-01", "$year-$month-$daysInMonth"])
            ->orderBy('target_date');
        if ($search) {
            $holidays->where(function ($q) use ($search) {
                $q->where('description', 'like', '%' . $search . '%')
                  ->orWhere('type', 'like', '%' . $search . '%');
            });
        }
        $holidays = $holidays->get()
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
                $weeks[$weekIndex][] = [
                    'day' => $day,
                    'date' => $dateStr,
                    'holiday' => $holidays->get($dateStr),
                ];
                $day++;
            }
        }

        return view('admin.holidays', compact('holidays', 'month', 'year', 'daysInMonth', 'weeks', 'search'));
    }

    public function storeHoliday(Request $request)
    {
        $data = $request->validate([
            'target_date' => 'required|date|unique:global_holidays,target_date',
            'type' => 'required|in:holiday,work_suspension',
            'value' => 'required|in:whole_day,am,pm',
            'description' => 'nullable|string|max:200',
        ]);

        GlobalHoliday::create($data);
        $m = (int)date('m', strtotime($data['target_date']));
        $y = date('Y', strtotime($data['target_date']));
        Cache::forget('global_holidays.' . $y . '.' . $m);

        return redirect()->route('admin.holidays', ['month' => $m, 'year' => $y])
            ->with('success', ucwords(str_replace('_', ' ', $data['type'])) . ' set for ' . date('M d, Y', strtotime($data['target_date'])));
    }

    public function deleteHoliday(GlobalHoliday $holiday)
    {
        $month = (int)$holiday->target_date->format('m');
        $year = $holiday->target_date->format('Y');
        $holiday->delete();
        Cache::forget('global_holidays.' . $year . '.' . $month);

        return redirect()->route('admin.holidays', ['month' => $month, 'year' => $year])
            ->with('success', 'Removed.');
    }

    public function workArrangement(Request $request)
    {
        $employees = DtrUser::orderBy('last_name')->orderBy('first_name');
        if ($request->filled('search')) {
            $search = $request->search;
            $employees->where(function ($q) use ($search) {
                $q->where('emp_code', 'like', '%' . $search . '%')
                  ->orWhere('last_name', 'like', '%' . $search . '%')
                  ->orWhere('first_name', 'like', '%' . $search . '%');
            });
        }
        $employees = $employees->get();
        $globalSetting = DtrSetting::where('setting_key', 'four_day_work_week')->first();
        return view('admin.work-arrangement', compact('employees', 'globalSetting'));
    }

    public function updateGlobalWorkWeek(Request $request)
    {
        $data = $request->validate(['value' => 'required|in:0,1']);
        $setting = DtrSetting::where('setting_key', 'four_day_work_week')->first();
        if ($setting) {
            $setting->setting_value = $data['value'];
            $setting->save();
        }
        return redirect()->route('admin.work-arrangement')->with('success', 'Global work week updated.');
    }

    public function updateEmployeeWorkWeek(Request $request, DtrUser $employee)
    {
        $data = $request->validate(['default_work_week' => 'required|in:5-day,4-day,default']);
        $value = $data['default_work_week'] === 'default' ? null : $data['default_work_week'];
        $employee->update(['default_work_week' => $value]);
        return redirect()->route('admin.work-arrangement')->with('success', "{$employee->full_name} updated.");
    }

    public function logs(Request $request)
    {
        $query = UserLog::with('user');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('description', 'like', '%' . $search . '%');
        }

        if ($request->filled('action')) {
            $query->byAction($request->action);
        }

        if ($request->filled('user_id')) {
            $query->forUser($request->user_id);
        }

        if ($request->filled('entity_type')) {
            $query->byEntity($request->entity_type);
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to . ' 23:59:59');
        }

        $logs = $query->latest('created_at')->paginate(50);

        $actions = UserLog::select('action')->distinct()->orderBy('action')->pluck('action');
        $users = User::orderBy('name')->get(['id', 'name']);

        return view('admin.logs', compact('logs', 'actions', 'users'));
    }

    public function passwordResetRequests(Request $request)
    {
        $pending = PasswordResetRequest::where('status', 'pending')
            ->with('user')
            ->latest();
        if ($request->filled('search')) {
            $search = $request->search;
            $pending->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%');
            });
        }
        $pending = $pending->get();

        $resolved = PasswordResetRequest::where('status', 'reset')
            ->with('user')
            ->latest();
        if ($request->filled('search')) {
            $search = $request->search;
            $resolved->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%');
            });
        }
        $resolved = $resolved->get();

        return view('admin.password-reset-requests', compact('pending', 'resolved'));
    }

    public function users(Request $request)
    {
        $users = User::withTrashed()->orderBy('name');
        if ($request->filled('search')) {
            $search = $request->search;
            $users->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('username', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%');
            });
        }
        $users = $users->get();
        return view('admin.users', compact('users'));
    }

    public function toggleSuper(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users')
                ->with('error', 'You cannot remove super admin privileges from yourself.');
        }

        $user->update(['is_super' => !$user->is_super]);

        $action = $user->is_super ? 'granted' : 'removed';
        app(UserLogService::class)->log(auth()->id(), 'update', "Super admin {$action} for {$user->name}", User::class, $user->id);

        return redirect()->route('admin.users')
            ->with('success', "Super admin privileges {$action} for {$user->name}.");
    }

    public function toggleCoa(User $user)
    {
        $user->update(['is_coa' => !$user->is_coa]);

        $action = $user->is_coa ? 'granted' : 'removed';
        app(UserLogService::class)->log(auth()->id(), 'update', "COA privileges {$action} for {$user->name}", User::class, $user->id);

        return redirect()->route('admin.users')
            ->with('success', "COA privileges {$action} for {$user->name}.");
    }

    public function coaShares(Request $request)
    {
        $month = (int) $request->input('month', date('m'));
        $year = (int) $request->input('year', date('Y'));

        $sharedMonths = DtrMonthlyShare::with('sharedBy')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc');
        if ($request->filled('search')) {
            $search = $request->search;
            $sharedMonths->whereHas('sharedBy', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%');
            });
        }
        $sharedMonths = $sharedMonths->get();

        $isCurrentShared = DtrMonthlyShare::where('month', $month)->where('year', $year)->exists();

        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $pendingCount = DtrEditRequest::whereBetween('target_date', ["$year-$month-01", "$year-$month-$daysInMonth"])
            ->where('status', 'pending')
            ->count();

        return view('admin.coa-shares', compact('sharedMonths', 'month', 'year', 'isCurrentShared', 'pendingCount'));
    }

    public function monitoring(Request $request)
    {
        $month = (int) $request->input('month', date('m'));
        $year = (int) $request->input('year', date('Y'));

        $employees = DtrUser::where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name');
        if ($request->filled('search')) {
            $search = $request->search;
            $employees->where(function ($q) use ($search) {
                $q->where('last_name', 'like', '%' . $search . '%')
                  ->orWhere('first_name', 'like', '%' . $search . '%')
                  ->orWhere('emp_code', 'like', '%' . $search . '%');
            });
        }
        $employees = $employees->get();

        $dtrController = app(DtrController::class);
        $stats = [];
        foreach ($employees as $emp) {
            $result = $dtrController->getEmployeeMonthlyStats($emp->emp_code, $year, $month);
            if ($result) {
                $stats[] = $result;
            }
        }

        return view('admin.monitoring', compact('stats', 'month', 'year'));
    }

    public function issueMemo(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:dtr_users,id',
            'notes' => 'nullable|string|max:1000',
            'month' => 'nullable|integer|between:1,12',
            'year' => 'nullable|integer|between:2000,2100',
        ]);

        $employee = DtrUser::findOrFail($data['employee_id']);
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));
        $monthYear = sprintf('%04d-%02d', $year, $month);

        Memo::create([
            'employee_id' => $data['employee_id'],
            'issued_by' => auth()->id(),
            'notes' => $data['notes'],
            'month_year' => $monthYear,
        ]);

        app(UserLogService::class)->log(auth()->id(), 'create', "Memo issued to {$employee->full_name}", Memo::class, null);

        return redirect()->route('admin.monitoring', ['month' => $month, 'year' => $year])
            ->with('success', "Memo issued to {$employee->full_name}.");
    }

    public function resetUserPassword(User $user)
    {
        $user->update(['password' => Hash::make('password')]);

        app(UserLogService::class)->log(auth()->id(), 'update', "Password reset for {$user->name}", User::class, $user->id);

        return redirect()->route('admin.users')
            ->with('success', "Password for {$user->name} reset to \"password\".");
    }

    public function deleteUser(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users')
                ->with('error', 'You cannot delete your own account.');
        }

        $userName = $user->name;
        $user->delete();

        app(UserLogService::class)->log(auth()->id(), 'delete', "Deleted user {$userName}", User::class, $user->id);

        return redirect()->route('admin.users')
            ->with('success', "User {$userName} has been deleted.");
    }

    public function deactivateUser(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users')
                ->with('error', 'You cannot deactivate your own account.');
        }

        $user->update(['is_active' => false]);

        app(UserLogService::class)->log(auth()->id(), 'update', "Deactivated user {$user->name}", User::class, $user->id);

        return redirect()->route('admin.users')
            ->with('success', "User {$user->name} has been deactivated.");
    }

    public function activateUser(User $user)
    {
        $user->update(['is_active' => true]);

        app(UserLogService::class)->log(auth()->id(), 'update', "Activated user {$user->name}", User::class, $user->id);

        return redirect()->route('admin.users')
            ->with('success', "User {$user->name} has been activated.");
    }

    public function approvePasswordReset(PasswordResetRequest $resetRequest)
    {
        if ($resetRequest->status !== 'pending') {
            return redirect()->route('admin.password-reset-requests')
                ->with('error', 'This request has already been resolved.');
        }

        $user = $resetRequest->user;

        $user->update(['password' => Hash::make('password')]);

        $resetRequest->update(['status' => 'reset']);

        app(UserLogService::class)->log(auth()->id(), 'update', "Password reset request approved for {$user->name}", User::class, $user->id);

        return redirect()->route('admin.password-reset-requests')
            ->with('success', "Password for {$user->name} reset to \"password\".");
    }

    public function runBackup()
    {
        \Artisan::call('backup:run');
        $output = \Artisan::output();

        app(UserLogService::class)->log(auth()->id(), 'backup', 'Manual database backup triggered');

        return redirect()->route('admin.settings')
            ->with('success', 'Database backup completed. Check Google Drive.');
    }
}
