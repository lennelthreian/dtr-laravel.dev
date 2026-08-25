<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('portal');
})->name('portal');

Route::middleware('guest')->group(function () {
    Route::get('/register', [App\Http\Controllers\Auth\RegisterController::class, 'showRegistrationForm'])
        ->name('register');
    Route::post('/register', [App\Http\Controllers\Auth\RegisterController::class, 'register']);
    Route::get('/login', [App\Http\Controllers\Auth\LoginController::class, 'showLoginForm'])
        ->name('login');
    Route::post('/login', [App\Http\Controllers\Auth\LoginController::class, 'login']);
    Route::get('/forgot-password', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'showForm'])
        ->name('password.request');
    Route::post('/forgot-password', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'store'])
        ->name('password.request.store');
    Route::get('/forgot-password/submitted', [App\Http\Controllers\Auth\ForgotPasswordController::class, 'submitted'])
        ->name('password.request.submitted');
});

Route::post('/logout', [App\Http\Controllers\Auth\LogoutController::class, 'store'])
    ->name('logout');

Route::get('/sections-by-office/{office}', function (App\Models\Office $office) {
    return $office->sections()->orderBy('name')->pluck('name', 'id');
})->name('sections-by-office');

Route::middleware('auth')->group(function () {
    Route::get('/dtr', [App\Http\Controllers\DtrController::class, 'index'])
        ->name('dtr.index');
    Route::get('/dtr/dashboard', [App\Http\Controllers\DtrController::class, 'dashboard'])
        ->name('dtr.dashboard');
    Route::get('/dtr/show', [App\Http\Controllers\DtrController::class, 'show'])
        ->name('dtr.show');
    Route::get('/dtr/print-all', [App\Http\Controllers\DtrController::class, 'printAll'])
        ->name('dtr.print-all');

    Route::get('/dtr/edit-request', function () {
        return redirect()->route('dtr.index');
    });
    Route::post('/dtr/edit-request', [App\Http\Controllers\DtrEditRequestController::class, 'store'])
        ->name('dtr.edit-request.store');
    Route::post('/dtr/edit-request/{edit_request}/approve', [App\Http\Controllers\DtrEditRequestController::class, 'approve'])
        ->name('dtr.edit-request.approve');
    Route::post('/dtr/edit-request/{edit_request}/reject', [App\Http\Controllers\DtrEditRequestController::class, 'reject'])
        ->name('dtr.edit-request.reject');
    Route::post('/dtr/edit-request/{edit_request}/request-deletion', [App\Http\Controllers\DtrEditRequestController::class, 'requestDeletion'])
        ->name('dtr.edit-request.request-deletion');
    Route::delete('/dtr/edit-request/{edit_request}', [App\Http\Controllers\DtrEditRequestController::class, 'destroy'])
        ->name('dtr.edit-request.destroy');
    Route::post('/dtr/edit-requests/batch-approve', [App\Http\Controllers\DtrEditRequestController::class, 'batchApprove'])
        ->name('dtr.edit-requests.batch-approve');

    Route::post('/dtr/toggle-share', [App\Http\Controllers\DtrController::class, 'toggleShare'])
        ->name('dtr.toggle-share');
    Route::post('/dtr/toggle-work-week', [App\Http\Controllers\DtrController::class, 'toggleWorkWeek'])
        ->name('dtr.toggle-work-week');
    Route::post('/dtr/toggle-day-work-week', [App\Http\Controllers\DtrController::class, 'toggleDayWorkWeek'])
        ->name('dtr.toggle-day-work-week');

    Route::post('/notifications/mark-read', [App\Http\Controllers\DtrEditRequestController::class, 'markAsRead'])
        ->name('notifications.mark-read');
    Route::post('/notifications/{notification}/mark-single-read', [App\Http\Controllers\DtrEditRequestController::class, 'markSingleAsRead'])
        ->name('notifications.mark-single-read');

    Route::get('/supervisor/pending', [App\Http\Controllers\SupervisorController::class, 'pending'])
        ->name('supervisor.pending');

    Route::get('/profile', [App\Http\Controllers\Auth\ProfileController::class, 'showProfileForm'])
        ->name('profile');
    Route::post('/profile', [App\Http\Controllers\Auth\ProfileController::class, 'updateProfile'])
        ->name('profile.update');
    Route::get('/password', [App\Http\Controllers\Auth\ProfileController::class, 'showPasswordForm'])
        ->name('password.form');
    Route::post('/password', [App\Http\Controllers\Auth\ProfileController::class, 'updatePassword'])
        ->name('password.update');
});

