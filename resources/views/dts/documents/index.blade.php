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
        @if($super)
        <div class="form-group" style="min-width:140px;margin-bottom:0;">
            <label for="office_id" style="font-size:12px;">Office</label>
            <select id="office_id" name="office_id" class="form-control">
                <option value="">All Offices</option>
                @foreach($offices as $office)
                    <option value="{{ $office->id }}" {{ request('office_id') == $office->id ? 'selected' : '' }}>{{ $office->name }}</option>
                @endforeach
            </select>
        </div>
        @endif
        <div style="display:flex;gap:8px;align-items:flex-end;">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <a href="{{ route('dts.documents', ['type' => $type]) }}" class="btn btn-outline btn-sm">Clear</a>
        </div>
    </form>
</div>

@if($super)
<div class="card" style="margin-bottom:16px;display:none;" id="bulkActionsCard">
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <span style="font-size:13px;font-weight:600;">Bulk Actions:</span>
        <span id="selectedCount" style="font-size:12px;color:var(--gray-600);">0 selected</span>
        <div style="display:flex;gap:8px;margin-left:auto;">
            <div style="display:flex;gap:4px;align-items:center;" id="bulkForwardSection" style="display:none;">
                <select id="bulkRecipient" class="form-control" style="width:200px;font-size:12px;">
                    <option value="">-- Forward to --</option>
                    @foreach(\App\Models\User::where('is_active', true)->orderBy('last_name')->get() as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
                <select id="bulkActionRequested" class="form-control" style="width:180px;font-size:12px;">
                    <option value="">-- Action --</option>
                    @foreach(['Approval/Signature','Comments/Recommendation','Staff Action','Study/Review','Report Due','Information/Notation'] as $action)
                        <option value="{{ $action }}">{{ $action }}</option>
                    @endforeach
                </select>
                <button type="button" class="btn btn-primary btn-sm" onclick="executeBulkAction('forward')">Forward Selected</button>
            </div>
            <button type="button" class="btn btn-accent btn-sm" onclick="executeBulkAction('process')">Process Selected</button>
            <button type="button" class="btn btn-danger btn-sm" onclick="executeBulkAction('archive')">Archive Selected</button>
        </div>
    </div>
</div>
@endif

<div class="card">
    @if($documents->isEmpty())
        <p style="text-align:center;color:var(--gray-500);padding:32px;">No documents found.</p>
    @else
        <table class="table" style="width:100%;">
            <thead>
                <tr>
                    @if($super)
                    <th style="width:30px;">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                    </th>
                    @endif
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
                    @if($super)
                    <td>
                        <input type="checkbox" name="document_ids[]" value="{{ $doc->id }}" class="doc-checkbox" onchange="updateSelectedCount()">
                    </td>
                    @endif
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

@if($super)
@push('scripts')
<script>
function toggleSelectAll(el) {
    var checkboxes = document.querySelectorAll('.doc-checkbox');
    checkboxes.forEach(function(cb) { cb.checked = el.checked; });
    updateSelectedCount();
}

function updateSelectedCount() {
    var checked = document.querySelectorAll('.doc-checkbox:checked').length;
    document.getElementById('selectedCount').textContent = checked + ' selected';
    var card = document.getElementById('bulkActionsCard');
    card.style.display = checked > 0 ? 'block' : 'none';
}

function executeBulkAction(action) {
    var checked = document.querySelectorAll('.doc-checkbox:checked');
    if (checked.length === 0) {
        alert('Please select at least one document.');
        return;
    }

    if (action === 'forward') {
        var recipient = document.getElementById('bulkRecipient').value;
        var actionReq = document.getElementById('bulkActionRequested').value;
        if (!recipient) {
            alert('Please select a recipient for forwarding.');
            return;
        }
    }

    if (!confirm('Are you sure you want to ' + action + ' ' + checked.length + ' document(s)?')) {
        return;
    }

    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route("dts.documents.bulk-action") }}';

    var csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value = '{{ csrf_token() }}';
    form.appendChild(csrf);

    var actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = action;
    form.appendChild(actionInput);

    if (action === 'forward') {
        var recipInput = document.createElement('input');
        recipInput.type = 'hidden';
        recipInput.name = 'recipient_id';
        recipInput.value = document.getElementById('bulkRecipient').value;
        form.appendChild(recipInput);

        var actReqInput = document.createElement('input');
        actReqInput.type = 'hidden';
        actReqInput.name = 'action_requested';
        actReqInput.value = document.getElementById('bulkActionRequested').value;
        form.appendChild(actReqInput);
    }

    checked.forEach(function(cb) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'document_ids[]';
        input.value = cb.value;
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
}
</script>
@endpush
@endif
