<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $settings['system_name'] ?? 'e-DTR Records' }}</title>
    <link rel="stylesheet" href="{{ asset('dtr.css') }}">
    <script>if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark');</script>
</head>
<body>
    @php $currentUser = auth()->user(); @endphp

    <div class="layout-sidebar">
        <div class="sidebar no-print">
            <div class="sidebar-header">
                @if (!empty($settings['logo_path']))
                    <img src="{{ asset('storage/' . $settings['logo_path']) }}" alt="Logo" style="height:32px;margin-bottom:4px;">
                @endif
                <h2>{{ $settings['system_name'] ?? 'e-DTR Records' }}</h2>
                <p>{{ $currentUser->name }} @if($currentUser->is_coa)<span style="display:inline-block;background:#e74c3c;color:#fff;font-size:10px;padding:1px 6px;border-radius:8px;vertical-align:middle;margin-left:4px;">COA</span>@endif</p>
            </div>
            <nav class="sidebar-nav">
                <a href="{{ route('dtr.dashboard') }}" class="{{ request()->routeIs('dtr.dashboard') ? 'active' : '' }}">
                    <span>&#128197;</span> <span>Dashboard</span>
                </a>
                <a href="{{ route('dtr.index') }}" class="{{ request()->routeIs('dtr.index') ? 'active' : '' }}">
                    <span>&#128196;</span> <span>e-DTR Records</span>
                </a>
                @if ($currentUser->is_super || $isSupervisor)
                    <a href="{{ route('supervisor.pending') }}">
                        <span>&#128276;</span> <span>Supervisor Panel</span>
                    </a>
                @endif
                @if ($currentUser->is_super)
                    <a href="{{ route('admin.dashboard') }}">
                        <span>&#9881;</span> <span>Admin</span>
                    </a>
                @endif
                <a href="{{ route('profile') }}">
                    <span>&#128100;</span> <span>My Profile</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <button onclick="toggleTheme()" class="btn btn-sm" style="background:rgba(255,255,255,0.1); color:#fff; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; font-size:12px; width:100%; margin-bottom:8px;" id="themeToggle">Dark Mode</button>

            </div>
        </div>

        <div class="main-content">
            <div class="navbar no-print" style="margin-bottom:20px;">
                <div class="navbar-left">
                    <h1 style="font-size:18px; color:var(--primary); margin:0;">e-DTR Records</h1>
                </div>
                @if ($dtrData)
                    <button onclick="window.print()" class="btn btn-primary btn-sm">Print / Save PDF</button>
                    @if ($currentUser->is_super && $month && $year)
                        <a href="{{ route('dtr.print-all', ['month' => $month, 'year' => $year]) }}" class="btn btn-accent btn-sm">Print All DTRs</a>
                    @endif
                    @if ($currentUser->is_coa && $month && $year)
                        <a href="{{ route('dtr.print-all', ['month' => $month, 'year' => $year]) }}" class="btn btn-primary btn-sm">View All DTRs</a>
                    @endif
                @endif
                @php $unread = $currentUser->unreadNotifications; @endphp
                <div class="notif-pos">
                    <button class="notif-btn" onclick="toggleNotif()">&#128276;
                        @if ($unread->count() > 0)
                            <span class="notif-badge">{{ $unread->count() }}</span>
                        @endif
                    </button>
                    <div id="notifDropdown" class="notif-dropdown">
                        <div class="notif-header">Notifications</div>
                        @forelse ($unread as $notif)
                            @if ($notif->data['type'] === 'edit_request_submitted')
                                <a href="{{ route('supervisor.pending') }}" class="notif-item" data-notif-id="{{ $notif->id }}">
                            @else
                                @php
                                    $d = $notif->data['target_date'] ?? null;
                                    $m = $d ? date('n', strtotime($d)) : date('n');
                                    $y = $d ? date('Y', strtotime($d)) : date('Y');
                                    $ec = $notif->data['emp_code'] ?? '';
                                @endphp
                                <a href="{{ url('/dtr/show?emp=' . $ec . '&month=' . $m . '&year=' . $y) }}" class="notif-item" data-notif-id="{{ $notif->id }}">
                            @endif
                                <strong>{{ $notif->data['message'] }}</strong>
                                <div class="notif-time">{{ $notif->created_at->diffForHumans() }}</div>
                            </a>
                        @empty
                            <div style="padding:24px; text-align:center; color:var(--gray-500); font-size:13px;">No new notifications</div>
                        @endforelse
                        @if ($unread->count() > 0)
                            <div class="notif-footer">
                                <form method="POST" action="{{ route('notifications.mark-read') }}">
                                    @csrf
                                    <button type="submit" style="background:none; border:none; color:var(--accent); font-size:12px; font-weight:600; cursor:pointer;">Mark all as read</button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="logout-corner">
                    @csrf
                    <button class="btn btn-outline btn-sm">Logout</button>
                </form>
            </div>

            @if (session('success'))
                <div class="alert alert-success no-print" style="max-width:1000px;">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-error no-print" style="max-width:1000px;">{{ session('error') }}</div>
            @endif

            @if (session('coa_not_shared'))
                <div class="alert alert-error no-print" style="max-width:1000px;">{{ session('coa_not_shared') }}</div>
            @endif

            <div class="dtr-layout">
                <div class="dtr-sidebar-form no-print">
                    <div class="card">
                        <h2>{{ $currentUser->is_super || $isSupervisor ? 'Select Employee' : 'My Daily Time Record' }}</h2>
                        <form method="get" action="{{ route('dtr.index') }}" class="dtr-form">
                            @if ($canViewAll || $isSupervisor)
                                <div class="form-group">
                                    <label for="emp">Employee</label>
                                    <select name="emp" id="emp" required class="form-control">
                                        <option value="">-- Select Employee --</option>
                                        @foreach ($employees as $emp)
                                            <option value="{{ $emp->emp_code }}" {{ $emp->emp_code == request('emp') ? 'selected' : '' }}>
                                                {{ $emp->full_name }} ({{ $emp->emp_code }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @else
                                @foreach ($employees as $emp)
                                    <input type="hidden" name="emp" value="{{ $emp->emp_code }}">
                                    <p style="font-size:15px; margin-bottom:18px; color:var(--gray-800);">
                                        <strong>{{ $emp->full_name }}</strong> ({{ $emp->emp_code }})
                                    </p>
                                @endforeach
                            @endif
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="month">Month</label>
                                    <select name="month" id="month" class="form-control">
                                        @foreach (range(1, 12) as $m)
                                            <option value="{{ $m }}" {{ $m == ($month ?? date('m')) ? 'selected' : '' }}>
                                                {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="year">Year</label>
                                    <select name="year" id="year" class="form-control">
                                        @for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++)
                                            <option value="{{ $y }}" {{ $y == ($year ?? date('Y')) ? 'selected' : '' }}>{{ $y }}</option>
                                        @endfor
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Generate DTR</button>
                        </form>
                    </div>
                </div>

                <div class="dtr-main-content">
                    @if ($employee && $month && $year)
                        <div class="dtr-page">
                            <div class="dtr-cols">
                                <div class="dtr-half">
                                    @include('dtr._content')
                                </div>
                                <div class="dtr-half">
                                    @include('dtr._content')
                                </div>
                            </div>
                        </div>

                        @if ($approvedRequests->isNotEmpty())
                            <div class="no-print" style="max-width:1000px; margin-bottom:15px;">
                                <div class="card" style="padding:15px 20px;">
                                    <h3 style="font-size:14px; margin-bottom:10px; color:var(--accent); border:none; margin:0 0 10px; padding:0;">Approved Edit Requests ({{ $approvedRequests->count() }})</h3>
                                    <div class="table-wrap">
                                        <table style="font-size:12px;">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Type</th>
                                                    <th>Details</th>
                                                    <th>Reason</th>
                                                    @if ($isOwnDtr)
                                                        <th style="width:50px;"></th>
                                                    @endif
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($approvedRequests as $req)
                                                    @php
                                                        $typeLabels = [
                                                            'time_correction' => 'Time Correction',
                                                            'absent' => 'Whole Day Absent',
                                                            'halfday_am' => 'Halfday (AM)',
                                                            'halfday_pm' => 'Halfday (PM)',
                                                            'holiday' => 'Holiday',
                                                            'on_leave' => 'On Leave',
                                                            'wfh' => 'WFH',
                                                            'special_order' => 'Special Order',
                                                            'travel_order' => 'Travel Order',
                                                            'official_business' => 'Official Business',
                                                            'work_suspension' => 'Work Suspension',
                                                            'locator_slip' => 'Locator Slip',
                                                            'delete_request' => 'Deletion Request',
                                                        ];
                                                        $details = '';
                                                        if ($req->type === 'time_correction') {
                                                            $details = $req->field . ': ' . ($req->old_value ?: '&mdash;') . ' &rarr; ' . $req->new_value;
                                                        } elseif (in_array($req->type, ['halfday_am', 'halfday_pm']) && $req->new_value) {
                                                            $label = $req->type === 'halfday_am' ? 'AM out' : 'PM in';
                                                            $details = $label . ': ' . $req->new_value;
                                                        } elseif ($req->type === 'on_leave' && $req->new_value) {
                                                            $details = 'On Leave (' . $req->new_value . ' hrs)';
                                                        } elseif ($req->type === 'locator_slip') {
                                                            $lsLabel = $req->field === 'personal' ? 'Personal' : 'Official';
                                                            $timeLeft = $req->ls_time_left ? date('h:i A', strtotime($req->ls_time_left)) : '';
                                                            $timeReturned = $req->ls_no_return ? 'No Return' : ($req->ls_time_returned ? date('h:i A', strtotime($req->ls_time_returned)) : '');
                                                            $parts = array_filter([$req->new_value, $timeLeft ? "Left: $timeLeft" : null, $timeReturned ? "Ret: $timeReturned" : null]);
                                                            $details = $typeLabels[$req->type] . ' (' . $lsLabel . ')' . ($parts ? ' &mdash; ' . implode(' | ', $parts) : '');
                                                        } elseif (in_array($req->type, ['work_suspension', 'wfh', 'special_order', 'travel_order', 'official_business']) && $req->new_value && $req->new_value !== 'whole_day') {
                                                            $details = $typeLabels[$req->type] ?? $req->type;
                                                            if ($req->new_value === 'am') $details .= ' (AM)';
                                                            elseif ($req->new_value === 'pm') $details .= ' (PM)';
                                                        } else {
                                                            $details = $typeLabels[$req->type] ?? $req->type;
                                                        }
                                                    @endphp
                                                    <tr>
                                                        <td>{{ $req->target_date->format('M d, Y') }}</td>
                                                        <td><strong>{{ $typeLabels[$req->type] ?? $req->type }}</strong></td>
                                                        <td>{!! $details !!}</td>
                                                        <td style="max-width:200px;">{{ $req->reason }}</td>
                                                        @if ($isOwnDtr)
                                                            <td>
                                                                <button type="button" onclick="openDeletionModal({{ $req->id }}, '{{ $req->target_date->format('M d, Y') }}')" style="background:none; border:none; color:var(--danger); cursor:pointer; font-size:14px; padding:2px 6px;" title="Request Deletion">&#10005;</button>
                                                            </td>
                                                        @endif
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="card empty-state">
                            <p style="color:var(--gray-500); font-size:14px; text-align:center; padding:40px 20px; margin:0;">
                                Select an employee, month, and year then click <strong>Generate DTR</strong> to view the Daily Time Record.
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if (isset($isOwnDtr) && $isOwnDtr)
    <div id="editRequestModal" class="modal-overlay">
        <div class="modal-box">
            <h2>Request DTR Edit</h2>
            <p class="modal-sub" id="modalDayLabel">Day </p>
            <form method="POST" action="{{ route('dtr.edit-request.store') }}">
                @csrf
                <input type="hidden" name="target_date" id="modal_target_date">

                <div class="form-group">
                    <label for="modal_type">Type</label>
                    <select name="type" id="modal_type" required class="form-control" onchange="toggleEditFields()">
                        <option value="time_correction">Time Correction</option>
                        <option value="absent">Absent</option>
                        <option value="on_leave">On Leave</option>
                        <option value="wfh">Work From Home (WFH)</option>
                        <option value="special_order">Special Order</option>
                        <option value="travel_order">Travel Order</option>
                        <option value="official_business">Official Business</option>
                        <option value="locator_slip">Locator Slip</option>
                    </select>
                </div>

                <div id="timeCorrectionFields">
                    <div class="form-group">
                        <label for="modal_field">Field to correct</label>
                        <select name="field" id="modal_field" class="form-control">
                            <option value="am_in">AM Arrival</option>
                            <option value="am_out">AM Departure</option>
                            <option value="pm_in">PM Arrival</option>
                            <option value="pm_out">PM Departure</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="modal_old_value">Current value</label>
                        <input type="text" name="old_value" id="modal_old_value" readonly class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="modal_new_value">Correct value</label>
                        <input type="time" name="new_value" id="modal_new_value" class="form-control" style="width:180px;">
                    </div>
                </div>

                <div id="soFields" style="display:none;">
                    <div class="form-group">
                        <label>Special Order Type</label>
                        <div style="display:flex;gap:16px;flex-wrap:wrap;padding:8px 0;">
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="so_type" value="whole_day" checked> Whole Day
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="so_type" value="am"> AM
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="so_type" value="pm"> PM
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="modal_so_number">Special Order No.</label>
                        <input type="text" name="so_number" id="modal_so_number" placeholder="e.g. SO-2024-001" class="form-control">
                    </div>
                </div>

                <div id="toFields" style="display:none;">
                    <div class="form-group">
                        <label>Travel Order Type</label>
                        <div style="display:flex;gap:16px;flex-wrap:wrap;padding:8px 0;">
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="to_type" value="whole_day" checked> Whole Day
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="to_type" value="am"> AM
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="to_type" value="pm"> PM
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="modal_to_number">Travel Order No.</label>
                        <input type="text" name="to_number" id="modal_to_number" placeholder="e.g. TO-2024-001" class="form-control">
                    </div>
                </div>

                <div id="obFields" style="display:none;">
                    <div class="form-group">
                        <label>Official Business Type</label>
                        <div style="display:flex;gap:16px;flex-wrap:wrap;padding:8px 0;">
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="ob_type" value="whole_day" checked> Whole Day
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="ob_type" value="am"> AM
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="ob_type" value="pm"> PM
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="modal_ob_number">Official Business No.</label>
                        <input type="text" name="ob_number" id="modal_ob_number" placeholder="e.g. OB-2024-001" class="form-control">
                    </div>
                </div>

                <div id="dateRangeFields" style="display:none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="modal_from_date">From Date</label>
                            <input type="date" name="from_date" id="modal_from_date" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="modal_to_date">To Date</label>
                            <input type="date" name="to_date" id="modal_to_date" class="form-control">
                        </div>
                    </div>
                </div>

                <div id="absentFields" style="display:none;">
                    <div class="form-group">
                        <label>Absent Type</label>
                        <div style="display:flex;gap:16px;flex-wrap:wrap;padding:8px 0;">
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="absent_type" value="whole_day" onchange="setAbsentType('absent')" checked> Whole Day
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="absent_type" value="am" onchange="setAbsentType('halfday_am')"> AM
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="absent_type" value="pm" onchange="setAbsentType('halfday_pm')"> PM
                            </label>
                        </div>
                    </div>
                </div>

                <div id="wfhFields" style="display:none;">
                    <div class="form-group">
                        <label>WFH Type</label>
                        <div style="display:flex;gap:16px;flex-wrap:wrap;padding:8px 0;">
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="wfh_type" value="whole_day" checked> Whole Day
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="wfh_type" value="am"> AM
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="wfh_type" value="pm"> PM
                            </label>
                        </div>
                    </div>
                </div>

                <div id="onLeaveFields" style="display:none;">
                    <div class="form-group">
                        <label>Leave Type</label>
                        <div style="display:flex;gap:16px;flex-wrap:wrap;padding:8px 0;">
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="leave_type" value="whole_day" checked onchange="updateLeaveHours()"> Whole Day
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="leave_type" value="am" onchange="updateLeaveHours()"> Halfday (AM)
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="leave_type" value="pm" onchange="updateLeaveHours()"> Halfday (PM)
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="modal_leave_hours">Leave Hours</label>
                        <input type="number" name="leave_hours" id="modal_leave_hours" min="0.5" max="24" step="0.5" value="8" class="form-control" style="width:140px;">
                    </div>
                </div>

                <div id="locatorSlipFields" style="display:none;">
                    <div class="form-group">
                        <label>Locator Slip Type</label>
                        <div style="display:flex;gap:16px;flex-wrap:wrap;padding:8px 0;">
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="ls_type" value="official" checked> Official
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="ls_type" value="personal"> Personal
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="modal_ls_whereabouts">Whereabouts</label>
                        <input type="text" name="ls_whereabouts" id="modal_ls_whereabouts" placeholder="e.g. Field visit, meeting" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="modal_ls_time_left">Time Left</label>
                        <input type="time" name="ls_time_left" id="modal_ls_time_left" class="form-control" style="width:180px;">
                    </div>
                    <div id="lsReturnFields">
                        <div class="form-group">
                            <label for="modal_ls_time_returned">Actual Time Returned</label>
                            <input type="time" name="ls_time_returned" id="modal_ls_time_returned" class="form-control" style="width:180px;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Return Status</label>
                        <div style="display:flex;gap:16px;flex-wrap:wrap;padding:8px 0;">
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="ls_no_return" value="0" checked onchange="toggleNoReturn()"> With Return
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;font-size:14px;">
                                <input type="radio" name="ls_no_return" value="1" onchange="toggleNoReturn()"> No Return
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="modal_reason" id="modalReasonLabel">Reason</label>
                    <textarea name="reason" id="modal_reason" required rows="3" placeholder="Explain why" class="form-control"></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeEditRequest()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <div id="deletionRequestModal" class="modal-overlay">
        <div class="modal-box">
            <h2>Request Deletion of Approved Edit</h2>
            <p class="modal-sub" id="deletionDayLabel">Request deletion for </p>
            <form method="POST" action="" id="deletionForm">
                @csrf
                <input type="hidden" name="edit_request_id" id="deletion_request_id">
                <div class="form-group">
                    <label for="deletion_reason">Reason for deletion</label>
                    <textarea name="reason" id="deletion_reason" required rows="3" placeholder="Explain why this approved edit should be deleted" class="form-control"></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeDeletionModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">Submit Deletion Request</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditRequest(day, amIn, amOut, pmIn, pmOut) {
            var dateStr = ('{{ $year }}' + '-' + String('{{ $month }}').padStart(2, '0') + '-' + String(day).padStart(2, '0'));
            document.getElementById('modal_target_date').value = dateStr;
            document.getElementById('modalDayLabel').textContent = 'Day ' + day + ' (' + dateStr + ')';
            document.getElementById('modal_from_date').value = dateStr;
            document.getElementById('modal_to_date').value = dateStr;
            document.getElementById('modal_type').value = 'time_correction';

            var fieldSelect = document.getElementById('modal_field');
            var oldValueInput = document.getElementById('modal_old_value');
            var newValueInput = document.getElementById('modal_new_value');

            function updateOldValue() {
                var field = fieldSelect.value;
                var values = { am_in: amIn, am_out: amOut, pm_in: pmIn, pm_out: pmOut };
                oldValueInput.value = values[field] || '';
            }

            fieldSelect.onchange = updateOldValue;
            updateOldValue();
            toggleEditFields();
            document.getElementById('editRequestModal').classList.add('active');
            newValueInput.value = '';
            document.getElementById('modal_so_number').value = '';
            document.getElementById('modal_to_number').value = '';
            document.getElementById('modal_reason').value = '';
            document.querySelector('input[name="absent_type"][value="whole_day"]').checked = true;
            document.querySelector('input[name="wfh_type"][value="whole_day"]').checked = true;
            document.querySelector('input[name="so_type"][value="whole_day"]').checked = true;
            document.querySelector('input[name="to_type"][value="whole_day"]').checked = true;
            document.querySelector('input[name="ls_type"][value="official"]').checked = true;
            document.getElementById('modal_ls_whereabouts').value = '';
            document.getElementById('modal_ls_time_left').value = '';
            document.getElementById('modal_ls_time_returned').value = '';
            document.querySelector('input[name="ls_no_return"][value="0"]').checked = true;
            document.getElementById('lsReturnFields').style.display = 'block';
            document.querySelector('input[name="leave_type"][value="whole_day"]').checked = true;
            document.getElementById('modal_leave_hours').value = '8';
            updateLeaveHours();
        }

        function updateLeaveHours() {
            var type = document.querySelector('input[name="leave_type"]:checked');
            if (type) {
                document.getElementById('modal_leave_hours').value = type.value === 'whole_day' ? '8' : '4';
            }
        }

        function toggleNoReturn() {
            var noReturn = document.querySelector('input[name="ls_no_return"]:checked');
            document.getElementById('lsReturnFields').style.display = noReturn && noReturn.value === '1' ? 'none' : 'block';
        }

        function closeEditRequest() {
            document.getElementById('editRequestModal').classList.remove('active');
        }

        function toggleEditFields() {
            var type = document.getElementById('modal_type').value;
            var tcFields = document.getElementById('timeCorrectionFields');
            var soFields = document.getElementById('soFields');
            var toFields = document.getElementById('toFields');
            var obFields = document.getElementById('obFields');
            var absentFields = document.getElementById('absentFields');
            var wfhFields = document.getElementById('wfhFields');
            var onLeaveFields = document.getElementById('onLeaveFields');
            var locatorSlipFields = document.getElementById('locatorSlipFields');
            var dateRangeFields = document.getElementById('dateRangeFields');
            var modalDayLabel = document.getElementById('modalDayLabel');
            var fieldSelect = document.getElementById('modal_field');
            var newValueInput = document.getElementById('modal_new_value');
            var reasonLabel = document.getElementById('modalReasonLabel');
            var reasonInput = document.getElementById('modal_reason');

            tcFields.style.display = 'none';
            soFields.style.display = 'none';
            toFields.style.display = 'none';
            obFields.style.display = 'none';
            absentFields.style.display = 'none';
            wfhFields.style.display = 'none';
            onLeaveFields.style.display = 'none';
            locatorSlipFields.style.display = 'none';
            dateRangeFields.style.display = 'none';
            modalDayLabel.style.display = 'block';
            reasonLabel.textContent = 'Reason';
            reasonInput.placeholder = 'Explain why';
            fieldSelect.required = false;
            newValueInput.required = false;
            newValueInput.disabled = false;

            if (type === 'time_correction') {
                tcFields.style.display = 'block';
                fieldSelect.required = true;
                newValueInput.required = true;
            } else if (type === 'absent') {
                absentFields.style.display = 'block';
            } else if (type === 'wfh') {
                wfhFields.style.display = 'block';
            } else if (type === 'special_order') {
                soFields.style.display = 'block';
                dateRangeFields.style.display = 'block';
                modalDayLabel.style.display = 'none';
                reasonLabel.textContent = 'Title of Activity';
                reasonInput.placeholder = 'Enter the title of activity';
                document.getElementById('modal_so_number').required = true;
            } else if (type === 'travel_order') {
                toFields.style.display = 'block';
                dateRangeFields.style.display = 'block';
                modalDayLabel.style.display = 'none';
                reasonLabel.textContent = 'Title of Activity';
                reasonInput.placeholder = 'Enter the title of activity';
                document.getElementById('modal_to_number').required = true;
            } else if (type === 'official_business') {
                obFields.style.display = 'block';
                dateRangeFields.style.display = 'block';
                modalDayLabel.style.display = 'none';
                reasonLabel.textContent = 'Title of Activity';
                reasonInput.placeholder = 'Enter the title of activity';
            } else if (type === 'on_leave') {
                onLeaveFields.style.display = 'block';
                reasonLabel.textContent = 'Type of Leave';
                reasonInput.placeholder = 'Enter leave type';
            } else if (type === 'locator_slip') {
                locatorSlipFields.style.display = 'block';
                reasonLabel.textContent = 'Purpose';
                reasonInput.placeholder = 'Enter the purpose';
                document.getElementById('modal_ls_whereabouts').required = true;
            }
        }

        function setAbsentType(subType) {
            document.getElementById('modal_type').value = subType;
        }

        document.getElementById('editRequestModal').addEventListener('click', function(e) {
            if (e.target === this) closeEditRequest();
        });

        function openDeletionModal(id, dateLabel) {
            document.getElementById('deletion_request_id').value = id;
            document.getElementById('deletionDayLabel').textContent = 'Request deletion for ' + dateLabel;
            var form = document.getElementById('deletionForm');
            form.action = '{{ url("dtr/edit-request") }}/' + id + '/request-deletion';
            document.getElementById('deletionRequestModal').classList.add('active');
        }

        function closeDeletionModal() {
            document.getElementById('deletionRequestModal').classList.remove('active');
            document.getElementById('deletion_reason').value = '';
        }

        document.getElementById('deletionRequestModal').addEventListener('click', function(e) {
            if (e.target === this) closeDeletionModal();
        });
    </script>
    @endif

    <script>
        function toggleNotif() {
            var d = document.getElementById('notifDropdown');
            d.classList.toggle('active');
        }
        document.addEventListener('click', function(e) {
            var d = document.getElementById('notifDropdown');
            if (!e.target.closest('#notifDropdown') && !e.target.closest('.notif-btn')) {
                d.classList.remove('active');
            }
        });
        document.getElementById('notifDropdown') && document.getElementById('notifDropdown').addEventListener('click', function(e) {
            var item = e.target.closest('.notif-item');
            if (!item) return;
            var notifId = item.getAttribute('data-notif-id');
            if (notifId) {
                e.preventDefault();
                var url = item.getAttribute('href');
                fetch('/notifications/' + notifId + '/mark-single-read', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    }
                }).then(function() {
                    var badge = document.querySelector('.notif-badge');
                    if (badge) {
                        var count = parseInt(badge.textContent);
                        count--;
                        if (count <= 0) {
                            badge.remove();
                        } else {
                            badge.textContent = count;
                        }
                    }
                    window.location.href = url;
                }).catch(function() {
                    window.location.href = url;
                });
            }
        });
        function toggleWorkWeek(val) {
            fetch('{{ route("dtr.toggle-work-week") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ value: val })
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (data.success) location.reload();
            });
        }
        function toggleDayWorkWeek(dateStr, newType) {
            fetch('{{ route("dtr.toggle-day-work-week") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ target_date: dateStr, work_week_type: newType })
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (data.success) location.reload();
            });
        }
        function toggleTheme() {
            var html = document.documentElement;
            var isDark = html.getAttribute('data-theme') === 'dark';
            if (isDark) {
                html.removeAttribute('data-theme');
                localStorage.setItem('theme', 'light');
                document.getElementById('themeToggle').textContent = 'Dark Mode';
            } else {
                html.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
                document.getElementById('themeToggle').textContent = 'Light Mode';
            }
        }
        (function() {
            var btn = document.getElementById('themeToggle');
            if (btn && localStorage.getItem('theme') === 'dark') btn.textContent = 'Light Mode';
        })();
    </script>
</body>
</html>
