@extends('dts.layouts.app')

@section('title', 'Dashboard')

@section('styles')
<style>
    .dts-welcome { background: linear-gradient(135deg, #4A7C2E 0%, #5E8F42 60%, #8BC34A 100%); border-radius: var(--radius-md); padding: 28px 32px; color: #fff; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; box-shadow: var(--shadow-sm); }
    .dts-welcome h1 { font-size: 22px; font-weight: 700; margin: 0 0 4px; color: #fff; }
    .dts-welcome p { font-size: 13px; opacity: 0.85; margin: 0; }
    .dts-welcome .dts-welcome-actions { display: flex; gap: 8px; }
    .dts-welcome .dts-welcome-actions .btn { background: rgba(255,255,255,0.2); color: #fff; border: 1px solid rgba(255,255,255,0.3); backdrop-filter: blur(4px); font-size: 12px; padding: 8px 16px; border-radius: var(--radius-sm); cursor: pointer; font-weight: 600; text-decoration: none; transition: var(--transition); }
    .dts-welcome .dts-welcome-actions .btn:hover { background: rgba(255,255,255,0.35); }

    .dts-stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(155px, 1fr)); gap: 14px; margin-bottom: 24px; }
    .dts-stat { background: var(--white); border-radius: var(--radius-md); padding: 18px 16px; text-align: center; border: 1px solid var(--gray-200); box-shadow: var(--shadow-sm); transition: var(--transition); position: relative; overflow: hidden; }
    .dts-stat:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
    .dts-stat .dts-stat-icon { width: 36px; height: 36px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 16px; margin-bottom: 8px; }
    .dts-stat .dts-stat-num { font-size: 26px; font-weight: 800; color: var(--gray-900); line-height: 1; margin-bottom: 4px; }
    .dts-stat .dts-stat-label { font-size: 11px; font-weight: 600; color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.5px; }
    .dts-stat .dts-stat-bar { position: absolute; bottom: 0; left: 0; right: 0; height: 3px; }
    .icon-pending { background: #FFF3E0; color: #E65100; }
    .icon-incoming { background: #E3F2FD; color: #1565C0; }
    .icon-outgoing { background: #E8F5E9; color: #2E7D32; }
    .icon-processed { background: #F3E5F5; color: #7B1FA2; }
    .icon-total { background: #ECEFF1; color: #455A64; }
    .icon-users { background: #E0F7FA; color: #00838F; }

    .dts-quick-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-bottom: 20px; }
    .dts-quick-btn { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 16px 10px; background: var(--white); border: 1px solid var(--gray-200); border-radius: var(--radius-md); text-decoration: none; color: var(--gray-700); font-size: 12px; font-weight: 600; transition: var(--transition); box-shadow: var(--shadow-sm); }
    .dts-quick-btn:hover { border-color: var(--primary); color: var(--primary); background: #f0f9f0; transform: translateY(-1px); box-shadow: var(--shadow-md); }
    .dts-quick-btn .quick-icon { font-size: 20px; }

    .dts-doc-row { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--gray-100); transition: var(--transition); }
    .dts-doc-row:last-child { border-bottom: none; }
    .dts-doc-row:hover { background: var(--gray-50); margin: 0 -28px; padding: 12px 28px; }
    .dts-doc-row .dts-doc-num { font-size: 11px; font-weight: 700; color: var(--primary); background: #f0f9f0; padding: 3px 8px; border-radius: 4px; white-space: nowrap; }
    .dts-doc-row .dts-doc-info { flex: 1; min-width: 0; }
    .dts-doc-row .dts-doc-title { font-size: 13px; font-weight: 600; color: var(--gray-800); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .dts-doc-row .dts-doc-meta { font-size: 11px; color: var(--gray-500); margin-top: 2px; }
    .dts-doc-row .dts-doc-badge { flex-shrink: 0; }
    .dts-doc-row .dts-doc-action { flex-shrink: 0; }

    .dts-office-item { display: flex; align-items: flex-start; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--gray-100); }
    .dts-office-item:last-child { border-bottom: none; }
    .dts-office-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--primary); margin-top: 5px; flex-shrink: 0; }
    .dts-office-name { font-size: 13px; font-weight: 600; color: var(--gray-800); }
    .dts-office-sec { font-size: 11px; color: var(--gray-500); margin-top: 1px; }

    .dts-empty { text-align: center; padding: 40px 20px; color: var(--gray-400); }
    .dts-empty .dts-empty-icon { font-size: 40px; margin-bottom: 8px; }
    .dts-empty p { font-size: 13px; }
</style>
@endsection

@section('content')
@php $logo = App\Models\DtrSetting::getSettings()['logo_path'] ?? null; @endphp

<div class="dts-welcome">
    <div>
        <h1>Good {{ date('H') < 12 ? 'Morning' : (date('H') < 18 ? 'Afternoon' : 'Evening') }}, {{ $user->first_name }}</h1>
        <p>{{ date('l, F j, Y') }} &mdash; Here's what's happening with your documents today.</p>
    </div>
    <div class="dts-welcome-actions">
        <a href="{{ route('dts.documents.create') }}" class="btn">+ New Document</a>
        @if($super)
        <a href="{{ route('dts.analytics') }}" class="btn">Analytics</a>
        @endif
    </div>
</div>

<div class="dts-stat-grid">
    <div class="dts-stat">
        <div class="dts-stat-icon icon-pending">&#128203;</div>
        <div class="dts-stat-num">{{ $pendingCount }}</div>
        <div class="dts-stat-label">Pending{{ $super ? ' (All)' : '' }}</div>
        <div class="dts-stat-bar" style="background:linear-gradient(90deg,#FF9800,#FFB74D);"></div>
    </div>
    <div class="dts-stat">
        <div class="dts-stat-icon icon-incoming">&#128229;</div>
        <div class="dts-stat-num">{{ $incomingCount }}</div>
        <div class="dts-stat-label">My Incoming</div>
        <div class="dts-stat-bar" style="background:linear-gradient(90deg,#2196F3,#64B5F6);"></div>
    </div>
    <div class="dts-stat">
        <div class="dts-stat-icon icon-outgoing">&#128228;</div>
        <div class="dts-stat-num">{{ $outgoingCount }}</div>
        <div class="dts-stat-label">My Outgoing</div>
        <div class="dts-stat-bar" style="background:linear-gradient(90deg,#4CAF50,#81C784);"></div>
    </div>
    <div class="dts-stat">
        <div class="dts-stat-icon icon-processed">&#9989;</div>
        <div class="dts-stat-num">{{ $processedCount }}</div>
        <div class="dts-stat-label">Processed{{ $super ? ' (All)' : '' }}</div>
        <div class="dts-stat-bar" style="background:linear-gradient(90deg,#9C27B0,#CE93D8);"></div>
    </div>
    <div class="dts-stat">
        <div class="dts-stat-icon icon-total">&#128196;</div>
        <div class="dts-stat-num">{{ $totalDocuments }}</div>
        <div class="dts-stat-label">Total Documents</div>
        <div class="dts-stat-bar" style="background:linear-gradient(90deg,#607D8B,#90A4AE);"></div>
    </div>
    @if($super)
    <div class="dts-stat">
        <div class="dts-stat-icon icon-users">&#128101;</div>
        <div class="dts-stat-num">{{ $totalUsers }}</div>
        <div class="dts-stat-label">Active Users</div>
        <div class="dts-stat-bar" style="background:linear-gradient(90deg,#00BCD4,#4DD0E1);"></div>
    </div>
    @endif
</div>

<div class="dts-quick-grid">
    <a href="{{ route('dts.documents', ['type' => 'incoming']) }}" class="dts-quick-btn">
        <span class="quick-icon">&#128229;</span>
        Incoming
    </a>
    <a href="{{ route('dts.documents', ['type' => 'outgoing']) }}" class="dts-quick-btn">
        <span class="quick-icon">&#128228;</span>
        Outgoing
    </a>
    <a href="{{ route('dts.documents', ['type' => 'all']) }}" class="dts-quick-btn">
        <span class="quick-icon">&#128194;</span>
        All Docs
    </a>
    <a href="{{ route('dts.documents', ['type' => 'archived']) }}" class="dts-quick-btn">
        <span class="quick-icon">&#128451;</span>
        Archived
    </a>
    <a href="{{ route('dts.documents.create') }}" class="dts-quick-btn">
        <span class="quick-icon">&#10010;</span>
        New Doc
    </a>
    @if($super)
    <a href="{{ route('dts.analytics') }}" class="dts-quick-btn">
        <span class="quick-icon">&#128202;</span>
        Analytics
    </a>
    @endif
</div>

<div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;">
    <div style="flex:2;min-width:0;">
        <div class="card">
            <h2>Recent Documents{{ $super ? ' (All Users)' : '' }}</h2>
            @if($recentDocuments->isEmpty())
                <div class="dts-empty">
                    <div class="dts-empty-icon">&#128196;</div>
                    <p>No documents yet. Create your first document to get started.</p>
                </div>
            @else
                @foreach($recentDocuments as $doc)
                <div class="dts-doc-row" style="cursor:pointer;" onclick="window.location='{{ route('dts.documents.show', $doc) }}'">
                    <span class="dts-doc-num">{{ $doc->tracking_number }}</span>
                    <div class="dts-doc-info">
                        <div class="dts-doc-title">{{ $doc->title }}</div>
                        <div class="dts-doc-meta">
                            {{ optional($doc->sender)->name ?? 'Unknown' }} &rarr; {{ optional($doc->recipient)->name ?? 'Unknown' }}
                            &middot; {{ $doc->created_at->diffForHumans() }}
                        </div>
                    </div>
                    <div class="dts-doc-badge">
                        <span class="badge {{ $doc->type === 'incoming' ? 'badge-blue' : 'badge-green' }}">{{ ucfirst($doc->type) }}</span>
                    </div>
                    <div class="dts-doc-badge">
                        @if($doc->status === 'pending')
                            <span class="badge" style="background:#FFF3E0;color:#E65100;">Pending</span>
                        @elseif($doc->status === 'received')
                            <span class="badge" style="background:#E3F2FD;color:#1565C0;">Received</span>
                        @else
                            <span class="badge" style="background:#E8F5E9;color:#2E7D32;">Processed</span>
                        @endif
                    </div>
                    @if($doc->priority === 'urgent')
                    <div class="dts-doc-badge">
                        <span style="background:#e74c3c;color:#fff;font-size:9px;padding:2px 7px;border-radius:8px;font-weight:700;">URGENT</span>
                    </div>
                    @endif
                    <div class="dts-doc-action">
                        @if($doc->status === 'pending' && $doc->recipient_id === auth()->id())
                        <form method="POST" action="{{ route('dts.documents.receive', $doc) }}" onclick="event.stopPropagation();" onsubmit="return confirm('Receive this document?')">
                            @csrf
                            <button type="submit" class="btn btn-xs" style="background:var(--primary);color:#fff;border:none;font-size:11px;padding:4px 10px;">Receive</button>
                        </form>
                        @endif
                    </div>
                </div>
                @endforeach
            @endif
        </div>
    </div>

    <div style="flex:1;min-width:280px;">
        @if($super && $officeStats->isNotEmpty())
        <div class="card" style="margin-bottom:16px;">
            <h2>Documents by Office</h2>
            @foreach($officeStats as $stat)
            <div style="margin-bottom:10px;">
                <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px;">
                    <span style="font-weight:600;color:var(--gray-700);">{{ optional($stat->office)->name ?? 'Unassigned' }}</span>
                    <span style="font-weight:700;color:var(--gray-900);">{{ $stat->total }}</span>
                </div>
                <div style="background:var(--gray-100);border-radius:4px;height:6px;overflow:hidden;">
                    <div style="background:linear-gradient(90deg,#4A7C2E,#8BC34A);height:100%;width:{{ $totalDocuments > 0 ? round($stat->total / $totalDocuments * 100) : 0 }}%;border-radius:4px;transition:width 0.3s;"></div>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        @if($super && $categoryStats->isNotEmpty())
        <div class="card" style="margin-bottom:16px;">
            <h2>Top Categories</h2>
            @foreach($categoryStats->take(6) as $i => $stat)
            <div style="display:flex;align-items:center;gap:10px;padding:7px 0;{{ $i < $categoryStats->count() - 1 ? 'border-bottom:1px solid var(--gray-100);' : '' }}">
                <span style="width:20px;height:20px;border-radius:4px;background:{{ ['#E8F5E9','#E3F2FD','#FFF3E0','#F3E5F5','#ECEFF1','#E0F7FA'][$i % 6] }};color:{{ ['#2E7D32','#1565C0','#E65100','#7B1FA2','#455A64','#00838F'][$i % 6] }};font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;">{{ $i + 1 }}</span>
                <span style="flex:1;font-size:12px;font-weight:600;color:var(--gray-700);">{{ $stat->category }}</span>
                <span style="font-size:12px;font-weight:700;color:var(--gray-900);">{{ $stat->total }}</span>
            </div>
            @endforeach
        </div>
        @endif

        <div class="card" style="margin-bottom:16px;">
            <h2>Office Directory</h2>
            @if($offices->isEmpty())
                <div class="dts-empty" style="padding:20px;">
                    <p>No offices found.</p>
                </div>
            @else
                @foreach($offices as $office)
                <div class="dts-office-item">
                    <div class="dts-office-dot"></div>
                    <div>
                        <div class="dts-office-name">{{ $office->name }}</div>
                        @if($office->sections->isNotEmpty())
                            <div class="dts-office-sec">{{ $office->sections->pluck('name')->join(', ') }}</div>
                        @endif
                    </div>
                </div>
                @endforeach
            @endif
        </div>
    </div>
</div>
@endsection
