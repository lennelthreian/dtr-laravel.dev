@extends('dts.layouts.app')

@section('title', 'Edit ' . $document->tracking_number)

@section('content')
<div class="page-header">
    <h1>Edit Document #{{ $document->tracking_number }}</h1>
    <a href="{{ route('dts.documents.show', $document) }}" class="btn btn-outline btn-sm">Cancel</a>
</div>

<div class="card" style="max-width:700px;">
    <form method="POST" action="{{ route('dts.documents.update', $document) }}">
        @csrf
        @method('PUT')

        <div class="form-row">
            <div class="form-group">
                <label for="type">Type</label>
                <select id="type" name="type" class="form-control" required>
                    <option value="incoming" {{ $document->type === 'incoming' ? 'selected' : '' }}>Incoming</option>
                    <option value="outgoing" {{ $document->type === 'outgoing' ? 'selected' : '' }}>Outgoing</option>
                </select>
            </div>
            <div class="form-group">
                <label for="communication_type">Type of Communication</label>
                <select id="communication_type" name="communication_type" class="form-control" required>
                    <option value="internal" {{ old('communication_type', $document->communication_type) === 'internal' ? 'selected' : '' }}>Internal</option>
                    <option value="external" {{ old('communication_type', $document->communication_type) === 'external' ? 'selected' : '' }}>External</option>
                </select>
            </div>
            <div class="form-group">
                <label for="priority">Priority</label>
                <select id="priority" name="priority" class="form-control" required>
                    <option value="low" {{ $document->priority === 'low' ? 'selected' : '' }}>Low</option>
                    <option value="normal" {{ $document->priority === 'normal' ? 'selected' : '' }}>Normal</option>
                    <option value="high" {{ $document->priority === 'high' ? 'selected' : '' }}>High</option>
                    <option value="urgent" {{ $document->priority === 'urgent' ? 'selected' : '' }}>Urgent</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="title">Title/Subject/Description</label>
            <input type="text" id="title" name="title" class="form-control" value="{{ old('title', $document->title) }}" required>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="category">Category</label>
                <select id="category" name="category" class="form-control">
                    <option value="">-- Select --</option>
                    @foreach(['Memo','Letter','Report','Purchase Request','Travel Order','Special Order','Office Order','OJT Agreement','Contract','Other'] as $cat)
                        <option value="{{ $cat }}" {{ old('category', $document->category) === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="action_requested">Action Requested</label>
                <select id="action_requested" name="action_requested" class="form-control">
                    <option value="">-- Select --</option>
                    @foreach(['Approval/Signature','Comments/Recommendation','Staff Action','Study/Review','Report Due','Rewrite/Redraft','Information/Notation','See Me/Call Me','Dispatch','Publish','File','Misrouted'] as $action)
                        <option value="{{ $action }}" {{ old('action_requested', $document->action_requested) === $action ? 'selected' : '' }}>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div style="margin-bottom:16px;">
            <label style="font-weight:600;font-size:14px;margin-bottom:8px;display:block;">Recipient</label>
            <div class="form-row">
                <div class="form-group">
                    <label for="recip_office_id">Office</label>
                    <select id="recip_office_id" class="form-control" onchange="loadRecipientSections(this.value)">
                        <option value="">-- Select Office --</option>
                        @foreach($offices as $office)
                            <option value="{{ $office->id }}" {{ old('recip_office_id', $document->office_id) == $office->id ? 'selected' : '' }}>{{ $office->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label for="recip_section_id">Section</label>
                    <select id="recip_section_id" class="form-control" onchange="loadRecipientUsers()">
                        <option value="">-- Select Section --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="recipient_id">User</label>
                    <select id="recipient_id" name="recipient_id" class="form-control">
                        <option value="">-- Select User --</option>
                        @if($document->recipient_id)
                            <option value="{{ $document->recipient_id }}" selected>{{ optional($document->recipient)->name ?? 'Unknown' }}</option>
                        @endif
                    </select>
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="date_received">Date Received</label>
                <input type="date" id="date_received" name="date_received" class="form-control" value="{{ old('date_received', $document->date_received ? $document->date_received->format('Y-m-d') : '') }}">
            </div>
            </div>
        </div>

        <div class="form-group">
            <label for="remarks">Remarks</label>
            <textarea id="remarks" name="remarks" class="form-control" rows="2">{{ old('remarks', $document->remarks) }}</textarea>
        </div>

        <div style="display:flex;gap:8px;">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="{{ route('dts.documents.show', $document) }}" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function loadRecipientSections(officeId) {
    var selSec = document.getElementById('recip_section_id');
    var selUser = document.getElementById('recipient_id');
    selSec.innerHTML = '<option value="">-- Select Section --</option>';
    selUser.innerHTML = '<option value="">-- Select User --</option>';
    if (!officeId) return;
    fetch('/dts/sections-by-office/' + officeId)
        .then(r => r.json())
        .then(data => {
            for (var id in data) {
                var selId = '{{ $document->section_id }}';
                var sel = (id == selId) ? ' selected' : '';
                selSec.innerHTML += '<option value="' + id + '"' + sel + '>' + data[id] + '</option>';
            }
        });
    fetch('/dts/users-by-office/' + officeId)
        .then(r => r.json())
        .then(data => {
            var curRecip = '{{ $document->recipient_id }}';
            for (var u of data) {
                var sel = (u.id == curRecip) ? ' selected' : '';
                selUser.innerHTML += '<option value="' + u.id + '"' + sel + '>' + u.name + '</option>';
            }
        });
}
function loadRecipientUsers() {
    var officeId = document.getElementById('recip_office_id').value;
    var sectionId = document.getElementById('recip_section_id').value;
    var selUser = document.getElementById('recipient_id');
    selUser.innerHTML = '<option value="">Loading...</option>';
    var url = sectionId ? '/dts/users-by-section/' + sectionId : (officeId ? '/dts/users-by-office/' + officeId : null);
    if (!url) { selUser.innerHTML = '<option value="">-- Select User --</option>'; return; }
    fetch(url)
        .then(r => r.json())
        .then(data => {
            selUser.innerHTML = '<option value="">-- Select User --</option>';
            var curRecip = '{{ $document->recipient_id }}';
            for (var u of data) {
                var sel = (u.id == curRecip) ? ' selected' : '';
                selUser.innerHTML += '<option value="' + u.id + '"' + sel + '>' + u.name + '</option>';
            }
        })
        .catch(() => { selUser.innerHTML = '<option value="">-- Select User --</option>'; });
}
@if($document->office_id)
    loadRecipientSections('{{ $document->office_id }}');
@endif
</script>
@endpush
