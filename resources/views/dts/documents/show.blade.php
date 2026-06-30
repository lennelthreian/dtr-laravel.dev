@extends('dts.layouts.app')

@section('title', $document->tracking_number)

@section('content')
<div class="page-header">
    <div>
        <h1>Document #{{ $document->tracking_number }}</h1>
        <p style="margin-top:4px;font-size:13px;color:var(--gray-500);">
            Created {{ $document->created_at->format('F j, Y g:i A') }}
            by {{ optional($document->creator)->name ?? 'System' }}
        </p>
    </div>
    <div style="background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius-md);padding:12px 16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">

        @if($document->status === 'pending' && $document->recipient_id === auth()->id())
        <form method="POST" action="{{ route('dts.documents.receive', $document) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-primary">&#10003; Receive Document</button>
        </form>
        @endif

        @if(in_array($document->status, ['received', 'pending']) && ($document->recipient_id === auth()->id() || $document->sender_id === auth()->id()))
        <form method="POST" action="{{ route('dts.documents.process', $document) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-accent">&#9889; Mark as Processed</button>
        </form>
        @endif

        <a href="{{ route('dts.documents.print-routing-slip', $document) }}" class="btn" style="background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-300);" target="_blank">&#128424; Print Routing Slip</a>
        <a href="{{ route('dts.documents.edit', $document) }}" class="btn" style="background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-300);">&#9998; Edit Document</a>

        @if(!$document->trashed())
        <form method="POST" action="{{ route('dts.documents.destroy', $document) }}" style="display:inline;" onsubmit="return confirm('Archive this document?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">&#128451; Archive</button>
        </form>
        @else
        <form method="POST" action="{{ route('dts.documents.restore', $document->id) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-accent">&#128260; Restore</button>
        </form>
        @endif

        <span style="margin-left:auto;border-left:1px solid var(--gray-300);padding-left:12px;">
            <a href="{{ route('dts.documents') }}" class="btn btn-outline">&larr; Back to List</a>
        </span>
    </div>
</div>

@php
$statusSteps = ['pending', 'received', 'processed'];
$currentStep = array_search($document->status, $statusSteps);
$currentStep = $currentStep !== false ? $currentStep : 0;
@endphp
<div style="background:var(--white);border:1px solid var(--gray-200);border-radius:var(--radius-md);padding:14px 20px;margin-bottom:16px;">
    <div style="display:flex;align-items:center;gap:0;">
        @foreach($statusSteps as $i => $step)
            @php
                $isCompleted = $i < $currentStep;
                $isCurrent = $i === $currentStep;
                $stepColor = $isCompleted ? 'var(--primary)' : ($isCurrent ? 'var(--accent)' : 'var(--gray-300)');
            @endphp
            <div style="flex:1;text-align:center;position:relative;">
                @if($i > 0)
                <div style="position:absolute;top:12px;right:50%;left:-50%;height:3px;background:{{ $isCompleted ? 'var(--primary)' : 'var(--gray-200)' }};z-index:0;"></div>
                @endif
                <div style="position:relative;z-index:1;display:inline-block;width:28px;height:28px;border-radius:50%;background:{{ $stepColor }};color:#fff;font-size:12px;font-weight:700;line-height:28px;text-align:center;margin-bottom:5px;">
                    @if($isCompleted) &#10003; @else {{ $i + 1 }} @endif
                </div>
                <div style="font-size:11px;font-weight:{{ $isCurrent ? '700' : '500' }};color:{{ $isCurrent ? 'var(--gray-900)' : 'var(--gray-500)' }};text-transform:uppercase;">{{ $step }}</div>
            </div>
        @endforeach
    </div>
</div>

