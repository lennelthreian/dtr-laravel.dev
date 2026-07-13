<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MBLISTTDA Document Tracking System</title>
    <link rel="stylesheet" href="{{ asset('dtr.css') }}">
    <script>if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark');</script>
    <style>
        .dts-auth-body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #4A7C2E 0%, #5E8F42 100%);
            padding: 20px;
        }
        .dts-auth-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 36px 40px;
            width: 100%;
            width: 420px;
            height: 507.12px;
            box-shadow: var(--shadow-lg);
        }
        .dts-auth-card h1 {
            text-align: center;
            font-size: 20px;
            color: #4A7C2E;
            margin-bottom: 2px;
        }
        .dts-auth-card .auth-subtitle {
            text-align: center;
            color: var(--gray-500);
            font-size: 13px;
            margin-bottom: 24px;
        }
        .dts-auth-card .form-group {
            margin-bottom: 16px;
        }
        .dts-auth-card label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 4px;
        }
        .dts-auth-card .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-family: var(--font);
            transition: var(--transition);
        }
        .dts-auth-card .form-control:focus {
            outline: none;
            border-color: #4A7C2E;
            box-shadow: 0 0 0 3px rgba(74,124,46,0.15);
        }
        .dts-auth-card .btn {
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }
        .dts-auth-card .btn-primary {
            background: #4A7C2E;
            color: #fff;
        }
        .dts-auth-card .btn-primary:hover {
            background: #2E5E1A;
        }
        .dts-auth-card .auth-link {
            text-align: center;
            margin-top: 18px;
            font-size: 13px;
        }
        .dts-auth-card .auth-link a {
            color: #4A7C2E;
            font-weight: 600;
            text-decoration: none;
        }
        .dts-back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: rgba(255,255,255,0.85);
            font-size: 13px;
            text-decoration: none;
        }
        .dts-back-link:hover {
            color: #fff;
            text-decoration: underline;
        }
    </style>
</head>
<body class="dts-auth-body">
    <div>
        <div class="dts-auth-card">
            @if (!empty($settings['logo_path']))
                <img src="{{ asset('storage/' . $settings['logo_path']) }}" alt="Logo" style="display:block;height:48px;margin:0 auto 12px;">
            @endif
            <h1>MBLISTTDA</h1>
            <p class="auth-subtitle">Document Tracking System</p>
            <form method="POST" action="{{ route('dts.login') }}">
                @csrf
                <div class="form-group">
                    <label for="username">Username</label>
                    <input id="username" type="text" name="username" value="{{ old('username') }}" required autofocus class="form-control">
                    @error('username')
                        <div style="color:var(--danger); font-size:12px; margin-top:4px;">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" required class="form-control">
                </div>
                <button type="submit" class="btn btn-primary">Login</button>
                <div class="auth-link">
                    <a href="{{ route('portal') }}">Back to Portal</a>
                </div>
            </form>
        </div>
        <a href="{{ route('portal') }}" class="dts-back-link">&larr; Back to MBLISTTDA Portal</a>
    </div>
</body>
</html>
