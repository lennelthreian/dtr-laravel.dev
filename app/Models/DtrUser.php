<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\LogsUserActivity;

class DtrUser extends Model
{
    use HasFactory;
    use LogsUserActivity;

    protected $fillable = [
        'emp_code', 'first_name', 'last_name', 'middle_name',
        'honorific_prefix', 'honorific_suffix',
        'position', 'sex', 'department', 'office', 'section', 'office_id', 'section_id',
        'employee_status', 'is_active', 'default_work_week',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function officeModel()
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function sectionModel()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'emp_code', 'emp_code');
    }

    public function getFullNameAttribute()
    {
        $user = User::where('emp_code', $this->emp_code)->first();
        if ($user && $user->last_name) {
            $middle = $user->middle_name ? ' ' . strtoupper(substr($user->middle_name, 0, 1)) . '.' : '';
            $name = trim($user->first_name . $middle . ' ' . $user->last_name);
            if ($name) {
                $prefix = $user->honorific_prefix ? $user->honorific_prefix . ' ' : '';
                $suffix = $user->honorific_suffix ? ', ' . $user->honorific_suffix : '';
                return $prefix . $name . $suffix;
            }
        }

        $middle = $this->middle_name ? ' ' . strtoupper(substr($this->middle_name, 0, 1)) . '.' : '';
        $name = trim($this->first_name . $middle . ' ' . $this->last_name);
        if ($name) {
            $prefix = $this->honorific_prefix ? $this->honorific_prefix . ' ' : '';
            $suffix = $this->honorific_suffix ? ', ' . $this->honorific_suffix : '';
            return $prefix . $name . $suffix;
        }
        return 'Employee #' . $this->emp_code;
    }
}
