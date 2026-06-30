<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'first_name', 'middle_name', 'last_name', 'honorific_prefix', 'honorific_suffix',
        'emp_code', 'username',
        'office', 'section', 'office_id', 'section_id', 'position', 'sex', 'email', 'password', 'is_super', 'is_coa', 'is_active',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_super' => 'boolean',
        'is_coa' => 'boolean',
        'is_active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function dtrUser()
    {
        return $this->belongsTo(DtrUser::class, 'emp_code', 'emp_code');
    }

    public function dtsDocuments()
    {
        return $this->hasMany(DtsDocument::class, 'user_id');
    }
}
