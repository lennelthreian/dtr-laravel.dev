<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DtsNotification extends Model
{
    protected $fillable = [
        'user_id', 'document_id', 'type', 'message', 'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function document()
    {
        return $this->belongsTo(DtsDocument::class, 'document_id');
    }
}
