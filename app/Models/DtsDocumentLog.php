<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DtsDocumentLog extends Model
{
    protected $fillable = [
        'document_id', 'user_id', 'action', 'notes', 'action_requested', 'from_status', 'to_status',
    ];

    public function document()
    {
        return $this->belongsTo(DtsDocument::class, 'document_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
