<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ChatController extends Controller
{
    public function index()
    {
        return view('dts.chat.index');
    }

    public function threads(Request $request)
    {
        $userId = auth()->id();

        $threads = ChatThread::where('user_one_id', $userId)
            ->orWhere('user_two_id', $userId)
            ->with('lastMessage.sender')
            ->get()
            ->map(function ($thread) use ($userId) {
                $other = $thread->otherUser($userId);
                return [
                    'id' => $thread->id,
                    'other_user' => [
                        'id' => $other->id,
                        'name' => $other->name,
                    ],
                    'last_message' => $thread->lastMessage ? [
                        'body' => Str::limit($thread->lastMessage->body, 50),
                        'sender_id' => $thread->lastMessage->sender_id,
                        'created_at' => $thread->lastMessage->created_at->toIso8601String(),
                    ] : null,
                    'unread_count' => $thread->unreadCount($userId),
                    'is_closed' => $thread->isClosedFor($userId),
                ];
            })
            ->sortByDesc(function ($item) {
                return $item['last_message']['created_at'] ?? '';
            })
            ->values();

        $open = $threads->where('is_closed', false)->values();
        $closed = $threads->where('is_closed', true)->values();

        return response()->json(['open' => $open, 'closed' => $closed]);
    }

    public function searchUsers(Request $request)
    {
        $query = $request->input('q', '');
        $userId = auth()->id();

        if (strlen($query) < 1) {
            return response()->json([]);
        }

        $users = User::where('id', '!=', $userId)
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('first_name', 'like', "%{$query}%")
                  ->orWhere('last_name', 'like', "%{$query}%")
                  ->orWhere('username', 'like', "%{$query}%");
            })
            ->limit(10)
            ->get(['id', 'name']);

        return response()->json($users);
    }

    public function storeThread(Request $request)
    {
        $request->validate([
            'recipient_id' => 'required|exists:users,id',
        ]);

        $userId = auth()->id();
        $recipientId = $request->recipient_id;

        if ($userId == $recipientId) {
            return response()->json(['error' => 'Cannot chat with yourself.'], 422);
        }

        $thread = ChatThread::findOrCreate($userId, $recipientId);

        $other = $thread->otherUser($userId);

        return response()->json([
            'id' => $thread->id,
            'other_user' => [
                'id' => $other->id,
                'name' => $other->name,
            ],
        ]);
    }

    public function messages(ChatThread $thread, Request $request)
    {
        $userId = auth()->id();

        if ($thread->user_one_id != $userId && $thread->user_two_id != $userId) {
            abort(403);
        }

        $query = $thread->messages()->with('sender');

        if ($request->filled('since')) {
            $query->where('created_at', '>', $request->since);
        }

        $messages = $query->orderBy('created_at', 'asc')
            ->limit(100)
            ->get()
            ->map(function ($msg) {
                return [
                    'id' => $msg->id,
                    'sender_id' => $msg->sender_id,
                    'sender_name' => $msg->sender->name,
                    'body' => $msg->body,
                    'read_at' => $msg->read_at,
                    'created_at' => $msg->created_at->toIso8601String(),
                ];
            });

        return response()->json($messages);
    }

    public function sendMessage(ChatThread $thread, Request $request)
    {
        $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $userId = auth()->id();

        if ($thread->user_one_id != $userId && $thread->user_two_id != $userId) {
            abort(403);
        }

        $message = $thread->messages()->create([
            'sender_id' => $userId,
            'body' => $request->body,
        ]);

        return response()->json([
            'id' => $message->id,
            'sender_id' => $message->sender_id,
            'sender_name' => auth()->user()->name,
            'body' => $message->body,
            'read_at' => null,
            'created_at' => $message->created_at->toIso8601String(),
        ]);
    }

    public function closeThread(ChatThread $thread)
    {
        $userId = auth()->id();

        if ($thread->user_one_id != $userId && $thread->user_two_id != $userId) {
            abort(403);
        }

        $thread->closeFor($userId);

        return response()->json(['status' => 'closed']);
    }

    public function openThread(ChatThread $thread)
    {
        $userId = auth()->id();

        if ($thread->user_one_id != $userId && $thread->user_two_id != $userId) {
            abort(403);
        }

        $thread->openFor($userId);

        return response()->json(['status' => 'opened']);
    }
}