Route::middleware(['auth', 'super'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [App\Http\Controllers\AdminController::class, 'dashboard'])
        ->name('dashboard');
    Route::get('/offices', [App\Http\Controllers\AdminController::class, 'offices'])
        ->name('offices');
    Route::post('/offices', [App\Http\Controllers\AdminController::class, 'storeOffice'])
        ->name('offices.store');
    Route::delete('/offices/{office}', [App\Http\Controllers\AdminController::class, 'deleteOffice'])
        ->name('offices.delete');
    Route::post('/offices/{office}/assign-supervisor', [App\Http\Controllers\AdminController::class, 'assignOfficeSupervisor'])
        ->name('offices.assign-supervisor');
    Route::post('/offices/{office}/assign-senior-manager', [App\Http\Controllers\AdminController::class, 'assignSeniorManager'])
        ->name('offices.assign-senior-manager');
    Route::post('/offices/{office}/assign-oic', [App\Http\Controllers\AdminController::class, 'assignOic'])
        ->name('offices.assign-oic');
    Route::post('/offices/{office}/assign-senior-manager-oic', [App\Http\Controllers\AdminController::class, 'assignSeniorManagerOic'])
        ->name('offices.assign-senior-manager-oic');
    Route::get('/sections', [App\Http\Controllers\AdminController::class, 'sections'])
        ->name('sections');
    Route::post('/sections', [App\Http\Controllers\AdminController::class, 'storeSection'])
        ->name('sections.store');
    Route::delete('/sections/{section}', [App\Http\Controllers\AdminController::class, 'deleteSection'])
        ->name('sections.delete');
    Route::post('/sections/{section}/assign-supervisor', [App\Http\Controllers\AdminController::class, 'assignSectionSupervisor'])
        ->name('sections.assign-supervisor');
    Route::post('/sections/{section}/assign-oic', [App\Http\Controllers\AdminController::class, 'assignSectionOic'])
        ->name('sections.assign-oic');
    Route::get('/employees', [App\Http\Controllers\AdminController::class, 'employees'])
        ->name('employees');
    Route::post('/employees/{employee}/assign', [App\Http\Controllers\AdminController::class, 'assignEmployee'])
        ->name('employees.assign');
    Route::post('/employees/{employee}/reset-password', [App\Http\Controllers\AdminController::class, 'resetPassword'])
        ->name('employees.reset-password');
    Route::get('/settings', [App\Http\Controllers\AdminController::class, 'settings'])
        ->name('settings');
    Route::post('/settings', [App\Http\Controllers\AdminController::class, 'updateSettings'])
        ->name('settings.update');
    Route::post('/settings/backup', [App\Http\Controllers\AdminController::class, 'runBackup'])
        ->name('settings.backup');
    Route::get('/holidays', [App\Http\Controllers\AdminController::class, 'holidays'])
        ->name('holidays');
    Route::post('/holidays', [App\Http\Controllers\AdminController::class, 'storeHoliday'])
        ->name('holidays.store');
    Route::delete('/holidays/{holiday}', [App\Http\Controllers\AdminController::class, 'deleteHoliday'])
        ->name('holidays.delete');
    Route::get('/work-arrangement', [App\Http\Controllers\AdminController::class, 'workArrangement'])
        ->name('work-arrangement');
    Route::post('/work-arrangement/global', [App\Http\Controllers\AdminController::class, 'updateGlobalWorkWeek'])
        ->name('work-arrangement.global');
    Route::post('/work-arrangement/employee/{employee}', [App\Http\Controllers\AdminController::class, 'updateEmployeeWorkWeek'])
        ->name('work-arrangement.employee');
    Route::get('/logs', [App\Http\Controllers\AdminController::class, 'logs'])
        ->name('logs');
    Route::get('/password-reset-requests', [App\Http\Controllers\AdminController::class, 'passwordResetRequests'])
        ->name('password-reset-requests');
    Route::post('/password-reset-requests/{resetRequest}/reset', [App\Http\Controllers\AdminController::class, 'approvePasswordReset'])
        ->name('password-reset-requests.reset');
    Route::get('/monitoring', [App\Http\Controllers\AdminController::class, 'monitoring'])
        ->name('monitoring');
    Route::get('/users', [App\Http\Controllers\AdminController::class, 'users'])
        ->name('users');
    Route::post('/users/{user}/toggle-super', [App\Http\Controllers\AdminController::class, 'toggleSuper'])
        ->name('users.toggle-super');
    Route::post('/users/{user}/toggle-coa', [App\Http\Controllers\AdminController::class, 'toggleCoa'])
        ->name('users.toggle-coa');
    Route::post('/users/{user}/reset-password', [App\Http\Controllers\AdminController::class, 'resetUserPassword'])
        ->name('users.reset-password');
    Route::post('/users/{user}/delete', [App\Http\Controllers\AdminController::class, 'deleteUser'])
        ->name('users.delete');
    Route::post('/users/{user}/deactivate', [App\Http\Controllers\AdminController::class, 'deactivateUser'])
        ->name('users.deactivate');
    Route::post('/users/{user}/activate', [App\Http\Controllers\AdminController::class, 'activateUser'])
        ->name('users.activate');
    Route::post('/monitoring/issue-memo', [App\Http\Controllers\AdminController::class, 'issueMemo'])
        ->name('issue-memo');
    Route::get('/coa-shares', [App\Http\Controllers\AdminController::class, 'coaShares'])
        ->name('coa-shares');
});
