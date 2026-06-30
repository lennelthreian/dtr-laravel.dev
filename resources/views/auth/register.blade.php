@extends('layouts.app')

@section('title', 'Register')

@section('content')
    <form method="POST" action="{{ route('register') }}">
        @csrf
        <div class="form-group">
            <label for="first-name">First Name</label>
            <input id="first-name" type="text" name="first_name" value="{{ old('first_name') }}" required autofocus placeholder="Enter your first name" class="form-control">
            @error('first_name')
                <div style="color:var(--danger); font-size:12px; margin-top:4px;">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-group">
            <label for="middle-name">Middle Name</label>
            <input id="middle-name" type="text" name="middle_name" value="{{ old('middle_name') }}" placeholder="Enter your middle name" class="form-control">
            @error('middle_name')
                <div style="color:var(--danger); font-size:12px; margin-top:4px;">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-group">
            <label for="last-name">Last Name</label>
            <input id="last-name" type="text" name="last_name" value="{{ old('last_name') }}" required placeholder="Enter your last name" class="form-control">
            @error('last_name')
                <div style="color:var(--danger); font-size:12px; margin-top:4px;">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-group">
            <label for="bio-id">Biometrics ID</label>
            <input id="bio-id" type="text" name="emp_code" value="{{ old('emp_code') }}" required placeholder="Enter Biometrics ID Provided by IT Staff" class="form-control">
            @error('emp_code')
                <div style="color:var(--danger); font-size:12px; margin-top:4px;">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-group">
            <label for="username">Username</label>
            <input id="username" type="text" name="username" value="{{ old('username') }}" required placeholder="Enter your username" class="form-control">
            @error('username')
                <div style="color:var(--danger); font-size:12px; margin-top:4px;">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-group">
            <label for="position">Position</label>
            <input id="position" type="text" name="position" value="{{ old('position') }}" required placeholder="e.g. PEO III" class="form-control">
            @error('position')
                <div style="color:var(--danger); font-size:12px; margin-top:4px;">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-group">
            <label for="sex">Sex</label>
            <select id="sex" name="sex" required class="form-control">
                <option value="">-- Select Sex --</option>
                <option value="Male" {{ old('sex') == 'Male' ? 'selected' : '' }}>Male</option>
                <option value="Female" {{ old('sex') == 'Female' ? 'selected' : '' }}>Female</option>
            </select>
            @error('sex')
                <div style="color:var(--danger); font-size:12px; margin-top:4px;">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-group">
            <label for="office">Office</label>
            <select id="office" name="office_id" required class="form-control">
                <option value="">-- Select Office --</option>
                @foreach ($offices as $office)
                    <option value="{{ $office->id }}" {{ old('office_id') == $office->id ? 'selected' : '' }}>{{ $office->name }}</option>
                @endforeach
            </select>
            @error('office_id')
                <div style="color:var(--danger); font-size:12px; margin-top:4px;">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-group">
            <label for="section">Section</label>
            <select id="section" name="section_id" required class="form-control">
                <option value="">-- Select Section --</option>
            </select>
            @error('section_id')
                <div style="color:var(--danger); font-size:12px; margin-top:4px;">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required placeholder="Enter your email address" class="form-control">
            @error('email')
                <div style="color:var(--danger); font-size:12px; margin-top:4px;">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" required placeholder="Enter your password" class="form-control">
            @error('password')
                <div style="color:var(--danger); font-size:12px; margin-top:4px;">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-group">
            <label for="password-confirm">Confirm Password</label>
            <input id="password-confirm" type="password" name="password_confirmation" required placeholder="Confirm your password" class="form-control">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;">Register</button>
        <div class="auth-link">
            Already have an account? <a href="{{ route('login') }}">Login</a>
        </div>
    </form>
@endsection

@push('scripts')
<script>
document.getElementById('office').addEventListener('change', function () {
    var officeId = this.value;
    var sectionSelect = document.getElementById('section');
    sectionSelect.innerHTML = '<option value="">-- Select Section --</option>';
    if (officeId) {
        fetch('{{ url('/sections-by-office') }}/' + officeId)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                for (var id in data) {
                    var opt = document.createElement('option');
                    opt.value = id;
                    opt.textContent = data[id];
                    if (opt.value == @json(old('section_id'))) opt.selected = true;
                    sectionSelect.appendChild(opt);
                }
            });
    }
});
@if (old('office_id'))
document.getElementById('office').dispatchEvent(new Event('change'));
@endif
</script>
@endpush
