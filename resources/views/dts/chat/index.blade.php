@extends('dts.layouts.app')

@section('title', 'Chat')

@section('styles')
<style>
.chat-layout { display:flex; gap:0; height:calc(100vh - 120px); background:var(--white); border-radius:var(--radius-lg); overflow:hidden; box-shadow:var(--shadow-sm); border:1px solid var(--gray-200); }
.chat-sidebar { width:300px; flex-shrink:0; border-right:1px solid var(--gray-200); display:flex; flex-direction:column; }
.chat-sidebar-header { padding:12px; border-bottom:1px solid var(--gray-200); }
.chat-sidebar-header input { width:100%; padding:8px 12px; border:1px solid var(--gray-200); border-radius:var(--radius-md); font-size:13px; background:var(--gray-50); outline:none; }
.chat-sidebar-header input:focus { border-color:var(--primary); }
.chat-user-search { display:none; max-height:200px; overflow-y:auto; border-bottom:1px solid var(--gray-200); }
.chat-user-search.active { display:block; }
.chat-user-item { display:flex; align-items:center; gap:10px; padding:10px 12px; cursor:pointer; font-size:13px; transition:background 0.15s; }
.chat-user-item:hover { background:var(--gray-50); }
.chat-thread-list { flex:1; overflow-y:auto; }
.chat-thread-item { display:flex; align-items:center; gap:10px; padding:12px; cursor:pointer; border-bottom:1px solid var(--gray-100); transition:background 0.15s; }
.chat-thread-item:hover { background:var(--gray-50); }
.chat-thread-item.active { background:var(--primary); color:var(--white); }
.chat-thread-item.active .chat-thread-time,
.chat-thread-item.active .chat-thread-preview { color:rgba(255,255,255,0.8); }
.chat-thread-info { flex:1; min-width:0; }
.chat-thread-name { font-size:13px; font-weight:600; margin-bottom:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.chat-thread-preview { font-size:11px; color:var(--gray-500); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.chat-thread-meta { text-align:right; flex-shrink:0; }
.chat-thread-time { font-size:10px; color:var(--gray-500); margin-bottom:4px; }
.chat-thread-badge { display:inline-block; background:var(--primary); color:var(--white); font-size:10px; font-weight:700; padding:1px 6px; border-radius:10px; min-width:18px; text-align:center; }
.chat-thread-item.active .chat-thread-badge { background:var(--white); color:var(--primary); }
.chat-main { flex:1; display:flex; flex-direction:column; min-width:0; }
.chat-main-header { padding:14px 16px; border-bottom:1px solid var(--gray-200); font-weight:600; font-size:14px; display:flex; align-items:center; gap:8px; }
.chat-messages { flex:1; overflow-y:auto; padding:16px; display:flex; flex-direction:column; gap:6px; }
.chat-empty { flex:1; display:flex; align-items:center; justify-content:center; color:var(--gray-500); font-size:14px; }
.chat-bubble { max-width:70%; padding:10px 14px; border-radius:var(--radius-lg); font-size:13px; line-height:1.5; word-wrap:break-word; }
.chat-bubble.sent { align-self:flex-end; background:var(--primary); color:var(--white); border-bottom-right-radius:4px; }
.chat-bubble.received { align-self:flex-start; background:var(--gray-100); color:var(--gray-800); border-bottom-left-radius:4px; }
.chat-bubble-sender { font-size:11px; font-weight:600; margin-bottom:2px; opacity:0.8; }
.chat-bubble-time { font-size:10px; opacity:0.6; margin-top:4px; text-align:right; }
.chat-input-bar { padding:12px 16px; border-top:1px solid var(--gray-200); display:flex; gap:8px; align-items:flex-end; }
.chat-input-bar textarea { flex:1; padding:10px 12px; border:1px solid var(--gray-200); border-radius:var(--radius-md); font-size:13px; resize:none; outline:none; font-family:inherit; max-height:100px; min-height:40px; }
.chat-input-bar textarea:focus { border-color:var(--primary); }
.chat-input-bar button { padding:10px 20px; background:var(--primary); color:var(--white); border:none; border-radius:var(--radius-md); font-size:13px; font-weight:600; cursor:pointer; white-space:nowrap; }
.chat-input-bar button:hover { opacity:0.9; }
.chat-input-bar button:disabled { opacity:0.5; cursor:not-allowed; }
</style>
@endsection

@section('content')
<div class="chat-layout">
    <div class="chat-sidebar">
        <div class="chat-sidebar-header">
            <input type="text" id="userSearch" placeholder="Search users to chat..." autocomplete="off">
        </div>
        <div class="chat-user-search" id="userSearchResults"></div>
        <div class="chat-thread-list" id="threadList">
            <div style="padding:24px;text-align:center;color:var(--gray-500);font-size:13px;">Loading conversations...</div>
        </div>
    </div>
    <div class="chat-main">
        <div class="chat-main-header" id="chatHeader">
            <span style="color:var(--gray-500);">Select a conversation to start chatting</span>
        </div>
        <div class="chat-messages" id="chatMessages" style="display:none;"></div>
        <div class="chat-input-bar" id="chatInput" style="display:none;">
            <textarea id="messageInput" rows="1" placeholder="Type a message..."></textarea>
            <button id="sendBtn" onclick="sendMessage()">Send</button>
        </div>
        <div class="chat-empty" id="chatEmpty">
            <div style="text-align:center;">
                <div style="font-size:32px;margin-bottom:8px;">&#128172;</div>
                <div>Select a conversation or search for a user to start chatting</div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
var currentThreadId = null;
var currentUserId = {{ auth()->id() }};
var lastMessageTimestamp = null;
var pollInterval = null;
var threadPollInterval = null;
var searchTimeout = null;

document.getElementById('userSearch').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    var q = this.value.trim();
    if (q.length < 1) {
        document.getElementById('userSearchResults').classList.remove('active');
        return;
    }
    searchTimeout = setTimeout(function() {
        fetch('{{ route("dts.chat.search-users") }}?q=' + encodeURIComponent(q))
            .then(function(r) { return r.json(); })
            .then(function(users) {
                var el = document.getElementById('userSearchResults');
                if (!users.length) {
                    el.innerHTML = '<div style="padding:12px;color:var(--gray-500);font-size:12px;text-align:center;">No users found</div>';
                } else {
                    el.innerHTML = users.map(function(u) {
                        return '<div class="chat-user-item" onclick="startChat(' + u.id + ')">' + escapeHtml(u.name) + '</div>';
                    }).join('');
                }
                el.classList.add('active');
            });
    }, 300);
});

