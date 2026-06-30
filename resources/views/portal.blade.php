<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MBLISTTDA Portal</title>
    <link rel="stylesheet" href="{{ asset('dtr.css') }}">
    <script>if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark');</script>
    <style>
        .portal-body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            padding: 20px;
        }
        .portal-wrap {
            width: 100%;
            max-width: 800px;
            text-align: center;
        }
        .portal-header {
            margin-bottom: 40px;
            color: var(--white);
        }
        .portal-header h1 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: 1px;
        }
        .portal-header p {
            font-size: 15px;
            opacity: 0.85;
        }
        .portal-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        @media (max-width: 600px) {
            .portal-cards { grid-template-columns: 1fr; }
            .portal-header h1 { font-size: 24px; }
        }
        .portal-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: 36px 28px;
            box-shadow: var(--shadow-lg);
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            color: var(--gray-900);
        }
        .portal-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.18);
        }
        .portal-card .icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 16px;
        }
        .portal-card .icon-dtr {
            background: #e8f5e9;
            color: var(--primary);
        }
        .portal-card .icon-dts {
            background: #e3f2fd;
            color: #1565c0;
        }
        .portal-card h2 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--gray-800);
        }
        .portal-card .system-sub {
            font-size: 13px;
            color: var(--gray-500);
            margin-bottom: 12px;
        }
        .portal-card .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-dtr {
            background: #e8f5e9;
            color: var(--primary);
        }
        .badge-dts {
            background: #e3f2fd;
            color: #1565c0;
        }
        .portal-footer {
            margin-top: 36px;
            color: rgba(255,255,255,0.7);
            font-size: 12px;
        }
    </style>
</head>
<body class="portal-body">
    <div class="portal-wrap">
        <div class="portal-header">
            <h1>MBLISTTDA Portal</h1>
            <p>Metropolitan Baguio City, La Trinidad, Itogon, Sablan, Tuba, and Tublay Development Authority</p>
        </div>
        <div class="portal-cards">
            <a href="{{ route('login') }}" class="portal-card">
                <div class="icon icon-dtr">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M8 14h.01M12 14h.01M16 14h.01"/></svg>
                </div>
                <h2>MBLISTTDA e-DTR System</h2>
                <p class="system-sub">Electronic Daily Time Record</p>
                <span class="badge badge-dtr">Time &amp; Attendance</span>
            </a>
            <a href="{{ route('dts.login') }}" class="portal-card">
                <div class="icon icon-dts">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                </div>
                <h2>MBLISTTDA Document Tracking System</h2>
                <p class="system-sub">Document &amp; Records Management</p>
                <span class="badge badge-dts">Document Tracking</span>
            </a>
        </div>
        <div class="portal-footer">
            &copy; {{ date('Y') }} MBLISTTDA. All rights reserved.
        </div>
    </div>
</body>
</html>
