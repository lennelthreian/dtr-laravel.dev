<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DtsDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tracking_number', 'title', 'category', 'description', 'type', 'status', 'priority',
        'sender_id', 'recipient_id', 'office_id', 'section_id', 'created_by',
        'date_received', 'date_actioned', 'deadline', 'remarks',
    ];

    protected $casts = [
        'date_received' => 'date',
        'date_actioned' => 'date',
        'deadline' => 'date',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function office()
    {
        return $this->belongsTo(Office::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs()
    {
        return $this->hasMany(DtsDocumentLog::class, 'document_id');
    }
}
