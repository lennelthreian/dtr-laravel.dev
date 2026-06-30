<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Employees - {{ $settings['system_name'] ?? 'e-DTR System' }}</title>
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
                <a href="{{ route('admin.employees') }}" class="active"><span>Assign Employees</span></a>
                <a href="{{ route('admin.users') }}"><span>Manage Users</span></a>
                <a href="{{ route('admin.password-reset-requests') }}"><span>Reset Requests</span></a>
                <a href="{{ route('admin.monitoring') }}"><span>Employee Monitoring</span></a>
                <a href="{{ route('admin.coa-shares') }}"><span>COA Sharing</span></a>
                <a href="{{ route('admin.holidays') }}"><span>Holidays & Suspensions</span></a>
                <a href="{{ route('admin.work-arrangement') }}"><span>Work Arrangement</span></a>
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
                <h1 style="font-size:22px;font-weight:700;color:var(--primary);margin:0;">Assign Employees</h1>
                <form method="POST" action="{{ route('logout') }}" class="logout-corner">
                    @csrf
                    <button class="btn btn-outline btn-sm">Logout</button>
                </form>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="card">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
                    <h2 style="margin:0;">Employees ({{ $employees->count() }})</h2>
                    <form method="GET" action="{{ route('admin.employees') }}" style="display:flex;gap:8px;">
                        <input type="text" name="search" placeholder="Search by name, code, office..." value="{{ request('search') }}" style="padding:8px 12px;border:1.5px solid var(--gray-300);border-radius:6px;font-size:13px;background:var(--white);color:var(--gray-900);width:220px;outline:none;">
                        <button type="submit" class="btn btn-primary btn-sm">Search</button>
                        @if (request('search'))
                            <a href="{{ route('admin.employees') }}" class="btn btn-outline btn-sm">Clear</a>
                        @endif
                    </form>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Emp Code</th>
                                <th>Current Office</th>
                                <th>Current Section</th>
                                <th class="text-center">Assign</th>
                                <th class="text-center">Password</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($employees as $employee)
                                <tr>
                                    <td><strong>{{ $employee->full_name }}</strong></td>
                                    <td>{{ $employee->emp_code }}</td>
                                    <td>{{ $employee->office ?: '&mdash;' }}</td>
                                    <td>{{ $employee->section ?: '&mdash;' }}</td>
                                    <td class="text-center">
                                        <button class="btn btn-outline btn-sm" onclick="showAssign({{ $employee->id }}, '{{ $employee->full_name }}', {{ $employee->office_id ?? 'null' }}, {{ $employee->section_id ?? 'null' }})">Assign</button>
                                    </td>
                                    <td class="text-center">
                                        <form method="POST" action="{{ route('admin.employees.reset-password', $employee) }}" onsubmit="return confirm('Reset password for {{ $employee->full_name }} to &quot;password&quot;?')">
                                            @csrf
                                            <button class="btn btn-outline btn-sm">Reset</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted" style="padding:24px;">No employees.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="assignModal" class="modal-overlay">
        <div class="modal-box">
            <h2>Assign Employee</h2>
            <p class="modal-sub" id="modalEmployeeName"></p>
            <form method="POST" id="assignForm">
                @csrf
                <div class="form-group">
                    <label for="modal_office_id">Office</label>
                    <select name="office_id" id="modal_office_id" class="form-control" onchange="updateSections()">
                        <option value="">&mdash; None &mdash;</option>
                        @foreach ($offices as $office)
                            <option value="{{ $office->id }}">{{ $office->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="modal_section_id">Section</label>
                    <select name="section_id" id="modal_section_id" class="form-control">
                        <option value="">&mdash; None &mdash;</option>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeAssign()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
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
        const offices = @json($offices);

        function showAssign(id, name, officeId, sectionId) {
            document.getElementById('modalEmployeeName').textContent = name;
            document.getElementById('assignForm').action = '{{ url('/admin/employees') }}/' + id + '/assign';
            document.getElementById('modal_office_id').value = officeId || '';
            updateSections(function() {
                document.getElementById('modal_section_id').value = sectionId || '';
            });
            document.getElementById('assignModal').classList.add('active');
        }

        function closeAssign() {
            document.getElementById('assignModal').classList.remove('active');
        }

        function updateSections(callback) {
            const officeId = document.getElementById('modal_office_id').value;
            const sectionSelect = document.getElementById('modal_section_id');
            sectionSelect.innerHTML = '<option value="">&mdash; None &mdash;</option>';
            if (officeId) {
                const office = offices.find(o => o.id == officeId);
                if (office && office.sections) {
                    office.sections.forEach(function(s) {
                        const opt = document.createElement('option');
                        opt.value = s.id;
                        opt.textContent = s.name;
                        sectionSelect.appendChild(opt);
                    });
                }
            }
            if (callback) callback();
        }

        document.getElementById('assignModal').addEventListener('click', function(e) {
            if (e.target === this) closeAssign();
        });

    </script>
</body>
</html>


