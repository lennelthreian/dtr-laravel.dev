<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatThreadClosure extends Model
{
    public $timestamps = false;

    protected $fillable = ['thread_id', 'user_id'];

    public function thread()
    {
        return $this->belongsTo(ChatThread::class, 'thread_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
