@extends('dts.layouts.app')

@section('title', ucfirst($type) . ' Documents')

@section('content')
<div class="page-header">
    <h1>{{ $type === 'archived' ? 'Archived' : ucfirst($type) }} Documents</h1>
    <a href="{{ route('dts.documents.create') }}" class="btn btn-primary btn-sm">+ New Document</a>
</div>

<div class="card" style="margin-bottom:16px;">
    <form method="GET" action="{{ route('dts.documents') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
        <input type="hidden" name="type" value="{{ $type }}">
        <div class="form-group" style="flex:1;min-width:200px;margin-bottom:0;">
            <label for="search" style="font-size:12px;">Search</label>
            <input type="text" id="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Tracking #, title, or description">
        </div>
        @if($type !== 'archived')
        <div class="form-group" style="min-width:130px;margin-bottom:0;">
            <label for="status" style="font-size:12px;">Status</label>
            <select id="status" name="status" class="form-control">
                <option value="">All</option>
                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="received" {{ request('status') === 'received' ? 'selected' : '' }}>Received</option>
                <option value="processed" {{ request('status') === 'processed' ? 'selected' : '' }}>Processed</option>
            </select>
        </div>
        @endif
        <div class="form-group" style="min-width:120px;margin-bottom:0;">
            <label for="priority" style="font-size:12px;">Priority</label>
            <select id="priority" name="priority" class="form-control">
                <option value="">All</option>
                <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>Low</option>
                <option value="normal" {{ request('priority') === 'normal' ? 'selected' : '' }}>Normal</option>
                <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>High</option>
                <option value="urgent" {{ request('priority') === 'urgent' ? 'selected' : '' }}>Urgent</option>
            </select>
        </div>
        <div class="form-group" style="min-width:120px;margin-bottom:0;">
            <label for="category" style="font-size:12px;">Category</label>
            <select id="category" name="category" class="form-control">
                <option value="">All</option>
                @foreach(['Memo','Letter','Report','Purchase Request','Travel Order','Special Order','Office Order','OJT Agreement','Contract','Other'] as $cat)
                    <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                @endforeach
            </select>
        </div>
        <div style="display:flex;gap:8px;align-items:flex-end;">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <a href="{{ route('dts.documents', ['type' => $type]) }}" class="btn btn-outline btn-sm">Clear</a>
        </div>
    </form>
</div>

<div class="card">
    @if($documents->isEmpty())
        <p style="text-align:center;color:var(--gray-500);padding:32px;">No documents found.</p>
    @else
    <table class="table" style="width:100%;">
        <thead>
            <tr>
                <th>Tracking #</th>
                <th>Title</th>
                <th>Category</th>
                <th>Type</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Sender</th>
                <th>Recipient</th>
                <th>Office</th>
                <th>Date</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($documents as $doc)
            <tr>
                <td><strong>{{ $doc->tracking_number }}</strong></td>
                <td>
                    <a href="{{ route('dts.documents.show', $doc) }}" style="color:var(--accent);text-decoration:none;">
                        {{ Str::limit($doc->title, 40) }}
                    </a>
                    @if($doc->priority === 'urgent')
                        <span style="display:inline-block;background:#e74c3c;color:#fff;font-size:9px;padding:1px 6px;border-radius:8px;vertical-align:middle;margin-left:4px;">URGENT</span>
                    @endif
                </td>
                <td style="font-size:12px;">{{ $doc->category ?? '-' }}</td>
                <td><span class="badge {{ $doc->type === 'incoming' ? 'badge-blue' : 'badge-green' }}">{{ ucfirst($doc->type) }}</span></td>
                <td>
                    @if($doc->priority === 'urgent')
                        <span style="color:#e74c3c;font-weight:600;">{{ ucfirst($doc->priority) }}</span>
                    @else
                        {{ ucfirst($doc->priority) }}
                    @endif
                </td>
                <td><span class="badge">{{ ucfirst($doc->status) }}</span></td>
                <td>{{ optional($doc->sender)->name ?? '-' }}</td>
                <td>{{ optional($doc->recipient)->name ?? '-' }}</td>
                <td>{{ optional($doc->office)->name ?? '-' }}</td>
                <td style="font-size:12px;">{{ $doc->created_at->format('M d, Y') }}</td>
                <td>
                    <div style="display:flex;gap:4px;">
                    @if($doc->status === 'pending' && $doc->recipient_id === $user->id)
                        <form method="POST" action="{{ route('dts.documents.receive', $doc) }}">
                            @csrf
                            <button type="submit" class="btn btn-xs" style="background:var(--primary);color:#fff;border:none;">Receive</button>
                        </form>
                    @endif
                    <a href="{{ route('dts.documents.show', $doc) }}" class="btn btn-outline btn-xs">View</a>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="margin-top:16px;">
        {{ $documents->links('vendor.pagination.default') }}
    </div>
    @endif
</div>
@endsection
