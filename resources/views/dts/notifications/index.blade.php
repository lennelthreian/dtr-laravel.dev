@extends('dts.layouts.app')

@section('title', 'Notifications')

@section('content')
<div class="page-header">
    <h1>Notifications</h1>
    @if($unreadCount > 0)
    <form method="POST" action="{{ route('dts.notifications.mark-all-read') }}">
        @csrf
        <button type="submit" class="btn btn-outline btn-sm">Mark All as Read</button>
    </form>
    @endif
</div>

<div class="card">
    @if($notifications->isEmpty())
        <p style="text-align:center;color:var(--gray-500);padding:32px;">No notifications.</p>
    @else
        @foreach($notifications as $notif)
        <a href="{{ route('dts.notifications.read', $notif->id) }}" style="display:flex;align-items:flex-start;gap:12px;padding:12px 16px;text-decoration:none;color:inherit;border-bottom:1px solid var(--gray-100);transition:background 0.15s;{{ !$notif->is_read ? 'background:var(--gray-50);' : '' }}">
            <div style="flex-shrink:0;width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;{{ !$notif->is_read ? 'background:#e3f2fd;' : 'background:var(--gray-100);' }}">
                @if($notif->type === 'forwarded') &#10148;
                @elseif($notif->type === 'received') &#10003;
                @elseif($notif->type === 'processed') &#9889;
                @elseif($notif->type === 'new_document') &#128196;
                @else &#128276;
                @endif
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:13px;{{ !$notif->is_read ? 'font-weight:600;' : '' }}">{{ $notif->message }}</div>
                <div style="font-size:11px;color:var(--gray-500);margin-top:3px;">{{ $notif->created_at->diffForHumans() }}</div>
            </div>
            @if(!$notif->is_read)
                <div style="flex-shrink:0;width:8px;height:8px;border-radius:50%;background:var(--accent);margin-top:6px;"></div>
            @endif
        </a>
        @endforeach
        <div style="margin-top:16px;">
            {{ $notifications->links('vendor.pagination.default') }}
        </div>
    @endif
</div>
@endsection