<div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;">
    <div style="flex:2;min-width:300px;">
        <div class="card" style="margin-bottom:16px;">
            <h2 style="font-size:15px;margin-bottom:16px;">Document Details</h2>
            <table class="detail-table" style="width:100%;">
                <tr>
                    <td>Tracking #</td>
                    <td><strong>{{ $document->tracking_number }}</strong></td>
                </tr>
                <tr>
                    <td>Title</td>
                    <td>{{ $document->title }}</td>
                </tr>
                <tr>
                    <td>Description</td>
                    <td>{{ $document->description ?: '-' }}</td>
                </tr>
                <tr>
                    <td>Type</td>
                    <td><span class="badge {{ $document->type === 'incoming' ? 'badge-blue' : 'badge-green' }}">{{ ucfirst($document->type) }}</span></td>
                </tr>
                <tr>
                    <td>Status</td>
                    <td><span class="badge">{{ ucfirst($document->status) }}</span></td>
                </tr>
                <tr>
                    <td>Priority</td>
                    <td>
                        @if($document->priority === 'urgent')
                            <span style="color:#e74c3c;font-weight:600;">URGENT</span>
                        @else
                            {{ ucfirst($document->priority) }}
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>Sender</td>
                    <td>{{ optional($document->sender)->name ?? '-' }}</td>
                </tr>
                <tr>
                    <td>Recipient</td>
                    <td>{{ optional($document->recipient)->name ?? '-' }}</td>
                </tr>
                <tr>
                    <td>Office</td>
                    <td>{{ optional($document->office)->name ?? '-' }}</td>
                </tr>
                <tr>
                    <td>Section</td>
                    <td>{{ optional($document->section)->name ?? '-' }}</td>
                </tr>
                <tr>
                    <td>Date Received</td>
                    <td>{{ $document->date_received ? $document->date_received->format('F j, Y') : '-' }}</td>
                </tr>
                <tr>
                    <td>Date Actioned</td>
                    <td>{{ $document->date_actioned ? $document->date_actioned->format('F j, Y') : '-' }}</td>
                </tr>
                <tr>
                    <td>Deadline</td>
                    <td>{{ $document->deadline ? $document->deadline->format('F j, Y') : '-' }}</td>
                </tr>
                <tr>
                    <td>Remarks</td>
                    <td>{{ $document->remarks ?: '-' }}</td>
                </tr>
            </table>
        </div>

        @if(!$document->trashed())
        <div class="card">
            <h2 style="font-size:15px;margin-bottom:12px;">Forward Document</h2>
            <form method="POST" action="{{ route('dts.documents.forward', $document) }}">
                @csrf
                <div class="form-row" style="margin-bottom:8px;">
                    <div class="form-group">
                        <label style="font-size:12px;">Office</label>
                        <select id="fwd_office_id" class="form-control" onchange="loadFwdSections(this.value)">
                            <option value="">-- Select Office --</option>
                            @foreach($offices as $office)
                                <option value="{{ $office->id }}">{{ $office->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="font-size:12px;">Section</label>
                        <select id="fwd_section_id" class="form-control" onchange="loadFwdUsers()">
                            <option value="">-- Select Section --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="font-size:12px;">Recipient</label>
                        <select name="recipient_id" id="fwd_recipient_id" class="form-control" required>
                            <option value="">-- Select User --</option>
                        </select>
                    </div>
                </div>
                <div style="display:flex;gap:8px;">
                    <input type="text" name="notes" class="form-control" placeholder="Forwarding notes (optional)" style="flex:1;">
                    <button type="submit" class="btn btn-primary">Forward</button>
                </div>
            </form>
        </div>
        @endif
    </div>

    <div style="flex:1;min-width:280px;">
        <div class="card">
            <h2 style="font-size:15px;margin-bottom:12px;">Activity Log</h2>
            @if($document->logs->isEmpty())
                <p style="text-align:center;color:var(--gray-500);padding:16px;">No activity recorded.</p>
            @else
            <div style="max-height:400px;overflow-y:auto;">
                @foreach($document->logs as $log)
                <div style="padding:8px 0;border-bottom:1px solid var(--gray-100);font-size:13px;">
                    <div style="color:var(--gray-600);">
                        <strong>{{ optional($log->user)->name ?? 'System' }}</strong>
                        <span style="color:var(--accent);font-weight:600;">{{ $log->action }}</span>
                        @if($log->from_status && $log->to_status)
                            <span style="color:var(--gray-400);font-size:11px;">{{ $log->from_status }} &rarr; {{ $log->to_status }}</span>
                        @endif
                    </div>
                    @if($log->notes)
                        <div style="color:var(--gray-500);font-size:12px;margin-top:2px;">{{ $log->notes }}</div>
                    @endif
                    <div style="color:var(--gray-400);font-size:11px;margin-top:2px;">{{ $log->created_at->format('M d, Y g:i A') }}</div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function loadFwdSections(officeId) {
    var selSec = document.getElementById('fwd_section_id');
    var selUser = document.getElementById('fwd_recipient_id');
    selSec.innerHTML = '<option value="">-- Select Section --</option>';
    selUser.innerHTML = '<option value="">-- Select User --</option>';
    if (!officeId) return;
    fetch('/dts/sections-by-office/' + officeId)
        .then(r => r.json())
        .then(data => {
            for (var id in data) selSec.innerHTML += '<option value="' + id + '">' + data[id] + '</option>';
        });
    fetch('/dts/users-by-office/' + officeId)
        .then(r => r.json())
        .then(data => {
            for (var u of data) selUser.innerHTML += '<option value="' + u.id + '">' + u.name + '</option>';
        });
}
function loadFwdUsers() {
    var officeId = document.getElementById('fwd_office_id').value;
    var sectionId = document.getElementById('fwd_section_id').value;
    var selUser = document.getElementById('fwd_recipient_id');
    selUser.innerHTML = '<option value="">Loading...</option>';
    var url = sectionId ? '/dts/users-by-section/' + sectionId : (officeId ? '/dts/users-by-office/' + officeId : null);
    if (!url) { selUser.innerHTML = '<option value="">-- Select User --</option>'; return; }
    fetch(url).then(r => r.json()).then(data => {
        selUser.innerHTML = '<option value="">-- Select User --</option>';
        for (var u of data) selUser.innerHTML += '<option value="' + u.id + '">' + u.name + '</option>';
    }).catch(() => { selUser.innerHTML = '<option value="">-- Select User --</option>'; });
}
</script>
@endpush
