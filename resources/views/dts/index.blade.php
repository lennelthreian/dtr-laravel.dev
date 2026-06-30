@extends('dts.layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="page-header">
    <h1>Document Tracking Dashboard</h1>
    <p>{{ date('F j, Y') }}</p>
</div>

<div class="stat-row">
    <div class="stat-card">
        <div class="stat-val">{{ $pendingCount }}</div>
        <div class="stat-lbl">Pending</div>
    </div>
    <div class="stat-card">
        <div class="stat-val">{{ $incomingCount }}</div>
        <div class="stat-lbl">Incoming / Received</div>
    </div>
    <div class="stat-card">
        <div class="stat-val">{{ $outgoingCount }}</div>
        <div class="stat-lbl">Outgoing</div>
    </div>
    <div class="stat-card">
        <div class="stat-val">{{ $processedCount }}</div>
        <div class="stat-lbl">Processed</div>
    </div>
    <div class="stat-card">
        <div class="stat-val">{{ $totalDocuments }}</div>
        <div class="stat-lbl">Total Documents</div>
    </div>
</div>

<div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;">
    <div style="flex:2;min-width:0;">
        <div class="card" style="margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <h2 style="margin:0;font-size:15px;">Recent Documents</h2>
                <a href="{{ route('dts.documents.create') }}" class="btn btn-primary btn-sm">+ New Document</a>
            </div>
            @if($recentDocuments->isEmpty())
                <p style="text-align:center;color:var(--gray-500);padding:24px;">No documents yet.</p>
            @else
            <table class="table" style="width:100%;">
                <thead>
                    <tr>
                        <th>Tracking #</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Office</th>
                        <th>Date</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentDocuments as $doc)
                    <tr>
                        <td><strong>{{ $doc->tracking_number }}</strong></td>
                        <td style="cursor:pointer;" onclick="window.location='{{ route('dts.documents.show', $doc) }}'">{{ $doc->title }}</td>
                        <td><span class="badge {{ $doc->type === 'incoming' ? 'badge-blue' : 'badge-green' }}">{{ ucfirst($doc->type) }}</span></td>
                        <td><span class="badge">{{ ucfirst($doc->status) }}</span></td>
                        <td>{{ optional($doc->office)->name ?? '-' }}</td>
                        <td>{{ $doc->created_at->format('M d, Y') }}</td>
                        <td>
                            @if($doc->status === 'pending' && $doc->recipient_id === auth()->id())
                            <form method="POST" action="{{ route('dts.documents.receive', $doc) }}">
                                @csrf
                                <button type="submit" class="btn btn-xs" style="background:var(--primary);color:#fff;border:none;">Receive</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
    </div>

    <div style="flex:1;min-width:280px;">
        @if($offices->isNotEmpty())
        <div class="card">
            <h2 style="font-size:15px;margin-bottom:12px;">Office Directory</h2>
            <table class="detail-table" style="width:100%;">
                @foreach($offices as $office)
                <tr>
                    <td>{{ $office->name }}</td>
                    <td style="font-size:12px;color:var(--gray-500);">
                        @if($office->sections->isNotEmpty())
                            {{ $office->sections->pluck('name')->join(', ') }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
                @endforeach
            </table>
        </div>
        @endif

        <div class="card" style="margin-top:16px;">
            <h2 style="font-size:15px;margin-bottom:12px;">Quick Links</h2>
            <div style="display:flex;flex-direction:column;gap:8px;">
                <a href="{{ route('dts.documents', ['type' => 'incoming']) }}" class="btn btn-outline btn-sm" style="text-align:left;">View Incoming</a>
                <a href="{{ route('dts.documents', ['type' => 'outgoing']) }}" class="btn btn-outline btn-sm" style="text-align:left;">View Outgoing</a>
                <a href="{{ route('dts.documents', ['type' => 'archived']) }}" class="btn btn-outline btn-sm" style="text-align:left;">View Archives</a>
            </div>
        </div>
    </div>
</div>
@endsection
