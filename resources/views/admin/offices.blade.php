<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Divisions - {{ $settings['system_name'] ?? 'e-DTR System' }}</title>
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
                <a href="{{ route('admin.offices') }}" class="active"><span>Manage Divisions</span></a>
                <a href="{{ route('admin.sections') }}"><span>Manage Sections</span></a>
                <a href="{{ route('admin.employees') }}"><span>Assign Employees</span></a>
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
                <h1 style="font-size:22px;font-weight:700;color:var(--primary);margin:0;">Manage Divisions</h1>
                <form method="POST" action="{{ route('logout') }}" class="logout-corner">
                    @csrf
                    <button class="btn btn-outline btn-sm">Logout</button>
                </form>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="card">
                <h2>Add Division</h2>
                <form method="POST" action="{{ route('admin.offices.store') }}" style="display:flex; gap:10px;">
                    @csrf
                    <input type="text" name="name" placeholder="Division name" required class="form-control" style="flex:1;">
                    <button type="submit" class="btn btn-primary">Add</button>
                </form>
                @error('name')
                    <div style="color:var(--danger); font-size:12px; margin-top:5px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="card">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
                    <h2 style="margin:0;">Offices ({{ $offices->count() }})</h2>
                    <form method="GET" action="{{ route('admin.offices') }}" style="display:flex;gap:8px;">
                        <input type="text" name="search" placeholder="Search offices..." value="{{ request('search') }}" style="padding:8px 12px;border:1.5px solid var(--gray-300);border-radius:6px;font-size:13px;background:var(--white);color:var(--gray-900);width:220px;outline:none;">
                        <button type="submit" class="btn btn-primary btn-sm">Search</button>
                        @if (request('search'))
                            <a href="{{ route('admin.offices') }}" class="btn btn-outline btn-sm">Clear</a>
                        @endif
                    </form>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th class="text-center">Sections</th>
                                <th>Supervisor</th>
                                <th>Senior Manager</th>
                                <th>OIC (Supervisor)</th>
                                <th>OIC (Senior Manager)</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($offices as $office)
                                <tr>
                                    <td><strong>{{ $office->name }}</strong></td>
                                    <td class="text-center">{{ $office->sections_count }}</td>
                                    <td>
                                        @if ($office->supervisor)
                                            {{ $office->supervisor->full_name }}
                                        @else
                                            <span class="text-muted">&mdash;</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($office->seniorManager)
                                            {{ $office->seniorManager->full_name }}
                                        @else
                                            <span class="text-muted">&mdash;</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($office->oic)
                                            {{ $office->oic->full_name }}
                                        @else
                                            <span class="text-muted">&mdash;</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($office->seniorManagerOic)
                                            {{ $office->seniorManagerOic->full_name }}
                                        @else
                                            <span class="text-muted">&mdash;</span>
                                        @endif
                                    </td>
                                    <td class="text-center nowrap">
                                        <button class="btn btn-outline btn-xs" onclick="showAssign({{ $office->id }}, '{{ $office->name }}', {{ $office->supervisor_id ?? 'null' }})">Set Supervisor</button>
                                        <button class="btn btn-outline btn-xs" onclick="showAssignSM({{ $office->id }}, '{{ $office->name }}', {{ $office->senior_manager_id ?? 'null' }})">Set Senior Manager</button>
                                        <button class="btn btn-outline btn-xs" onclick="showAssignOIC({{ $office->id }}, '{{ $office->name }}', {{ $office->oic_id ?? 'null' }})">Set OIC (Sup)</button>
                                        <button class="btn btn-outline btn-xs" onclick="showAssignSMOIC({{ $office->id }}, '{{ $office->name }}', {{ $office->senior_manager_oic_id ?? 'null' }})">Set OIC (SM)</button>
                                        <form method="POST" action="{{ route('admin.offices.delete', $office) }}"
                                              onsubmit="return confirm('Delete this office and all its sections?')" style="display:inline">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-danger btn-xs">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted" style="padding:24px;">No offices yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="assignModal" class="modal-overlay">
        <div class="modal-box">
            <h2>Set Division Supervisor</h2>
            <p class="modal-sub" id="modalOfficeName"></p>
            <form method="POST" id="assignForm">
                @csrf
                <div class="form-group">
                    <label for="modal_supervisor_id">Supervisor</label>
                    <select name="supervisor_id" id="modal_supervisor_id" class="form-control">
                        <option value="">&mdash; None &mdash;</option>
                        @foreach ($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->emp_code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeAssign()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div id="assignSM" class="modal-overlay">
        <div class="modal-box">
            <h2>Set Senior Manager</h2>
            <p class="modal-sub" id="modalSMOfficeName"></p>
            <form method="POST" id="assignSMForm">
                @csrf
                <div class="form-group">
                    <label for="modal_senior_manager_id">Senior Manager</label>
                    <select name="senior_manager_id" id="modal_senior_manager_id" class="form-control">
                        <option value="">&mdash; None &mdash;</option>
                        @foreach ($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->emp_code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeAssignSM()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div id="assignOIC" class="modal-overlay">
        <div class="modal-box">
            <h2>Set OIC (Supervisor)</h2>
            <p class="modal-sub" id="modalOICOfficeName"></p>
            <form method="POST" id="assignOICForm">
                @csrf
                <div class="form-group">
                    <label for="modal_oic_id">OIC</label>
                    <select name="oic_id" id="modal_oic_id" class="form-control">
                        <option value="">&mdash; None &mdash;</option>
                        @foreach ($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->emp_code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeAssignOIC()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div id="assignSMOIC" class="modal-overlay">
        <div class="modal-box">
            <h2>Set OIC (Senior Manager)</h2>
            <p class="modal-sub" id="modalSMOICOfficeName"></p>
            <form method="POST" id="assignSMOICForm">
                @csrf
                <div class="form-group">
                    <label for="modal_senior_manager_oic_id">OIC</label>
                    <select name="senior_manager_oic_id" id="modal_senior_manager_oic_id" class="form-control">
                        <option value="">&mdash; None &mdash;</option>
                        @foreach ($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->emp_code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-outline" onclick="closeAssignSMOIC()">Cancel</button>
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
        function showAssign(id, name, supervisorId) {
            document.getElementById('modalOfficeName').textContent = name;
            document.getElementById('assignForm').action = '{{ url('/admin/offices') }}/' + id + '/assign-supervisor';
            document.getElementById('modal_supervisor_id').value = supervisorId || '';
            document.getElementById('assignModal').classList.add('active');
        }
        function closeAssign() {
            document.getElementById('assignModal').classList.remove('active');
        }
        document.getElementById('assignModal').addEventListener('click', function(e) {
            if (e.target === this) closeAssign();
        });
        function showAssignSM(id, name, seniorManagerId) {
            document.getElementById('modalSMOfficeName').textContent = name;
            document.getElementById('assignSMForm').action = '{{ url('/admin/offices') }}/' + id + '/assign-senior-manager';
            document.getElementById('modal_senior_manager_id').value = seniorManagerId || '';
            document.getElementById('assignSM').classList.add('active');
        }
        function closeAssignSM() {
            document.getElementById('assignSM').classList.remove('active');
        }
        document.getElementById('assignSM').addEventListener('click', function(e) {
            if (e.target === this) closeAssignSM();
        });
        function showAssignOIC(id, name, oicId) {
            document.getElementById('modalOICOfficeName').textContent = name;
            document.getElementById('assignOICForm').action = '{{ url('/admin/offices') }}/' + id + '/assign-oic';
            document.getElementById('modal_oic_id').value = oicId || '';
            document.getElementById('assignOIC').classList.add('active');
        }
        function closeAssignOIC() {
            document.getElementById('assignOIC').classList.remove('active');
        }
        document.getElementById('assignOIC').addEventListener('click', function(e) {
            if (e.target === this) closeAssignOIC();
        });
        function showAssignSMOIC(id, name, smOicId) {
            document.getElementById('modalSMOICOfficeName').textContent = name;
            document.getElementById('assignSMOICForm').action = '{{ url('/admin/offices') }}/' + id + '/assign-senior-manager-oic';
            document.getElementById('modal_senior_manager_oic_id').value = smOicId || '';
            document.getElementById('assignSMOIC').classList.add('active');
        }
        function closeAssignSMOIC() {
            document.getElementById('assignSMOIC').classList.remove('active');
        }
        document.getElementById('assignSMOIC').addEventListener('click', function(e) {
            if (e.target === this) closeAssignSMOIC();
        });
    </script>
</body>
</html>


