@extends('dts.layouts.app')

@section('title', 'Create Document')

@section('content')
<div class="page-header">
    <h1>New Document</h1>
    <div style="display:flex;gap:8px;align-items:center;">
        <a href="{{ route('dts.index') }}" class="btn btn-outline btn-sm">&larr; Dashboard</a>
        <a href="{{ route('dts.documents') }}" class="btn btn-outline btn-sm">Back to List</a>
    </div>
</div>

<div class="card" style="max-width:700px;">
    <form method="POST" action="{{ route('dts.documents.store') }}">
        @csrf

        <div class="form-group">
            <label>Tracking Number</label>
            <input type="text" class="form-control" value="{{ $trackingNumber }}" disabled>
            <small style="color:var(--gray-500);">Auto-generated</small>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="type">Type</label>
                <select id="type" name="type" class="form-control" required onchange="toggleTypeFields()">
                    <option value="incoming" {{ old('type') === 'incoming' ? 'selected' : '' }}>Incoming</option>
                    <option value="outgoing" {{ old('type', 'outgoing') === 'outgoing' ? 'selected' : '' }}>Outgoing</option>
                </select>
            </div>
            <div class="form-group">
                <label for="communication_type">Type of Communication</label>
                <select id="communication_type" name="communication_type" class="form-control" required>
                    <option value="internal" {{ old('communication_type', 'internal') === 'internal' ? 'selected' : '' }}>Internal</option>
                    <option value="external" {{ old('communication_type') === 'external' ? 'selected' : '' }}>External</option>
                </select>
            </div>
            <div class="form-group">
                <label for="priority">Priority</label>
                <select id="priority" name="priority" class="form-control" required>
                    <option value="low" {{ old('priority') === 'low' ? 'selected' : '' }}>Low</option>
                    <option value="normal" {{ old('priority', 'normal') === 'normal' ? 'selected' : '' }}>Normal</option>
                    <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>High</option>
                    <option value="urgent" {{ old('priority') === 'urgent' ? 'selected' : '' }}>Urgent</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="title">Title/Subject/Description</label>
            <input type="text" id="title" name="title" class="form-control" value="{{ old('title') }}" required placeholder="Document title">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="category">Category</label>
                <select id="category" name="category" class="form-control">
                    <option value="">-- Select --</option>
                    <option value="Memo" {{ old('category') === 'Memo' ? 'selected' : '' }}>Memo</option>
                    <option value="Letter" {{ old('category') === 'Letter' ? 'selected' : '' }}>Letter</option>
                    <option value="Report" {{ old('category') === 'Report' ? 'selected' : '' }}>Report</option>
                    <option value="Purchase Request" {{ old('category') === 'Purchase Request' ? 'selected' : '' }}>Purchase Request</option>
                    <option value="Travel Order" {{ old('category') === 'Travel Order' ? 'selected' : '' }}>Travel Order</option>
                    <option value="Special Order" {{ old('category') === 'Special Order' ? 'selected' : '' }}>Special Order</option>
                    <option value="Office Order" {{ old('category') === 'Office Order' ? 'selected' : '' }}>Office Order</option>
                    <option value="OJT Agreement" {{ old('category') === 'OJT Agreement' ? 'selected' : '' }}>OJT Agreement</option>
                    <option value="Contract" {{ old('category') === 'Contract' ? 'selected' : '' }}>Contract</option>
                    <option value="Other" {{ old('category') === 'Other' ? 'selected' : '' }}>Other</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="action_requested">Action Requested</label>
                <select id="action_requested" name="action_requested" class="form-control">
                    <option value="">-- Select --</option>
                    @foreach(['Approval/Signature','Comments/Recommendation','Staff Action','Study/Review','Report Due','Rewrite/Redraft','Information/Notation','See Me/Call Me','Dispatch','Publish','File','Misrouted'] as $action)
                        <option value="{{ $action }}" {{ old('action_requested') === $action ? 'selected' : '' }}>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        </div>

        <div class="form-row" id="incomingFields" style="display:none;">
            <div class="form-group">
                <label for="date_received">Date Received</label>
                <input type="date" id="date_received" name="date_received" class="form-control" value="{{ old('date_received', date('Y-m-d')) }}">
            </div>
        </div>

        <div class="form-row" id="outgoingFields">
            <div style="flex:1;">
                <label style="font-weight:600;font-size:14px;margin-bottom:8px;display:block;">Recipient</label>
                <div class="form-row">
                    <div class="form-group">
                        <label for="recip_office_id">Office</label>
                        <select id="recip_office_id" class="form-control" onchange="loadRecipientSections(this.value)">
                            <option value="">-- Select Office --</option>
                            @foreach($offices as $office)
                                <option value="{{ $office->id }}" {{ old('recip_office_id') == $office->id ? 'selected' : '' }}>{{ $office->name }}</option>
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
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="remarks">Remarks / Description</label>
            <textarea id="remarks" name="remarks" class="form-control" rows="4" placeholder="Additional notes or document description...">{{ old('remarks') }}</textarea>
        </div>

        <div style="display:flex;gap:8px;">
            <button type="submit" class="btn btn-primary">Register Document</button>
            <a href="{{ route('dts.documents') }}" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
function toggleTypeFields() {
    var type = document.getElementById('type').value;
    document.getElementById('incomingFields').style.display = type === 'incoming' ? 'flex' : 'none';
    document.getElementById('outgoingFields').style.display = type === 'outgoing' ? 'flex' : 'none';
}
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
                selSec.innerHTML += '<option value="' + id + '">' + data[id] + '</option>';
            }
        });
    fetch('/dts/users-by-office/' + officeId)
        .then(r => r.json())
        .then(data => {
            for (var u of data) {
                selUser.innerHTML += '<option value="' + u.id + '">' + u.name + '</option>';
            }
        });
}
function loadRecipientUsers() {
    var officeId = document.getElementById('recip_office_id').value;
    var sectionId = document.getElementById('recip_section_id').value;
    var selUser = document.getElementById('recipient_id');
    selUser.innerHTML = '<option value="">Loading...</option>';
    if (!sectionId) {
        if (officeId) {
            fetch('/dts/users-by-office/' + officeId)
                .then(r => r.json())
                .then(data => {
                    selUser.innerHTML = '<option value="">-- Select User --</option>';
                    for (var u of data) {
                        selUser.innerHTML += '<option value="' + u.id + '">' + u.name + '</option>';
                    }
                });
        } else {
            selUser.innerHTML = '<option value="">-- Select User --</option>';
        }
        return;
    }
    fetch('/dts/users-by-section/' + sectionId)
        .then(r => r.json())
        .then(data => {
            selUser.innerHTML = '<option value="">-- Select User --</option>';
            for (var u of data) {
                selUser.innerHTML += '<option value="' + u.id + '">' + u.name + '</option>';
            }
        })
        .catch(() => { selUser.innerHTML = '<option value="">-- Select User --</option>'; });
}
toggleTypeFields();
@if(old('recip_office_id'))
    loadRecipientSections('{{ old('recip_office_id') }}');
@endif
</script>
@endpush