function startChat(userId) {
    fetch('{{ route("dts.chat.threads.store") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ recipient_id: userId }),
    })
    .then(function(r) { return r.json(); })
    .then(function(thread) {
        document.getElementById('userSearch').value = '';
        document.getElementById('userSearchResults').classList.remove('active');
        openThread(thread.id, thread.other_user);
        loadThreads();
    });
}

function loadThreads() {
    fetch('{{ route("dts.chat.threads") }}')
        .then(function(r) { return r.json(); })
        .then(function(threads) {
            var el = document.getElementById('threadList');
            if (!threads.length) {
                el.innerHTML = '<div style="padding:24px;text-align:center;color:var(--gray-500);font-size:13px;">No conversations yet</div>';
                return;
            }
            el.innerHTML = threads.map(function(t) {
                var active = t.id === currentThreadId ? ' active' : '';
                var preview = t.last_message ? escapeHtml(t.last_message.body) : 'No messages yet';
                var time = t.last_message ? formatTime(t.last_message.created_at) : '';
                var badge = t.unread_count > 0 ? '<span class="chat-thread-badge">' + t.unread_count + '</span>' : '';
                return '<div class="chat-thread-item' + active + '" onclick="openThread(' + t.id + ', ' + JSON.stringify(t.other_user).replace(/"/g, '&quot;') + ')">' +
                    '<div class="chat-thread-info">' +
                        '<div class="chat-thread-name">' + escapeHtml(t.other_user.name) + '</div>' +
                        '<div class="chat-thread-preview">' + preview + '</div>' +
                    '</div>' +
                    '<div class="chat-thread-meta">' +
                        '<div class="chat-thread-time">' + time + '</div>' +
                        badge +
                    '</div>' +
                '</div>';
            }).join('');
        });
}

function openThread(threadId, otherUser) {
    currentThreadId = threadId;
    lastMessageTimestamp = null;

    document.getElementById('chatHeader').innerHTML = '<strong>' + escapeHtml(otherUser.name) + '</strong>';
    document.getElementById('chatMessages').style.display = 'flex';
    document.getElementById('chatMessages').innerHTML = '<div style="padding:24px;text-align:center;color:var(--gray-500);font-size:13px;">Loading messages...</div>';
    document.getElementById('chatInput').style.display = 'flex';
    document.getElementById('chatEmpty').style.display = 'none';

    loadMessages(true);
    loadThreads();

    clearInterval(pollInterval);
    pollInterval = setInterval(function() { loadMessages(false); }, 3000);
}

function loadMessages(initial) {
    if (!currentThreadId) return;
    var url = '/dts/chat/threads/' + currentThreadId + '/messages';
    if (!initial && lastMessageTimestamp) {
        url += '?since=' + encodeURIComponent(lastMessageTimestamp);
    }

    fetch(url)
        .then(function(r) { return r.json(); })
        .then(function(messages) {
            if (initial) {
                document.getElementById('chatMessages').innerHTML = '';
                if (!messages.length) {
                    document.getElementById('chatMessages').innerHTML = '<div class="chat-empty" style="flex:1;"><div style="text-align:center;color:var(--gray-500);">No messages yet. Say hello!</div></div>';
                    return;
                }
            }

            var container = document.getElementById('chatMessages');
            var shouldScroll = container.scrollTop + container.clientHeight >= container.scrollHeight - 60;

            messages.forEach(function(msg) {
                var isMine = msg.sender_id === currentUserId;
                var bubble = document.createElement('div');
                bubble.className = 'chat-bubble ' + (isMine ? 'sent' : 'received');
                var senderLine = isMine ? '' : '<div class="chat-bubble-sender">' + escapeHtml(msg.sender_name) + '</div>';
                bubble.innerHTML = senderLine +
                    escapeHtml(msg.body) +
                    '<div class="chat-bubble-time">' + formatTime(msg.created_at) + '</div>';
                container.appendChild(bubble);
                lastMessageTimestamp = msg.created_at;
            });

            if (shouldScroll || initial) {
                container.scrollTop = container.scrollHeight;
            }
        });
}

function sendMessage() {
    var input = document.getElementById('messageInput');
    var body = input.value.trim();
    if (!body || !currentThreadId) return;

    input.value = '';
    input.style.height = 'auto';

    fetch('/dts/chat/threads/' + currentThreadId + '/messages', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
        body: JSON.stringify({ body: body }),
    })
    .then(function(r) { return r.json(); })
    .then(function(msg) {
        var container = document.getElementById('chatMessages');
        var empty = container.querySelector('.chat-empty');
        if (empty) empty.remove();

        var bubble = document.createElement('div');
        bubble.className = 'chat-bubble sent';
        bubble.innerHTML = escapeHtml(msg.body) + '<div class="chat-bubble-time">' + formatTime(msg.created_at) + '</div>';
        container.appendChild(bubble);
        container.scrollTop = container.scrollHeight;
        lastMessageTimestamp = msg.created_at;
        loadThreads();
    });
}

document.getElementById('messageInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

document.getElementById('messageInput').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 100) + 'px';
});

document.addEventListener('click', function(e) {
    if (!e.target.closest('.chat-sidebar-header') && !e.target.closest('.chat-user-search')) {
        document.getElementById('userSearchResults').classList.remove('active');
    }
});

function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatTime(iso) {
    var d = new Date(iso);
    var now = new Date();
    var diffMs = now - d;
    var diffMin = Math.floor(diffMs / 60000);
    if (diffMin < 1) return 'now';
    if (diffMin < 60) return diffMin + 'm';
    var diffHr = Math.floor(diffMin / 60);
    if (diffHr < 24) return diffHr + 'h';
    return d.toLocaleDateString('en-PH', { month:'short', day:'numeric' });
}

loadThreads();
threadPollInterval = setInterval(loadThreads, 10000);
</script>
@endpush
