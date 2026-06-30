<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard - {{ $settings['system_name'] ?? 'e-DTR Records' }}</title>
    <link rel="stylesheet" href="{{ asset('dtr.css') }}">
    <script>if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark');</script>
    <style>
        .cal-wrap { flex:1; min-width:0; }
        .cal-month-nav { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }
        .cal-month-nav h2 { margin:0; font-size:18px; color:var(--primary); }
        .cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:2px; background:var(--gray-300); border-radius:8px; overflow:hidden; }
        .cal-grid > div { background:var(--white); padding:6px 4px; text-align:center; min-height:90px; position:relative; }
        .cal-grid .day-hdr { font-weight:600; font-size:11px; color:var(--gray-500); text-transform:uppercase; padding:6px 4px; min-height:auto; }
        .cal-grid .day-empty { background:var(--gray-50); min-height:auto; }
        .cal-grid .today { background:#f0fdf4; }
        .cal-grid .today .day-num { background:var(--primary); color:#fff; border-radius:50%; width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center; }
        .day-num { font-size:12px; font-weight:600; color:var(--gray-700); margin-bottom:2px; }
        .day-dow { font-size:9px; color:var(--gray-400); }
        .slot { font-size:10px; padding:1px 4px; border-radius:3px; margin:1px 0; line-height:1.4; }
        .slot-in { color:#166534; }
        .slot-out { color:#92400e; }
        .slot-holiday { background:#dbeafe; color:#1e40af; font-weight:600; }
        .slot-suspension { background:#fce7f3; color:#9d174d; font-weight:600; }
        .slot-wfh { background:#fef3c7; color:#92400e; font-weight:600; }
        .slot-absent { background:#fee2e2; color:#991b1b; font-weight:600; }
        .slot-ls { background:#e0e7ff; color:#3730a3; font-size:9px; }
        .slot-so { background:#e0f2fe; color:#075985; font-weight:600; }
        .slot-to { background:#d1fae5; color:#065f46; font-weight:600; }
        .slot-hd { background:#f3e8ff; color:#6b21a8; font-weight:600; }
        .slot-leave { background:#f0fdf4; color:#166534; font-weight:600; }
        .hours-badge { display:inline-block; font-size:9px; background:var(--gray-100); color:var(--gray-600); padding:1px 5px; border-radius:3px; margin-top:2px; }
        .weekend { background:var(--gray-50) !important; }
        .weekend .day-num { color:var(--gray-400); }
        .stat-card { background:var(--white); border:1px solid var(--gray-200); border-radius:8px; padding:14px 16px; text-align:center; flex:1; min-width:100px; }
        .stat-card .stat-val { font-size:24px; font-weight:700; color:var(--primary); }
        .stat-card .stat-lbl { font-size:11px; color:var(--gray-500); margin-top:2px; }
        .stat-row { display:flex; gap:12px; margin-bottom:16px; flex-wrap:wrap; }
        .legend { display:flex; gap:12px; flex-wrap:wrap; margin-top:12px; font-size:11px; }
        .legend-item { display:flex; align-items:center; gap:4px; }
        .legend-swatch { width:12px; height:12px; border-radius:3px; }
        .emp-info { margin-bottom:16px; }
        .emp-info h1 { font-size:20px; color:var(--primary); margin:0; }
        .emp-info p { font-size:13px; color:var(--gray-600); margin:2px 0 0; }
        .holiday-desc { font-size:9px; color:var(--gray-500); line-height:1.2; }
    </style>
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
                <a href="{{ route('dtr.dashboard') }}" class="active">
                    <span>&#128197;</span> <span>Dashboard</span>
                </a>
                <a href="{{ route('dtr.index') }}">
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
                    <h1 style="font-size:18px; color:var(--primary); margin:0;">Dashboard</h1>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    @if ($employee)<a href="{{ route('dtr.show', ['emp' => $employee->emp_code, 'month' => $month, 'year' => $year]) }}" class="btn btn-primary btn-sm">View Full DTR</a>@endif
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
                    <form method="POST" action="{{ route('logout') }}" class="logout-corner" style="margin:0;">
                        @csrf
                        <button class="btn btn-outline btn-sm">Logout</button>
                    </form>
                </div>
            </div>

            @if ($employee)
                <div class="emp-info">
                    <h1>{{ $employee->full_name }}</h1>
                    <p>{{ $employee->position ?: 'Employee' }} &mdash; {{ $employee->officeModel ? $employee->officeModel->name : ($employee->office ?: 'N/A') }}</p>
                </div>

                <div class="stat-row">
                    <div class="stat-card">
                        <div class="stat-val">{{ $presentDays }}</div>
                        <div class="stat-lbl">Days Present</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-val">{{ $totalHoursFormatted }}</div>
                        <div class="stat-lbl">Total Hours</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-val">{{ $daysInMonth }}</div>
                        <div class="stat-lbl">Total Days</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-val" style="color:var(--gray-700);">{{ $empDefaultWW === '4-day' ? '4-Day' : '5-Day' }}</div>
                        <div class="stat-lbl">Work Week</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-val" style="color:#b91c1c;">{{ $lateDaysCount }}</div>
                        <div class="stat-lbl">Days Late</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-val" style="color:#92400e;">{{ $undertimeDaysCount }}</div>
                        <div class="stat-lbl">Days w/ Undertime</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-val" style="color:#991b1b;">{{ $absentDaysCount }}</div>
                        <div class="stat-lbl">Days Absent</div>
                    </div>
                </div>

                <div class="card" style="padding:16px;">
                    <div class="cal-month-nav">
                        <a href="{{ route('dtr.dashboard', ['month' => $month > 1 ? $month - 1 : 12, 'year' => $month > 1 ? $year : $year - 1]) }}" class="btn btn-outline btn-sm">&larr; {{ date('M', mktime(0, 0, 0, $month > 1 ? $month - 1 : 12, 1)) }}</a>
                        <h2>{{ $monthName }} {{ $year }}</h2>
                        <a href="{{ route('dtr.dashboard', ['month' => $month < 12 ? $month + 1 : 1, 'year' => $month < 12 ? $year : $year + 1]) }}" class="btn btn-outline btn-sm">{{ date('M', mktime(0, 0, 0, $month < 12 ? $month + 1 : 1, 1)) }} &rarr;</a>
                    </div>

                    <div class="cal-grid">
                        <div class="day-hdr">Mon</div>
                        <div class="day-hdr">Tue</div>
                        <div class="day-hdr">Wed</div>
                        <div class="day-hdr">Thu</div>
                        <div class="day-hdr">Fri</div>
                        <div class="day-hdr">Sat</div>
                        <div class="day-hdr">Sun</div>
                        @foreach ($weeks as $week)
                            @foreach ($week as $cell)
                                @if ($cell === null)
                                    <div class="day-empty"></div>
                                @else
                                    @php
                                        $d = $cell['dtr'];
                                        $isToday = $cell['date'] === date('Y-m-d');
                                        $isWeekend = in_array(date('N', strtotime($cell['date'])), [6, 7]);
                                        $isHoliday = $cell['holiday'] && $cell['holiday']->type === 'holiday';
                                        $isWs = $cell['holiday'] && $cell['holiday']->type === 'work_suspension';
                                        $remarks = $d['remarks'] ?? '';
                                        $hasPunch = !empty($d['has_punch']);
                                        $ai = $d['am_in'] ?? '';
                                        $ao = $d['am_out'] ?? '';
                                        $pi = $d['pm_in'] ?? '';
                                        $po = $d['pm_out'] ?? '';
                                        $th = $d['total_hours'] ?? '';
                                        $isWholeDayLeave = $ai === 'ON LEAVE' && $pi === 'ON LEAVE';
                                        $typeClass = '';
                                        $typeLabel = '';
                                        if ($isHoliday) { $typeClass = 'slot-holiday'; $typeLabel = 'Holiday'; }
                                        elseif ($isWs) { $typeClass = 'slot-suspension'; $typeLabel = 'Suspension'; }
                                        elseif (strpos($remarks, 'WFH') !== false) { $typeClass = 'slot-wfh'; $typeLabel = 'WFH'; }
                                        elseif (strpos($remarks, 'Absent') !== false) { $typeClass = 'slot-absent'; $typeLabel = 'Absent'; }
                                        elseif ($isWholeDayLeave) { $typeClass = 'slot-leave'; $typeLabel = $remarks ?: 'On Leave'; }
                                        elseif (strpos($remarks, 'Halfday') !== false) { $typeClass = 'slot-hd'; $typeLabel = $remarks; }
                                        elseif (strpos($remarks, 'LS:') !== false) { $typeClass = 'slot-ls'; $typeLabel = 'LS'; }
                                        elseif (!empty($d['so_number'])) { $typeClass = 'slot-so'; $typeLabel = 'SO'; }
                                        elseif (!empty($d['to_number'])) { $typeClass = 'slot-to'; $typeLabel = 'TO'; }
                                        $todayClass = $isToday ? ' today' : '';
                                        $weekendClass = $isWeekend ? ' weekend' : '';
                                    @endphp
                                    <div class="{{ trim($todayClass . $weekendClass) }}">
                                        <div class="day-num">{{ $cell['day'] }}
                                            <span class="day-dow">{{ date('D', strtotime($cell['date'])) }}</span>
                                        </div>
                                        @if ($typeClass)
                                            <div class="slot {{ $typeClass }}">{{ $typeLabel }}</div>
                                            @if ($cell['holiday'] && $cell['holiday']->description)
                                                <div class="holiday-desc">{{ $cell['holiday']->description }}</div>
                                            @endif
                                        @elseif ($hasPunch)
                                            @if ($ai)
                                                <div class="slot slot-in">&#10548; {{ $ai }}</div>
                                            @endif
                                            @if ($ao)
                                                <div class="slot slot-out">&#10546; {{ $ao }}</div>
                                            @endif
                                            @if ($pi)
                                                <div class="slot slot-in">&#10548; {{ $pi }}</div>
                                            @endif
                                            @if ($po)
                                                <div class="slot slot-out">&#10546; {{ $po }}</div>
                                            @endif
                                            @if ($th && $th !== '--:--')
                                                <div class="hours-badge">{{ $th }}</div>
                                            @endif
                                            @if ($remarks && !$typeClass && strpos($remarks, 'Late:') !== false)
                                                <div class="holiday-desc" style="color:#b91c1c;">{{ $remarks }}</div>
                                            @endif
                                        @else
                                            <div style="font-size:9px;color:var(--gray-400);margin-top:8px;">&mdash;</div>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        @endforeach
                    </div>

                    <div class="legend">
                        <span class="legend-item"><span class="legend-swatch" style="background:#dbeafe;"></span> Holiday</span>
                        <span class="legend-item"><span class="legend-swatch" style="background:#fce7f3;"></span> Work Suspension</span>
                        <span class="legend-item"><span class="legend-swatch" style="background:#fef3c7;"></span> WFH</span>
                        <span class="legend-item"><span class="legend-swatch" style="background:#fee2e2;"></span> Absent</span>
                        <span class="legend-item"><span class="legend-swatch" style="background:#f0fdf4;border:2px solid #166534;"></span> On Leave</span>
                        <span class="legend-item"><span class="legend-swatch" style="background:#e0e7ff;"></span> Locator Slip</span>
                        <span class="legend-item"><span class="legend-swatch" style="background:#e0f2fe;"></span> Special Order</span>
                        <span class="legend-item"><span class="legend-swatch" style="background:#d1fae5;"></span> Travel Order</span>
                        <span class="legend-item"><span class="legend-swatch" style="background:#f3e8ff;"></span> Halfday</span>
                        <span class="legend-item"><span class="legend-swatch" style="background:#f0fdf4;border:2px solid var(--primary);"></span> Today</span>
                    </div>
                </div>
            @else
                <div class="card empty-state">
                    <p style="color:var(--gray-500); font-size:14px; text-align:center; padding:60px 20px; margin:0;">
                        No employee record found for your account. Please contact your administrator.
                    </p>
                </div>
            @endif
        </div>
    </div>
    <script>
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
    </script>
</body>
</html>