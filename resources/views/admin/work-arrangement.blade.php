<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Arrangement - {{ $settings['system_name'] ?? 'e-DTR System' }}</title>
    <link rel="stylesheet" href="{{ asset('dtr.css') }}">
    <script>if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark');</script>
</head>
<body>
    <div class="layout-sidebar">
        <div class="sidebar">
            <div class="sidebar-header">
                @if (!empty($settings['logo_path']))
                    <img src="{{ asset('storage/' . $settings['logo_path']) }}" alt="Logo" style="height:32px;margin-bottom:4px;">
                @endif
                <h2>Admin Panel</h2>
                <p>{{ $settings['system_name'] ?? 'e-DTR System' }}</p>
            </div>
            <nav class="sidebar-nav">
                <a href="{{ route('admin.dashboard') }}"><span>Dashboard</span></a>
                <a href="{{ route('admin.offices') }}"><span>Manage Divisions</span></a>
                <a href="{{ route('admin.sections') }}"><span>Manage Sections</span></a>
                <a href="{{ route('admin.employees') }}"><span>Assign Employees</span></a>
                <a href="{{ route('admin.users') }}"><span>Manage Users</span></a>
                <a href="{{ route('admin.password-reset-requests') }}"><span>Reset Requests</span></a>
                <a href="{{ route('admin.monitoring') }}"><span>Employee Monitoring</span></a>
                <a href="{{ route('admin.coa-shares') }}"><span>COA Sharing</span></a>
                <a href="{{ route('admin.holidays') }}"><span>Holidays & Suspensions</span></a>
                <a href="{{ route('admin.work-arrangement') }}" class="active"><span>Work Arrangement</span></a>
                <a href="{{ route('admin.logs') }}"><span>User Logs</span></a>
                <a href="{{ route('admin.settings') }}"><span>Settings</span></a>
            </nav>
            <div class="sidebar-footer">
                <button onclick="toggleTheme()" class="btn btn-sm" style="background:rgba(255,255,255,0.1); color:#fff; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; font-size:12px; width:100%; margin-bottom:8px;" id="themeToggle">Dark Mode</button>
                <a href="{{ route('profile') }}" class="btn btn-sm" style="background:rgba(255,255,255,0.1);color:var(--white);width:100%;justify-content:center;margin-bottom:4px;">&#128100; My Profile</a>
                <a href="{{ route('dtr.index') }}" class="btn btn-sm" style="background:rgba(255,255,255,0.1);color:var(--white);width:100%;justify-content:center;">&larr; e-DTR Home</a>
            </div>
        </div>
        <div class="main-content">
            <div class="admin-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
                <h1 style="font-size:22px;font-weight:700;color:var(--primary);margin:0;">Work Arrangement</h1>
                <form method="POST" action="{{ route('logout') }}" class="logout-corner">
                    @csrf
                    <button class="btn btn-outline btn-sm">Logout</button>
                </form>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="card" style="margin-bottom:20px;">
                <h2>Global Default</h2>
                <form method="POST" action="{{ route('admin.work-arrangement.global') }}" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    @csrf
                    <span style="font-size:14px;font-weight:600;color:var(--gray-700);">System-wide work week:</span>
                    <select name="value" class="form-control" style="width:200px;">
                        <option value="0" {{ ($globalSetting->setting_value ?? '0') === '0' ? 'selected' : '' }}>5-day (Mon-Fri)</option>
                        <option value="1" {{ ($globalSetting->setting_value ?? '0') === '1' ? 'selected' : '' }}>4-day</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Save Global Default</button>
                </form>
                <p style="font-size:12px;color:var(--gray-500);margin-top:8px;">This sets the default for all employees. Individual overrides below take precedence.</p>
            </div>

            <div class="card">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
                    <h2 style="margin:0;">Employee Overrides ({{ $employees->count() }})</h2>
                    <form method="GET" action="{{ route('admin.work-arrangement') }}" style="display:flex;gap:8px;">
                        <input type="text" name="search" placeholder="Search by name or emp code..." value="{{ request('search') }}" style="padding:8px 12px;border:1.5px solid var(--gray-300);border-radius:6px;font-size:13px;background:var(--white);color:var(--gray-900);width:220px;outline:none;">
                        <button type="submit" class="btn btn-primary btn-sm">Search</button>
                        @if (request('search'))
                            <a href="{{ route('admin.work-arrangement') }}" class="btn btn-outline btn-sm">Clear</a>
                        @endif
                    </form>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Emp Code</th>
                                <th>Office</th>
                                <th>Current Arrangement</th>
                                <th class="text-center">Change To</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($employees as $employee)
                                <tr>
                                    <td><strong>{{ $employee->full_name }}</strong></td>
                                    <td>{{ $employee->emp_code }}</td>
                                    <td>{{ $employee->office ?: '&mdash;' }}</td>
                                    <td>
                                        @if ($employee->default_work_week === '4-day')
                                            <span style="display:inline-block;padding:2px 10px;border-radius:4px;font-size:12px;font-weight:600;background:#dbeafe;color:#1e40af;">4-day</span>
                                        @elseif ($employee->default_work_week === '5-day')
                                            <span style="display:inline-block;padding:2px 10px;border-radius:4px;font-size:12px;font-weight:600;background:#e8f5e9;color:#2e7d32;">5-day</span>
                                        @else
                                            <span style="display:inline-block;padding:2px 10px;border-radius:4px;font-size:12px;font-weight:600;background:#f0f2f5;color:#6c757d;">Default</span>
                                            <span style="font-size:11px;color:var(--gray-500);">(inherits global)</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <form method="POST" action="{{ route('admin.work-arrangement.employee', $employee) }}" style="display:inline">
                                            @csrf
                                            <select name="default_work_week" class="form-control" style="width:110px;display:inline;" onchange="this.form.submit()">
                                                <option value="default" {{ $employee->default_work_week === null ? 'selected' : '' }}>Default</option>
                                                <option value="5-day" {{ $employee->default_work_week === '5-day' ? 'selected' : '' }}>5-day</option>
                                                <option value="4-day" {{ $employee->default_work_week === '4-day' ? 'selected' : '' }}>4-day</option>
                                            </select>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted" style="padding:24px;">No employees.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
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

    </script>
</body>
</html>


