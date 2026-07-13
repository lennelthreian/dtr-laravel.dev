<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatThread extends Model
{
    protected $fillable = ['user_one_id', 'user_two_id'];

    public function userOne()
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo()
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages()
    {
        return $this->hasMany(ChatMessage::class, 'thread_id');
    }

    public function lastMessage()
    {
        return $this->hasOne(ChatMessage::class, 'thread_id')->latestOfMany();
    }

    public function otherUser($userId)
    {
        return $this->user_one_id == $userId ? $this->userTwo : $this->userOne;
    }

    public function unreadCount($userId)
    {
        return $this->messages()
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public static function findOrCreate($userOneId, $userTwoId)
    {
        if ($userOneId > $userTwoId) {
            [$userOneId, $userTwoId] = [$userTwoId, $userOneId];
        }

        return static::firstOrCreate([
            'user_one_id' => $userOneId,
            'user_two_id' => $userTwoId,
        ]);
    }

    public function closures()
    {
        return $this->hasMany(ChatThreadClosure::class, 'thread_id');
    }

    public function isClosedFor($userId)
    {
        return $this->closures()->where('user_id', $userId)->exists();
    }

    public function closeFor($userId)
    {
        return $this->closures()->firstOrCreate(['user_id' => $userId]);
    }

    public function openFor($userId)
    {
        return $this->closures()->where('user_id', $userId)->delete();
    }
}
