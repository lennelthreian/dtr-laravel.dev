<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\DtrUser;
use App\Models\Office;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest');
    }

    public function showRegistrationForm()
    {
        $offices = Office::orderBy('name')->get();
        return view('auth.register', compact('offices'));
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'emp_code' => ['required', 'string', 'max:20', 'unique:users'],
            'username' => ['required', 'string', 'max:50', 'unique:users'],
            'position' => ['required', 'string', 'max:255'],
            'sex' => ['required', 'in:Male,Female'],
            'office_id' => ['nullable', 'exists:offices,id'],
            'section_id' => ['nullable', 'exists:sections,id'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $officeName = $data['office_id'] ? Office::find($data['office_id'])->name : '';
        $sectionName = $data['section_id'] ? \App\Models\Section::find($data['section_id'])->name : '';

        $user = User::create([
            'name' => trim($data['first_name'] . ' ' . ($data['middle_name'] ? $data['middle_name'] . ' ' : '') . $data['last_name']),
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'],
            'last_name' => $data['last_name'],
            'emp_code' => $data['emp_code'],
            'username' => $data['username'],
            'position' => $data['position'],
            'sex' => $data['sex'],
            'office' => $officeName,
            'section' => $sectionName,
            'office_id' => $data['office_id'],
            'section_id' => $data['section_id'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        DtrUser::updateOrCreate(
            ['emp_code' => $data['emp_code']],
            [
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'],
                'last_name' => $data['last_name'],
                'position' => $data['position'],
                'sex' => $data['sex'],
                'department' => 'Department',
                'office_id' => $data['office_id'],
                'section_id' => $data['section_id'],
                'office' => $officeName,
                'section' => $sectionName,
                'employee_status' => 'Regular',
                'is_active' => true,
            ]
        );

        auth()->login($user);

        if (auth()->user()->is_super) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('dtr.dashboard');
    }
}
