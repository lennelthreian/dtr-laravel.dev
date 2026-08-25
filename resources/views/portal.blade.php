<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MBLISTTDA Portal</title>
    <link rel="stylesheet" href="{{ asset('dtr.css') }}">
    <script>if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark');</script>
    <style>
        @keyframes drift {
            0% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -20px) scale(1.05); }
            66% { transform: translate(-20px, 15px) scale(0.95); }
            100% { transform: translate(0, 0) scale(1); }
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-12px); }
        }
        @keyframes pulse-glow {
            0%, 100% { opacity: 0.4; }
            50% { opacity: 0.7; }
        }
        .portal-body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #0a1a0a 0%, #0a2e0a 30%, #0d1a0d 70%, #050a05 100%);
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        .portal-body::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 48px 48px;
            pointer-events: none;
            z-index: 0;
        }
        .portal-body::after {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            top: -100px;
            right: -100px;
            background: radial-gradient(circle, rgba(46,204,113,0.15) 0%, transparent 70%);
            border-radius: 50%;
            animation: drift 12s ease-in-out infinite;
            pointer-events: none;
            z-index: 0;
        }
        .glow-orb {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
        }
        .glow-orb-1 {
            width: 400px;
            height: 400px;
            bottom: -80px;
            left: -80px;
            background: radial-gradient(circle, rgba(46,204,113,0.1) 0%, transparent 70%);
            animation: drift 15s ease-in-out infinite reverse;
        }
        .glow-orb-2 {
            width: 250px;
            height: 250px;
            top: 40%;
            left: 60%;
            background: radial-gradient(circle, rgba(39,174,96,0.08) 0%, transparent 70%);
            animation: pulse-glow 4s ease-in-out infinite;
        }
        .portal-wrap {
            width: 100%;
            max-width: 1000px;
            text-align: center;
            position: relative;
            z-index: 1;
        }
        .portal-header {
            margin-bottom: 40px;
        }
        .portal-header h1 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: 2px;
            background: linear-gradient(135deg, #2ecc71, #27ae60, #1abc9c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: float 4s ease-in-out infinite;
        }
        .portal-header p {
            font-size: 15px;
            color: rgba(255,255,255,0.6);
            letter-spacing: 0.5px;
        }
        .portal-cards {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 24px;
        }
        @media (max-width: 800px) {
            .portal-cards { grid-template-columns: 1fr; }
            .portal-header h1 { font-size: 24px; }
        }
        .portal-card {
            background: rgba(255,255,255,0.06);
            -webkit-backdrop-filter: blur(20px);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: var(--radius-lg);
            padding: 36px 28px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            text-decoration: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            color: var(--white);
            position: relative;
            overflow: hidden;
        }
        .portal-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(46,204,113,0.6), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .portal-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 48px rgba(0,0,0,0.4);
            border-color: rgba(46,204,113,0.3);
        }
        .portal-card:hover::before {
            opacity: 1;
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
            position: relative;
        }
        .portal-card .icon-dtr {
            background: rgba(46,204,113,0.15);
            color: #2ecc71;
            border: 1px solid rgba(46,204,113,0.3);
        }
        .portal-card .icon-dts {
            background: rgba(26,188,156,0.15);
            color: #1abc9c;
            border: 1px solid rgba(26,188,156,0.3);
        }
        .portal-card .icon-ict {
            background: rgba(59,130,246,0.15);
            color: #3b82f6;
            border: 1px solid rgba(59,130,246,0.3);
        }
        .portal-card h2 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--white);
        }
        .portal-card .system-sub {
            font-size: 13px;
            color: rgba(255,255,255,0.5);
            margin-bottom: 12px;
        }
        .portal-card .badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .badge-dtr {
            background: rgba(46,204,113,0.15);
            color: #2ecc71;
            border: 1px solid rgba(46,204,113,0.3);
        }
        .badge-dts {
            background: rgba(26,188,156,0.15);
            color: #1abc9c;
            border: 1px solid rgba(26,188,156,0.3);
        }
        .badge-ict {
            background: rgba(59,130,246,0.15);
            color: #3b82f6;
            border: 1px solid rgba(59,130,246,0.3);
        }
        .portal-footer {
            margin-top: 36px;
            color: rgba(255,255,255,0.4);
            font-size: 12px;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body class="portal-body">
    <div class="portal-wrap">
        <div class="portal-header">
            @php $portalLogo = App\Models\DtrSetting::getSettings()['logo_path'] ?? null; @endphp
            @if (!empty($portalLogo))
                <img src="{{ asset('storage/' . $portalLogo) }}" alt="MBLISTTDA Logo" style="display:block;height:56px;margin:0 auto 12px;border-radius:8px;">
            @endif
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
            <a href="{{ config('app.dts_url') }}/dts/login" class="portal-card">
                <div class="icon icon-dts">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                </div>
                <h2>MBLISTTDA Document Tracking System</h2>
                <p class="system-sub">Document &amp; Records Management</p>
                <span class="badge badge-dts">Document Tracking</span>
            </a>
            <a href="{{ url('/ticket-ict/public/app') }}" class="portal-card">
                <div class="icon icon-ict">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>
                </div>
                <h2>ICT Ticketing System</h2>
                <p class="system-sub">ICT Support Requests &amp; Tracking</p>
                <span class="badge badge-ict">ICT Support</span>
            </a>
        </div>
        <div class="portal-footer">
            &copy; {{ date('Y') }} MBLISTTDA. All rights reserved.
        </div>
    </div>
</body>
</html>
