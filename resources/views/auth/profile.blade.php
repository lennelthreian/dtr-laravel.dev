<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>My Profile - {{ $settings['system_name'] ?? 'e-DTR Records' }}</title>
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
                <p>{{ $currentUser->name }}</p>
            </div>
            <nav class="sidebar-nav">
                <a href="{{ route('dtr.dashboard') }}">
                    <span>&#128197;</span> <span>Dashboard</span>
                </a>
                <a href="{{ route('dtr.index') }}">
                    <span>&#128196;</span> <span>e-DTR Records</span>
                </a>
                @if ($currentUser->is_super || (isset($isSupervisor) && $isSupervisor))
                    <a href="{{ route('supervisor.pending') }}">
                        <span>&#128276;</span> <span>Supervisor Panel</span>
                    </a>
                @endif
                @if ($currentUser->is_super)
                    <a href="{{ route('admin.dashboard') }}">
                        <span>&#9881;</span> <span>Admin</span>
                    </a>
                @endif
                <a href="{{ route('profile') }}" class="active">
                    <span>&#128100;</span> <span>My Profile</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="{{ route('dts.index') }}" style="color:rgba(255,255,255,0.8);font-size:12px;text-decoration:none;display:block;padding:6px 0;margin-bottom:4px;">&#128196; Document Tracking System</a>
                <button onclick="toggleTheme()" class="btn btn-sm" style="background:rgba(255,255,255,0.1); color:#fff; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; font-size:12px; width:100%; margin-bottom:8px;" id="themeToggle">Dark Mode</button>
            </div>
        </div>

        <div class="main-content">
            <div class="navbar no-print" style="margin-bottom:20px;">
                <div class="navbar-left">
                    <h1 style="font-size:18px; color:var(--primary); margin:0;">My Profile</h1>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="logout-corner">
                    @csrf
                    <button class="btn btn-outline btn-sm">Logout</button>
                </form>
            </div>

            @if (session('success'))
                <div class="alert alert-success" style="max-width:600px;">{{ session('success') }}</div>
            @endif

            <div class="card" style="max-width:600px;">
                <h2>Edit Profile</h2>
                <form method="POST" action="{{ route('profile.update') }}">
                    @csrf

                    <div class="form-row">
                        <div class="form-group">
                            <label for="honorific_prefix">Honorific Prefix</label>
                            <input type="text" name="honorific_prefix" id="honorific_prefix" class="form-control" value="{{ old('honorific_prefix', $user->honorific_prefix) }}" placeholder="e.g. Dr., Atty., Engr.">
                            @error('honorific_prefix') <div style="color:var(--danger);font-size:12px;margin-top:3px;">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-group">
                            <label for="honorific_suffix">Honorific Suffix</label>
                            <input type="text" name="honorific_suffix" id="honorific_suffix" class="form-control" value="{{ old('honorific_suffix', $user->honorific_suffix) }}" placeholder="e.g. Jr., III, PhD">
                            @error('honorific_suffix') <div style="color:var(--danger);font-size:12px;margin-top:3px;">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" name="first_name" id="first_name" class="form-control" value="{{ old('first_name', $user->first_name) }}" required>
                            @error('first_name') <div style="color:var(--danger);font-size:12px;margin-top:3px;">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-group">
                            <label for="middle_name">Middle Name</label>
                            <input type="text" name="middle_name" id="middle_name" class="form-control" value="{{ old('middle_name', $user->middle_name) }}">
                            @error('middle_name') <div style="color:var(--danger);font-size:12px;margin-top:3px;">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" name="last_name" id="last_name" class="form-control" value="{{ old('last_name', $user->last_name) }}" required>
                            @error('last_name') <div style="color:var(--danger);font-size:12px;margin-top:3px;">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-group">
                            <label for="sex">Sex</label>
                            <select name="sex" id="sex" class="form-control" required>
                                <option value="Male" {{ old('sex', $user->sex) === 'Male' ? 'selected' : '' }}>Male</option>
                                <option value="Female" {{ old('sex', $user->sex) === 'Female' ? 'selected' : '' }}>Female</option>
                            </select>
                            @error('sex') <div style="color:var(--danger);font-size:12px;margin-top:3px;">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" name="email" id="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                        @error('email') <div style="color:var(--danger);font-size:12px;margin-top:3px;">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label for="position">Position</label>
                        <input type="text" name="position" id="position" class="form-control" value="{{ old('position', $user->position) }}" required>
                        @error('position') <div style="color:var(--danger);font-size:12px;margin-top:3px;">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <div style="display:flex;gap:10px;align-items:center;">
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                            <a href="{{ route('password.form') }}" style="font-size:13px;color:var(--accent);">Change Password</a>
                        </div>
                    </div>
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
