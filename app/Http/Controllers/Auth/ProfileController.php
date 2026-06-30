<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\DtrUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function showProfileForm()
    {
        $user = auth()->user();
        return view('auth.profile', compact('user'));
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'honorific_prefix' => ['nullable', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'honorific_suffix' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'position' => ['required', 'string', 'max:255'],
            'sex' => ['required', 'in:Male,Female'],
        ]);

        $middle = $data['middle_name'] ? ' ' . $data['middle_name'] . ' ' : ' ';
        $nameBase = trim($data['first_name'] . $middle . $data['last_name']);
        $prefix = $data['honorific_prefix'] ? $data['honorific_prefix'] . ' ' : '';
        $suffix = $data['honorific_suffix'] ? ', ' . $data['honorific_suffix'] : '';
        $data['name'] = $prefix . $nameBase . $suffix;

        $user->update($data);

        DtrUser::where('emp_code', $user->emp_code)->update([
            'first_name' => $data['first_name'],
            'middle_name' => $data['middle_name'],
            'last_name' => $data['last_name'],
            'honorific_prefix' => $data['honorific_prefix'],
            'honorific_suffix' => $data['honorific_suffix'],
            'position' => $data['position'],
            'sex' => $data['sex'],
        ]);

        return redirect()->route('profile')->with('success', 'Profile updated successfully.');
    }

    public function showPasswordForm()
    {
        return view('auth.password');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('password.form')->with('success', 'Password changed successfully.');
    }
}
