<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - MBLISTTDA Document Tracking System</title>
    <link rel="stylesheet" href="{{ asset('dtr.css') }}">
    <script>if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark');</script>
    <style>
        .dts-layout { --primary: #4A7C2E; --primary-light: #5E8F42; --primary-dark: #2E5E1A; --accent: #4A7C2E; --accent-light: #5E8F42; }
        .table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .table th { text-align: left; padding: 8px 10px; border-bottom: 2px solid var(--gray-200); color: var(--gray-600); font-weight: 600; font-size: 12px; white-space: nowrap; }
        .table td { padding: 10px 10px; border-bottom: 1px solid var(--gray-100); color: var(--gray-800); }
        .table tr:hover td { background: var(--gray-50); }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; background: var(--gray-100); color: var(--gray-700); }
        .badge-blue { background: #e3f2fd; color: #1565c0; }
        .badge-green { background: #e8f5e9; color: #2e7d32; }
        .detail-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .detail-table td { padding: 8px 10px; border-bottom: 1px solid var(--gray-100); }
        .detail-table td:first-child { font-weight: 600; color: var(--gray-600); width: 140px; white-space: nowrap; }
    </style>
    @yield('styles')
</head>
<body>
    @php $currentUser = auth()->user(); @endphp
    @php
        $unreadDtsNotifications = App\Models\DtsNotification::where('user_id', $currentUser->id)
            ->where('is_read', false)->count();
        $dtsNotifications = App\Models\DtsNotification::where('user_id', $currentUser->id)
            ->where('is_read', false)->latest()->take(10)->get();
    @endphp

    <div class="layout-sidebar dts-layout">
        <div class="sidebar no-print">
            <div class="sidebar-header">
                @php $logo = App\Models\DtrSetting::getSettings()['logo_path'] ?? null; @endphp
                @if (!empty($logo))
                    <img src="{{ asset('storage/' . $logo) }}" alt="Logo" style="display:block;height:40px;margin:0 auto 8px;">
                @endif
                <h2 style="color:#fff;font-size:16px;margin:0 0 2px;">MBLISTTDA</h2>
                <p style="color:rgba(255,255,255,0.7);font-size:11px;">Document Tracking System</p>
                <p style="color:rgba(255,255,255,0.9);font-size:12px;margin-top:4px;">{{ $currentUser->name }}</p>
                @if($currentUser->office_id)
                    <p style="color:rgba(255,255,255,0.6);font-size:11px;">{{ optional($currentUser->office)->name }}{{ optional($currentUser->section)->name ? ' - ' . optional($currentUser->section)->name : '' }}</p>
                @endif
            </div>
            <nav class="sidebar-nav">
                <a href="{{ route('dts.index') }}" class="{{ request()->routeIs('dts.index') ? 'active' : '' }}">
                    Dashboard
                </a>
                <a href="{{ route('dts.documents', ['type' => 'all']) }}" class="{{ request()->routeIs('dts.documents*') && request('type', 'all') === 'all' ? 'active' : '' }}">
                    All Documents
                </a>
                <a href="{{ route('dts.documents', ['type' => 'incoming']) }}" class="{{ request('type') === 'incoming' ? 'active' : '' }}">
                    Incoming
                </a>
                <a href="{{ route('dts.documents', ['type' => 'outgoing']) }}" class="{{ request('type') === 'outgoing' ? 'active' : '' }}">
                    Outgoing
                </a>
                <a href="{{ route('dts.documents', ['type' => 'archived']) }}" class="{{ request('type') === 'archived' ? 'active' : '' }}">
                    Archived
                </a>
                <a href="{{ route('dts.documents.create') }}" class="{{ request()->routeIs('dts.documents.create') ? 'active' : '' }}">
                    + New Document
                </a>
                <a href="{{ route('dts.notifications') }}" class="{{ request()->routeIs('dts.notifications*') ? 'active' : '' }}" style="display:flex;align-items:center;justify-content:space-between;">
                    Notifications
                    @if($unreadDtsNotifications > 0)
                        <span style="background:#e74c3c;color:#fff;font-size:10px;padding:1px 7px;border-radius:10px;font-weight:700;">{{ $unreadDtsNotifications }}</span>
                    @endif
                </a>
            </nav>
            <div class="sidebar-footer" style="margin-top:16px;border-top:1px solid rgba(255,255,255,0.15);padding-top:12px;">
                <a href="{{ route('dtr.dashboard') }}" style="color:rgba(255,255,255,0.8);font-size:12px;text-decoration:none;display:block;padding:6px 0;">e-DTR System</a>
                <a href="{{ route('portal') }}" style="color:rgba(255,255,255,0.8);font-size:12px;text-decoration:none;display:block;padding:6px 0;">Portal</a>
                <button onclick="toggleTheme()" class="btn btn-sm" style="background:rgba(255,255,255,0.1);color:#fff;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:12px;width:100%;margin:12px 0 8px;">Toggle Theme</button>
                <a href="#" onclick="event.preventDefault();document.getElementById('logout-form').submit();" style="color:rgba(255,255,255,0.8);font-size:12px;text-decoration:none;">Logout</a>
            </div>
        </div>

        <div class="main-content">
            <div class="navbar no-print" style="margin-bottom:16px;">
                <div class="navbar-left">
                    <div id="dtsClock" style="font-size:13px;font-weight:600;color:var(--gray-700);"></div>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <div class="notif-pos">
                        <button class="notif-btn" onclick="toggleNotif()">&#128276;
                            @if ($unreadDtsNotifications > 0)
                                <span class="notif-badge">{{ $unreadDtsNotifications }}</span>
                            @endif
                        </button>
                        <div id="notifDropdown" class="notif-dropdown">
                            <div class="notif-header">Notifications</div>
                            @forelse ($dtsNotifications as $notif)
                                <a href="{{ route('dts.notifications.read', $notif->id) }}" class="notif-item" data-notif-id="{{ $notif->id }}">
                                    <strong>{{ $notif->message }}</strong>
                                    <div class="notif-time">{{ $notif->created_at->diffForHumans() }}</div>
                                </a>
                            @empty
                                <div style="padding:24px;text-align:center;color:var(--gray-500);font-size:13px;">No new notifications</div>
                            @endforelse
                            @if ($unreadDtsNotifications > 0)
                                <div class="notif-footer">
                                    <form method="POST" action="{{ route('dts.notifications.mark-all-read') }}">
                                        @csrf
                                        <button type="submit" style="background:none;border:none;color:var(--accent);font-size:12px;font-weight:600;cursor:pointer;">Mark all as read</button>
                                    </form>
                                </div>
                            @endif
                            <div class="notif-footer" style="border-top:none;padding-top:0;">
                                <a href="{{ route('dts.notifications') }}" style="color:var(--gray-600);font-size:11px;">View all notifications</a>
                            </div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="logout-corner" style="margin:0;">
                        @csrf
                        <button class="btn btn-outline btn-sm">Logout</button>
                    </form>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger" style="margin-bottom:16px;">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            @yield('content')
        </div>
    </div>

    <script>
    function updateClock() {
        var now = new Date();
        var opts = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
        document.getElementById('dtsClock').textContent = now.toLocaleDateString('en-PH', opts);
    }
    updateClock();
    setInterval(updateClock, 1000);

    function toggleNotif() {
        var el = document.getElementById('notifDropdown');
        el.classList.toggle('active');
    }
    document.addEventListener('click', function(e) {
        var dd = document.getElementById('notifDropdown');
        if (dd && dd.classList.contains('active') && !e.target.closest('.notif-pos')) {
            dd.classList.remove('active');
        }
    });
    </script>

    <form id="logout-form" method="POST" action="{{ route('logout') }}" style="display:none;">
        @csrf
    </form>

    <script>
    function toggleTheme() {
        var el = document.documentElement;
        if (el.getAttribute('data-theme') === 'dark') {
            el.removeAttribute('data-theme');
            localStorage.setItem('theme', 'light');
        } else {
            el.setAttribute('data-theme', 'dark');
            localStorage.setItem('theme', 'dark');
        }
    }
    </script>
    @stack('scripts')
</body>
</html>
