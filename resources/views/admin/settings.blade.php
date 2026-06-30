<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin - {{ $settings['system_name'] ?? 'e-DTR System' }}</title>
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
                <a href="{{ route('admin.work-arrangement') }}"><span>Work Arrangement</span></a>
                <a href="{{ route('admin.logs') }}"><span>User Logs</span></a>
                <a href="{{ route('admin.settings') }}" class="active"><span>Settings</span></a>
            </nav>
            <div class="sidebar-footer">
                <button onclick="toggleTheme()" class="btn btn-sm" style="background:rgba(255,255,255,0.1); color:#fff; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; font-size:12px; width:100%; margin-bottom:8px;" id="themeToggle">Dark Mode</button>
                <a href="{{ route('profile') }}" class="btn btn-sm" style="background:rgba(255,255,255,0.1);color:var(--white);width:100%;justify-content:center;margin-bottom:4px;">&#128100; My Profile</a>
                <a href="{{ route('dtr.index') }}" class="btn btn-sm" style="background:rgba(255,255,255,0.1);color:var(--white);width:100%;justify-content:center;">&larr; e-DTR Home</a>
            </div>
        </div>
        <div class="main-content">
            <div class="admin-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
                <h1 style="font-size:22px;font-weight:700;color:var(--primary);margin:0;">System Settings</h1>
                <form method="POST" action="{{ route('logout') }}" class="logout-corner">
                    @csrf
                    <button class="btn btn-outline btn-sm">Logout</button>
                </form>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="card">
                <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
                    @csrf
                    <table class="table" style="max-width:600px;">
                        <thead>
                            <tr>
                                <th style="width:250px;">Setting</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($settingModels as $setting)
                                <tr>
                                    <td>
                                        <label for="{{ $setting->setting_key }}" style="font-weight:600; font-size:13px;">
                                            @php
                                                $label = str_replace('_', ' ', $setting->setting_key);
                                                if ($setting->setting_key === 'four_day_work_week') {
                                                    $label = '4-Day Work Week';
                                                } elseif ($setting->setting_key === 'am_start_flexi') {
                                                    $label = 'AM Start Flexi Time';
                                                } elseif ($setting->setting_key === 'pm_end_flexi') {
                                                    $label = 'PM End Flexi Time';
                                                } elseif ($setting->setting_key === 'fdww_am_start') {
                                                    $label = '4-Day AM Start';
                                                } elseif ($setting->setting_key === 'fdww_am_end') {
                                                    $label = '4-Day AM End';
                                                } elseif ($setting->setting_key === 'fdww_pm_start') {
                                                    $label = '4-Day PM Start';
                                                } elseif ($setting->setting_key === 'fdww_pm_end') {
                                                    $label = '4-Day PM End';
                                                } elseif ($setting->setting_key === 'fdww_am_start_flexi') {
                                                    $label = '4-Day AM Flexi Time';
                                                } elseif ($setting->setting_key === 'fdww_pm_end_flexi') {
                                                    $label = '4-Day PM Flexi Time';
                                                } else {
                                                    $label = ucwords($label);
                                                }
                                            @endphp
                                            {{ $label }}
                                        </label>
                                    </td>
                                    <td>
                                        @if ($setting->setting_key === 'four_day_work_week')
                                            <select id="{{ $setting->setting_key }}"
                                                    name="{{ $setting->setting_key }}"
                                                    class="form-control" style="width:150px;">
                                                <option value="0" {{ $setting->setting_value == '0' ? 'selected' : '' }}>Off (Mon-Fri)</option>
                                                <option value="1" {{ $setting->setting_value == '1' ? 'selected' : '' }}>On</option>
                                            </select>
                                        @elseif ($setting->setting_key === 'agency_head_user_id')
                                            <select id="{{ $setting->setting_key }}"
                                                    name="{{ $setting->setting_key }}"
                                                    class="form-control" style="width:100%;">
                                                <option value="">-- Select User --</option>
                                                @foreach ($users as $user)
                                                    <option value="{{ $user->id }}" {{ $setting->setting_value == $user->id ? 'selected' : '' }}>
                                                        {{ $user->name }} ({{ $user->emp_code }})
                                                    </option>
                            @endforeach
                                            </select>
                                        @elseif ($setting->setting_key === 'logo_path')
                                            <div style="display:flex;align-items:center;gap:12px;">
                                                @if ($setting->setting_value)
                                                    <img src="{{ asset('storage/' . $setting->setting_value) }}"
                                                         alt="Logo" style="height:40px;border:1px solid var(--gray-300);border-radius:4px;padding:4px;background:#fff;">
                                                @endif
                                                <input type="file" id="{{ $setting->setting_key }}"
                                                       name="logo"
                                                       accept="image/*"
                                                       class="form-control" style="width:100%;">
                                            </div>
                                        @elseif (str_ends_with($setting->setting_key, '_flexi'))
                                            <div style="display:flex;align-items:center;gap:8px;">
                                                <input type="number" id="{{ $setting->setting_key }}"
                                                       name="{{ $setting->setting_key }}"
                                                       value="{{ $setting->setting_value }}"
                                                       min="0" max="120"
                                                       class="form-control" style="width:100px;">
                                                <span style="font-size:12px;color:var(--gray-500);">minutes</span>
                                            </div>
                                        @elseif ($setting->setting_key === 'system_name')
                                            <input type="text" id="{{ $setting->setting_key }}"
                                                   name="{{ $setting->setting_key }}"
                                                   value="{{ $setting->setting_value }}"
                                                   class="form-control" style="width:100%;font-size:15px;font-weight:600;">
                                        @else
                                            <input type="text" id="{{ $setting->setting_key }}"
                                                   name="{{ $setting->setting_key }}"
                                                   value="{{ $setting->setting_value }}"
                                                   class="form-control" style="width:100%;">
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <button type="submit" class="btn btn-primary" style="margin-top:16px;">Save Settings</button>
                </form>
            </div>

            <div class="card" style="margin-top:20px;">
                <h2 style="margin-bottom:12px;font-size:18px;">Database Backup</h2>
                <p style="color:var(--gray-600);margin-bottom:16px;font-size:14px;">
                    Backup both <strong>dtr_system</strong> and <strong>zkbiotime</strong> databases to Google Drive.
                    Backups run automatically every day at 11:00 PM.
                </p>

                @if ($gDriveConfigured)
                    <p style="color:#16a34a;font-size:13px;margin-bottom:12px;">
                        &check; Google Drive is configured.
                    </p>
                @else
                    <p style="color:#dc2626;font-size:13px;margin-bottom:12px;">
                        &cross; Google Drive is not configured. Upload the service account JSON key to:
                        <code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;">storage/app/google-drive/service-account.json</code>
                    </p>
                @endif

                <form method="POST" action="{{ route('admin.settings.backup') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary" {{ !$gDriveConfigured ? 'disabled' : '' }}>
                        Backup Now to Google Drive
                    </button>
                </form>
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


