<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Supervisor Panel - {{ $settings['system_name'] ?? 'e-DTR System' }}</title>
    <link rel="stylesheet" href="{{ asset('dtr.css') }}">
    <script>if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark');</script>
</head>
<body>
    @php $currentUser = auth()->user(); @endphp

    <div class="layout-sidebar">
        <div class="sidebar">
            <div class="sidebar-header">
                @if (!empty($settings['logo_path']))
                    <img src="{{ asset('storage/' . $settings['logo_path']) }}" alt="Logo" style="height:32px;margin-bottom:4px;">
                @endif
                <h2>Supervisor</h2>
                <p>{{ $currentUser->name }} @if($currentUser->is_coa)<span style="display:inline-block;background:#e74c3c;color:#fff;font-size:10px;padding:1px 6px;border-radius:8px;vertical-align:middle;margin-left:4px;">COA</span>@endif</p>
            </div>
            <nav class="sidebar-nav">
                <a href="{{ route('supervisor.pending') }}" class="active">
                    <span>&#128276;</span> <span>Pending Requests</span>
                    @php $pendingCount = $grouped->flatten()->count(); @endphp
                    @if ($pendingCount > 0)
                        <span class="badge">{{ $pendingCount }}</span>
                    @endif
                </a>
                <a href="{{ route('dtr.index') }}">
                    <span>&#128196;</span> <span>e-DTR Records</span>
                </a>
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
            <div class="navbar" style="margin-bottom:20px;">
                <div class="navbar-left">
                    <h1 style="font-size:20px; color:var(--primary); margin:0;">Pending Edit Requests</h1>
                </div>
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
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if ($grouped->isEmpty())
                <div class="card empty-state">
                    <p>No pending edit requests.</p>
                    <p>When employees submit requests, they will appear here.</p>
                </div>
            @else
                @foreach ($grouped as $employeeLabel => $requests)
                    <div class="card req-group">
                        <h3>{{ $employeeLabel }}</h3>
                        <div class="table-wrap">
                            <form method="POST" action="{{ route('dtr.edit-requests.batch-approve') }}" class="batch-form">
                                @csrf
                                <table>
                                    <thead>
                                        <tr>
                                            <th style="width:32px;"><input type="checkbox" class="select-all" onchange="toggleGroup(this)"></th>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Details</th>
                                            <th>Reason</th>
                                            <th>Submitted</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($requests as $req)
                                            @php
                                                $typeLabels = [
                                                    'time_correction' => 'Time Correction',
                                                    'absent' => 'Whole Day Absent',
                                                    'halfday_am' => 'Halfday (AM)',
                                                    'halfday_pm' => 'Halfday (PM)',
                                                    'holiday' => 'Holiday',
                                                    'wfh' => 'WFH',
                                                    'special_order' => 'Special Order',
                                                    'travel_order' => 'Travel Order',
                                                    'official_business' => 'Official Business',
                                                    'delete_request' => 'Deletion Request',
                                                ];
                                                $details = '';
                                                if ($req->type === 'time_correction') {
                                                    $details = $req->field . ': ' . ($req->old_value ?: '&mdash;') . ' &rarr; ' . $req->new_value;
                                                } elseif (in_array($req->type, ['halfday_am', 'halfday_pm']) && $req->new_value) {
                                                    $label = $req->type === 'halfday_am' ? 'AM out' : 'PM in';
                                                    $details = $label . ': ' . $req->new_value;
                                                } elseif ($req->type === 'delete_request' && $req->deletionOf) {
                                                    $details = 'Delete approved edit on ' . $req->deletionOf->target_date->format('M d, Y') . ' (' . ($typeLabels[$req->deletionOf->type] ?? $req->deletionOf->type) . ')';
                                                } else {
                                                    $details = $typeLabels[$req->type] ?? $req->type;
                                                }
                                            @endphp
                                            <tr>
                                                <td><input type="checkbox" name="ids[]" value="{{ $req->id }}" class="req-checkbox"></td>
                                                <td>{{ $req->target_date->format('M d, Y') }}</td>
                                                <td><strong>{{ $typeLabels[$req->type] ?? $req->type }}</strong></td>
                                                <td>{!! $details !!}</td>
                                                <td style="max-width:200px;">{{ $req->reason }}</td>
                                                <td style="font-size:12px; color:var(--gray-500);">{{ $req->created_at->diffForHumans() }}</td>
                                                <td class="text-center nowrap">
                                                    <button type="button" class="btn btn-outline btn-xs" onclick="viewRequest({{ $req->id }})">View</button>
                                                    <button type="button" class="btn btn-accent btn-xs" onclick="singleApprove({{ $req->id }})">Approve</button>
                                                    <button type="button" class="btn btn-danger btn-xs" onclick="singleReject({{ $req->id }})">Reject</button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                <div class="batch-bar" style="display:none; padding:10px 12px; background:var(--gray-100); border-top:1px solid var(--gray-200);">
                                    <span class="selected-count" style="font-size:13px; color:var(--gray-600);">0 selected</span>
                                    <button type="submit" class="btn btn-accent btn-sm" style="margin-left:auto;">Approve Selected</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    <div id="viewModal" class="modal-overlay">
        <div class="modal-box">
            <h2>Edit Request Details</h2>
            <p class="modal-sub" id="viewEmployeeName"></p>
            <table class="detail-table">
                <tr><td>Type:</td><td id="viewType"></td></tr>
                <tr><td>Date:</td><td id="viewDate"></td></tr>
                <tr><td>Field:</td><td id="viewField"></td></tr>
                <tr><td>Old Value:</td><td id="viewOldValue"></td></tr>
                <tr><td>New Value:</td><td id="viewNewValue"></td></tr>
                <tr><td>Reason:</td><td id="viewReason"></td></tr>
                <tr><td>Submitted:</td><td id="viewSubmitted"></td></tr>
            </table>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeView()">Close</button>
            </div>
        </div>
    </div>

    <div id="rejectModal" class="modal-overlay">
        <div class="modal-box">
            <h2>Reject Request</h2>
            <p style="margin-bottom:12px;color:var(--gray-600);">Provide a reason for rejecting this request:</p>
            <textarea id="rejectionReason" rows="3" class="form-control" placeholder="Reason for rejection..." style="width:100%;resize:vertical;"></textarea>
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeReject()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="submitReject()">Reject</button>
            </div>
        </div>
    </div>

    <script>
        var requests = @json($grouped->flatten());

        function viewRequest(id) {
            var req = requests.find(function(r) { return r.id === id; });
            if (!req) return;
            document.getElementById('viewEmployeeName').textContent = req.employee ? (req.employee.first_name + ' ' + req.employee.last_name) : '';
            var typeLabels = {
                time_correction: 'Time Correction',
                absent: 'Whole Day Absent',
                halfday_am: 'Halfday (AM)',
                halfday_pm: 'Halfday (PM)',
                holiday: 'Holiday',
                wfh: 'WFH',
                special_order: 'Special Order',
                travel_order: 'Travel Order',
                official_business: 'Official Business',
                delete_request: 'Deletion Request'
            };
            document.getElementById('viewType').textContent = typeLabels[req.type] || req.type;
            document.getElementById('viewDate').textContent = req.target_date;
            document.getElementById('viewField').textContent = req.field || '&mdash;';
            document.getElementById('viewOldValue').textContent = req.old_value || '&mdash;';
            document.getElementById('viewNewValue').textContent = req.new_value || '&mdash;';
            document.getElementById('viewReason').textContent = req.reason || '&mdash;';
            document.getElementById('viewSubmitted').textContent = req.created_at ? new Date(req.created_at).toLocaleString() : '&mdash;';
            document.getElementById('viewModal').classList.add('active');
        }

        function closeView() {
            document.getElementById('viewModal').classList.remove('active');
        }

        document.getElementById('viewModal').addEventListener('click', function(e) {
            if (e.target === this) closeView();
        });

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
        document.querySelectorAll('.batch-form').forEach(function(form) {
            var checkboxes = form.querySelectorAll('.req-checkbox');
            var selectAll = form.querySelector('.select-all');
            var batchBar = form.querySelector('.batch-bar');
            var countEl = form.querySelector('.selected-count');

            function updateBatchBar() {
                var checked = form.querySelectorAll('.req-checkbox:checked').length;
                if (checked > 0) {
                    batchBar.style.display = 'flex';
                    countEl.textContent = checked + ' selected';
                } else {
                    batchBar.style.display = 'none';
                }
            }

            checkboxes.forEach(function(cb) {
                cb.addEventListener('change', function() {
                    updateBatchBar();
                    if (selectAll) {
                        var all = form.querySelectorAll('.req-checkbox');
                        var checked = form.querySelectorAll('.req-checkbox:checked');
                        selectAll.checked = all.length === checked.length;
                    }
                });
            });

            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    form.querySelectorAll('.req-checkbox').forEach(function(cb) {
                        cb.checked = selectAll.checked;
                    });
                    updateBatchBar();
                });
            }
        });

        function toggleGroup(el) {
            var form = el.closest('form');
            form.querySelectorAll('.req-checkbox').forEach(function(cb) {
                cb.checked = el.checked;
            });
            var batchBar = form.querySelector('.batch-bar');
            var countEl = form.querySelector('.selected-count');
            if (el.checked) {
                batchBar.style.display = 'flex';
                countEl.textContent = form.querySelectorAll('.req-checkbox').length + ' selected';
            } else {
                batchBar.style.display = 'none';
            }
        }

        function singleApprove(id) {
            if (!confirm('Approve this request?')) return;
            fetch('/dtr/edit-request/' + id + '/approve', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                }
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (data.success) location.reload();
            }).catch(function() { location.reload(); });
        }

        var rejectId = null;

        function singleReject(id) {
            rejectId = id;
            document.getElementById('rejectionReason').value = '';
            document.getElementById('rejectModal').classList.add('active');
        }

        function closeReject() {
            document.getElementById('rejectModal').classList.remove('active');
            rejectId = null;
        }

        function submitReject() {
            var reason = document.getElementById('rejectionReason').value.trim();
            if (!reason) {
                alert('Please provide a reason for rejection.');
                return;
            }
            fetch('/dtr/edit-request/' + rejectId + '/reject', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ rejection_reason: reason })
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (data.success) location.reload();
            }).catch(function() { location.reload(); });
        }

        document.getElementById('rejectModal').addEventListener('click', function(e) {
            if (e.target === this) closeReject();
        });

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
