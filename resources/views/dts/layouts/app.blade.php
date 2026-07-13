<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - MBLISTTDA Document Tracking System</title>
    <link rel="stylesheet" href="{{ asset('dtr.css') }}">
    <script>if(localStorage.getItem('theme')==='dark')document.documentElement.setAttribute('data-theme','dark');</script>
    <style>
        .dts-layout { --primary: #4A7C2E; --primary-light: #5E8F42; --primary-dark: #2E5E1A; --accent: #4A7C2E; --accent-light: #5E8F42; }
        .table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .table th { text-align: left; padding: 8px 10px; border-bottom: 2px solid var(--gray-200); color: var(--gray-600); font-weight: 600; font-size: 12px; white-space: nowrap; }
        .table td { padding: 10px 10px; border-bottom: 1px solid var(--gray-100); color: var(--gray-800); }
        .table tr:hover td { background: var(--gray-50); }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; background: var(--gray-100); color: var(--gray-700); }
        .badge-blue { background: #e3f2fd; color: #1565c0; }
        .badge-green { background: #e8f5e9; color: #2e7d32; }
        .detail-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .detail-table td { padding: 8px 10px; border-bottom: 1px solid var(--gray-100); }
        .detail-table td:first-child { font-weight: 600; color: var(--gray-600); width: 140px; white-space: nowrap; }
    </style>
    @yield('styles')
</head>
<body>
    @php $currentUser = auth()->user(); @endphp
    @php
        $unreadDtsNotifications = App\Models\DtsNotification::where('user_id', $currentUser->id)
            ->where('is_read', false)->count();
        $dtsNotifications = App\Models\DtsNotification::where('user_id', $currentUser->id)
            ->where('is_read', false)->latest()->take(10)->get();
    @endphp

    <div class="layout-sidebar dts-layout">
        <div class="sidebar no-print">
            <div class="sidebar-header" style="text-align:center;">
                @php $logo = App\Models\DtrSetting::getSettings()['logo_path'] ?? null; @endphp
                @if (!empty($logo))
                    <img src="{{ asset('storage/' . $logo) }}" alt="Logo" style="display:block;height:40px;margin:0 auto 8px;">
                @endif
                <h2 style="color:#fff;font-size:16px;margin:0 0 2px;">MBLISTTDA</h2>
                <p style="color:rgba(255,255,255,0.7);font-size:11px;">Document Tracking System</p>
                <p style="color:rgba(255,255,255,0.9);font-size:12px;margin-top:4px;">{{ $currentUser->name }}
                    @if($currentUser->is_super)
                        <span style="background:#FFD700;color:#333;font-size:9px;padding:1px 6px;border-radius:8px;font-weight:700;vertical-align:middle;margin-left:4px;">SUPER ADMIN</span>
                    @endif
                </p>
                @if($currentUser->office_id)
                    <p style="color:rgba(255,255,255,0.6);font-size:11px;">{{ optional($currentUser->office)->name }}{{ optional($currentUser->section)->name ? ' - ' . optional($currentUser->section)->name : '' }}</p>
                @endif
            </div>
            <nav class="sidebar-nav">
                <a href="{{ route('dts.index') }}" class="{{ request()->routeIs('dts.index') ? 'active' : '' }}">
                    Dashboard
                </a>
                <a href="{{ route('dts.documents', ['type' => 'all']) }}" class="{{ request()->routeIs('dts.documents*') && request('type', 'all') === 'all' ? 'active' : '' }}">
                    All Documents
                </a>
                <a href="{{ route('dts.documents', ['type' => 'incoming']) }}" class="{{ request('type') === 'incoming' ? 'active' : '' }}">
                    Incoming
                </a>
                <a href="{{ route('dts.documents', ['type' => 'outgoing']) }}" class="{{ request('type') === 'outgoing' ? 'active' : '' }}">
                    Outgoing
                </a>
                <a href="{{ route('dts.documents', ['type' => 'archived']) }}" class="{{ request('type') === 'archived' ? 'active' : '' }}">
                    Archived
                </a>
                <a href="{{ route('dts.documents.create') }}" class="{{ request()->routeIs('dts.documents.create') ? 'active' : '' }}">
                    + New Document
                </a>
                @if($currentUser->is_super)
                <a href="{{ route('dts.analytics') }}" class="{{ request()->routeIs('dts.analytics') ? 'active' : '' }}">
                    Analytics
                </a>
                @endif
            </nav>
            <div class="sidebar-footer" style="margin-top:16px;border-top:1px solid rgba(255,255,255,0.15);padding-top:12px;">
                <a href="{{ route('dtr.dashboard') }}" style="color:rgba(255,255,255,0.8);font-size:12px;text-decoration:none;display:block;padding:6px 0;">e-DTR System</a>
                <a href="{{ route('portal') }}" style="color:rgba(255,255,255,0.8);font-size:12px;text-decoration:none;display:block;padding:6px 0;">Portal</a>
                <button onclick="toggleTheme()" class="btn btn-sm" style="background:rgba(255,255,255,0.1);color:#fff;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;font-size:12px;width:100%;margin:12px 0 8px;">Toggle Theme</button>
                <a href="#" onclick="event.preventDefault();document.getElementById('logout-form').submit();" style="color:rgba(255,255,255,0.8);font-size:12px;text-decoration:none;">Logout</a>
            </div>
        </div>

        <div class="main-content">
            <div class="navbar no-print" style="margin-bottom:16px;">
                <div class="navbar-left">
                    <div id="dtsClock" style="font-size:13px;font-weight:600;color:var(--gray-700);"></div>
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <div class="notif-pos">
                        <button class="notif-btn" onclick="toggleNotif()">&#128276;
                            @if ($unreadDtsNotifications > 0)
                                <span class="notif-badge">{{ $unreadDtsNotifications }}</span>
                            @endif
                        </button>
                        <div id="notifDropdown" class="notif-dropdown">
                            <div class="notif-header">Notifications</div>
                            @forelse ($dtsNotifications as $notif)
                                <a href="{{ route('dts.notifications.read', $notif->id) }}" class="notif-item" data-notif-id="{{ $notif->id }}">
                                    <strong>{{ $notif->message }}</strong>
                                    <div class="notif-time">{{ $notif->created_at->diffForHumans() }}</div>
                                </a>
                            @empty
                                <div style="padding:24px;text-align:center;color:var(--gray-500);font-size:13px;">No new notifications</div>
                            @endforelse
                            @if ($unreadDtsNotifications > 0)
                                <div class="notif-footer">
                                    <form method="POST" action="{{ route('dts.notifications.mark-all-read') }}">
                                        @csrf
                                        <button type="submit" style="background:none;border:none;color:var(--accent);font-size:12px;font-weight:600;cursor:pointer;">Mark all as read</button>
                                    </form>
                                </div>
                            @endif
                            <div class="notif-footer" style="border-top:none;padding-top:0;">
                                <a href="{{ route('dts.notifications') }}" style="color:var(--gray-600);font-size:11px;">View all notifications</a>
                            </div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="logout-corner" style="margin:0;">
                        @csrf
                        <button class="btn btn-outline btn-sm">Logout</button>
                    </form>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger" style="margin-bottom:16px;">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            @yield('content')
        </div>
    </div>

    <script>
    function updateClock() {
        var now = new Date();
        var opts = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
        document.getElementById('dtsClock').textContent = now.toLocaleDateString('en-PH', opts);
    }
    updateClock();
    setInterval(updateClock, 1000);

    function toggleNotif() {
        var el = document.getElementById('notifDropdown');
        el.classList.toggle('active');
    }
    document.addEventListener('click', function(e) {
        var dd = document.getElementById('notifDropdown');
        if (dd && dd.classList.contains('active') && !e.target.closest('.notif-pos')) {
            dd.classList.remove('active');
        }
    });
    </script>

    <form id="logout-form" method="POST" action="{{ route('logout') }}" style="display:none;">
        @csrf
    </form>

    <script>
    function toggleTheme() {
        var el = document.documentElement;
        if (el.getAttribute('data-theme') === 'dark') {
            el.removeAttribute('data-theme');
            localStorage.setItem('theme', 'light');
        } else {
            el.setAttribute('data-theme', 'dark');
            localStorage.setItem('theme', 'dark');
        }
    }
    </script>
    @stack('scripts')

    <!-- Floating Chat Widget -->
    <style>
    .chat-fab{position:fixed;bottom:24px;right:24px;width:56px;height:56px;border-radius:50%;background:var(--primary,#4A7C2E);color:#fff;border:none;cursor:pointer;box-shadow:0 4px 16px rgba(0,0,0,.25);z-index:9999;display:flex;align-items:center;justify-content:center;font-size:24px;transition:transform .2s,box-shadow .2s;}
    .chat-fab:hover{transform:scale(1.08);box-shadow:0 6px 24px rgba(0,0,0,.3);}
    .chat-fab .chat-fab-badge{position:absolute;top:-2px;right:-2px;background:#e74c3c;color:#fff;font-size:11px;font-weight:700;min-width:20px;height:20px;border-radius:10px;display:flex;align-items:center;justify-content:center;border:2px solid #fff;}
    .chat-panel{position:fixed;bottom:90px;right:24px;width:380px;height:520px;background:var(--white,#fff);border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.2);z-index:9998;display:none;flex-direction:column;overflow:hidden;border:1px solid var(--gray-200,#e9ecef);}
    .chat-panel.open{display:flex;}
    .chat-panel-header{padding:14px 16px;border-bottom:1px solid var(--gray-200);display:flex;align-items:center;justify-content:space-between;background:var(--primary,#4A7C2E);color:#fff;}
    .chat-panel-header h3{margin:0;font-size:15px;font-weight:600;}
    .chat-panel-header button{background:none;border:none;color:#fff;font-size:18px;cursor:pointer;padding:0 0 0 8px;opacity:.8;line-height:1;}
    .chat-panel-header button:hover{opacity:1;}
    .chat-panel-search{padding:10px 12px;border-bottom:1px solid var(--gray-200);}
    .chat-panel-search input{width:100%;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;outline:none;background:var(--gray-50);}
    .chat-panel-search input:focus{border-color:var(--primary);}
    .chat-panel-search-results{max-height:160px;overflow-y:auto;display:none;border-bottom:1px solid var(--gray-200);}
    .chat-panel-search-results.open{display:block;}
    .chat-panel-user-item{padding:8px 12px;cursor:pointer;font-size:12px;border-bottom:1px solid var(--gray-100);}
    .chat-panel-user-item:hover{background:var(--gray-50);}
    .chat-panel-threads{flex:1;overflow-y:auto;}
    .chat-panel-thread{display:flex;align-items:center;gap:8px;padding:10px 12px;cursor:pointer;border-bottom:1px solid var(--gray-100);transition:background .15s;}
    .chat-panel-thread:hover{background:var(--gray-50);}
    .chat-panel-thread.active{background:var(--primary);color:#fff;}
    .chat-panel-thread.active .chat-thread-sub,.chat-panel-thread.active .chat-thread-time{color:rgba(255,255,255,.7);}
    .chat-panel-thread.active .chat-thread-close{color:rgba(255,255,255,.7);border-color:rgba(255,255,255,.3);}
    .chat-panel-thread.active .chat-thread-close:hover{color:#fff;background:rgba(255,255,255,.15);}
    .chat-thread-info{flex:1;min-width:0;}
    .chat-thread-name{font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .chat-thread-sub{font-size:11px;color:var(--gray-500);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .chat-thread-meta{text-align:right;flex-shrink:0;}
    .chat-thread-time{font-size:10px;color:var(--gray-500);margin-bottom:2px;}
    .chat-thread-badge{display:inline-block;background:#e74c3c;color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;}
    .chat-panel-thread.active .chat-thread-badge{background:rgba(255,255,255,.25);}
    .chat-thread-close{background:none;border:1px solid var(--gray-200);color:var(--gray-500);font-size:14px;cursor:pointer;padding:2px 6px;border-radius:4px;line-height:1;flex-shrink:0;transition:all .15s;}
    .chat-thread-close:hover{color:#e74c3c;border-color:#e74c3c;background:#fef2f2;}
    .chat-closed-toggle{padding:8px 12px;font-size:11px;color:var(--gray-500);cursor:pointer;border-bottom:1px solid var(--gray-200);user-select:none;display:flex;align-items:center;gap:6px;}
    .chat-closed-toggle:hover{background:var(--gray-50);}
    .chat-closed-toggle .arrow{transition:transform .2s;font-size:10px;}
    .chat-closed-toggle .arrow.open{transform:rotate(90deg);}
    .chat-closed-list{display:none;border-bottom:1px solid var(--gray-200);}
    .chat-closed-list.open{display:block;}
    .chat-closed-list .chat-panel-thread{opacity:.7;}
    .chat-closed-list .chat-panel-thread:hover{opacity:1;}
    .chat-thread-open{background:none;border:1px solid var(--primary,#4A7C2E);color:var(--primary,#4A7C2E);font-size:10px;font-weight:600;cursor:pointer;padding:3px 8px;border-radius:4px;flex-shrink:0;transition:all .15s;}
    .chat-thread-open:hover{background:var(--primary);color:#fff;}
    .chat-panel-convo{display:none;flex-direction:column;flex:1;min-height:0;}
    .chat-panel-convo.open{display:flex;}
    .chat-convo-header{padding:10px 12px;border-bottom:1px solid var(--gray-200);display:flex;align-items:center;gap:8px;}
    .chat-convo-header button{background:none;border:none;font-size:16px;cursor:pointer;color:var(--gray-600);padding:2px 4px;}
    .chat-convo-header span{font-size:13px;font-weight:600;}
    .chat-convo-messages{flex:1;overflow-y:auto;padding:12px;display:flex;flex-direction:column;gap:4px;}
    .chat-convo-empty{flex:1;display:flex;align-items:center;justify-content:center;color:var(--gray-500);font-size:12px;text-align:center;padding:16px;}
    .chat-bubble{max-width:80%;padding:8px 12px;border-radius:10px;font-size:12px;line-height:1.45;word-wrap:break-word;}
    .chat-bubble.sent{align-self:flex-end;background:var(--primary,#4A7C2E);color:#fff;border-bottom-right-radius:3px;}
    .chat-bubble.received{align-self:flex-start;background:var(--gray-100);color:var(--gray-800);border-bottom-left-radius:3px;}
    .chat-bubble-sender{font-size:10px;font-weight:600;margin-bottom:1px;opacity:.8;}
    .chat-bubble-time{font-size:9px;opacity:.55;margin-top:3px;text-align:right;}
    .chat-convo-input{padding:10px 12px;border-top:1px solid var(--gray-200);display:flex;gap:6px;align-items:flex-end;}
    .chat-convo-input textarea{flex:1;padding:8px 10px;border:1px solid var(--gray-200);border-radius:6px;font-size:12px;resize:none;outline:none;font-family:inherit;max-height:60px;min-height:34px;}
    .chat-convo-input textarea:focus{border-color:var(--primary);}
    .chat-convo-input button{padding:8px 14px;background:var(--primary,#4A7C2E);color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;}
    .chat-convo-input button:disabled{opacity:.5;cursor:not-allowed;}
    .chat-empty-state{flex:1;display:flex;align-items:center;justify-content:center;color:var(--gray-500);font-size:13px;}
    </style>

    <button class="chat-fab" id="chatFab" onclick="toggleChatPanel()">
        &#128172;
        <span class="chat-fab-badge" id="chatFabBadge" style="display:none;">0</span>
    </button>

    <div class="chat-panel" id="chatPanel">
        <div class="chat-panel-header">
            <h3>Chat</h3>
            <button onclick="toggleChatPanel()">&times;</button>
        </div>
        <div class="chat-panel-search">
            <input type="text" id="chatUserSearch" placeholder="Search users..." autocomplete="off">
        </div>
        <div class="chat-panel-search-results" id="chatSearchResults"></div>
        <div class="chat-panel-threads" id="chatThreads">
            <div class="chat-empty-state">Loading conversations...</div>
        </div>
        <div class="chat-panel-convo" id="chatConvo">
            <div class="chat-convo-header">
                <button onclick="closeConvo()">&larr;</button>
                <span id="chatConvoName">...</span>
            </div>
            <div class="chat-convo-messages" id="chatConvoMessages">
                <div class="chat-convo-empty">No messages yet</div>
            </div>
            <div class="chat-convo-input">
                <textarea id="chatMsgInput" rows="1" placeholder="Type a message..."></textarea>
                <button onclick="sendChatMsg()">Send</button>
            </div>
        </div>
    </div>

    <script>
    var chatOpen=false,chatConvoOpen=false,chatThreadId=null,chatLastTs=null,chatPoll=null,chatThreadPoll=null,chatSearchTimer=null;
    var chatCurrentUserId={{ auth()->id() }};

    function toggleChatPanel(){
        chatOpen=!chatOpen;
        document.getElementById('chatPanel').classList.toggle('open',chatOpen);
        if(chatOpen&&!chatConvoOpen){chatLoadThreads();}
    }

    function chatLoadThreads(){
        fetch('/dts/chat/threads').then(function(r){return r.json();}).then(function(data){
            var el=document.getElementById('chatThreads'),total=0;
            var openThreads=data.open||[];
            var closedThreads=data.closed||[];

            if(!openThreads.length&&!closedThreads.length){
                el.innerHTML='<div class="chat-empty-state">No conversations yet</div>';
                chatUpdateBadge(0);return;
            }

            var html='';
            openThreads.forEach(function(t){
                html+=chatRenderThread(t,true);
                total+=t.unread_count;
            });

            if(closedThreads.length){
                html+='<div class="chat-closed-toggle" onclick="chatToggleClosed()"><span class="arrow" id="chatClosedArrow">&#9654;</span> Closed ('+closedThreads.length+')</div>';
                html+='<div class="chat-closed-list" id="chatClosedList">';
                closedThreads.forEach(function(t){
                    html+=chatRenderThread(t,false);
                });
                html+='</div>';
            }

            el.innerHTML=html;
            chatUpdateBadge(total);
        });
    }

    function chatRenderThread(t,isOpen){
        var active=t.id===chatThreadId?' active':'';
        var preview=t.last_message?t.last_message.body:'No messages yet';
        var time=t.last_message?chatFmtTime(t.last_message.created_at):'';
        var badge=t.unread_count>0?'<span class="chat-thread-badge">'+t.unread_count+'</span>':'';
        var nameHtml='<div class="chat-thread-name">'+chatEsc(t.other_user.name)+'</div>';

        var actionBtn='';
        if(isOpen){
            actionBtn='<button class="chat-thread-close" onclick="event.stopPropagation();chatCloseThread('+t.id+')" title="Close conversation">&times;</button>';
        }else{
            actionBtn='<button class="chat-thread-open" onclick="event.stopPropagation();chatOpenThreadById('+t.id+')" title="Reopen">Open</button>';
        }

        return '<div class="chat-panel-thread'+active+'" onclick="chatOpenThread('+t.id+','+JSON.stringify(t.other_user).replace(/"/g,'&quot;')+')"><div class="chat-thread-info">'+nameHtml+'<div class="chat-thread-sub">'+chatEsc(preview)+'</div></div><div class="chat-thread-meta"><div class="chat-thread-time">'+time+'</div>'+badge+'</div>'+actionBtn+'</div>';
    }

    function chatToggleClosed(){
        var list=document.getElementById('chatClosedList');
        var arrow=document.getElementById('chatClosedArrow');
        if(list){list.classList.toggle('open');}
        if(arrow){arrow.classList.toggle('open');}
    }

    function chatCloseThread(threadId){
        fetch('/dts/chat/threads/'+threadId+'/close',{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'}})
        .then(function(){chatLoadThreads();});
    }

    function chatOpenThreadById(threadId){
        fetch('/dts/chat/threads/'+threadId+'/open',{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'}})
        .then(function(r){return r.json();}).then(function(){
            chatLoadThreads();
            fetch('/dts/chat/threads').then(function(r){return r.json();}).then(function(data){
                var all=(data.open||[]).concat(data.closed||[]);
                var t=all.find(function(x){return x.id===threadId;});
                if(t)chatOpenThread(t.id,t.other_user);
            });
        });
    }

    function chatUpdateBadge(n){
        var b=document.getElementById('chatFabBadge');
        if(n>0){b.textContent=n;b.style.display='flex';}else{b.style.display='none';}
    }

    function chatSearchUsers(q){
        clearTimeout(chatSearchTimer);
        var el=document.getElementById('chatSearchResults');
        if(q.length<1){el.classList.remove('open');return;}
        chatSearchTimer=setTimeout(function(){
            fetch('/dts/chat/search-users?q='+encodeURIComponent(q)).then(function(r){return r.json();}).then(function(users){
                if(!users.length){el.innerHTML='<div style="padding:10px;text-align:center;color:var(--gray-500);font-size:11px;">No users found</div>';}
                else{el.innerHTML=users.map(function(u){return '<div class="chat-panel-user-item" onclick="chatStartChat('+u.id+')">'+chatEsc(u.name)+'</div>';}).join('');}
                el.classList.add('open');
            });
        },300);
    }

    function chatStartChat(userId){
        fetch('/dts/chat/threads',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'},body:JSON.stringify({recipient_id:userId})})
        .then(function(r){return r.json();}).then(function(thread){
            document.getElementById('chatUserSearch').value='';
            document.getElementById('chatSearchResults').classList.remove('open');
            chatOpenThread(thread.id,thread.other_user);
            chatLoadThreads();
        });
    }

    function chatOpenThread(id,otherUser){
        chatThreadId=id;chatLastTs=null;chatConvoOpen=true;
        document.getElementById('chatConvoName').textContent=otherUser.name;
        document.getElementById('chatConvo').classList.add('open');
        document.getElementById('chatConvoMessages').innerHTML='<div class="chat-convo-empty">Loading...</div>';
        chatLoadMessages(true);
        chatLoadThreads();
        clearInterval(chatPoll);
        chatPoll=setInterval(function(){chatLoadMessages(false);},3000);
    }

    function chatLoadMessages(initial){
        if(!chatThreadId)return;
        var url='/dts/chat/threads/'+chatThreadId+'/messages';
        if(!initial&&chatLastTs){url+='?since='+encodeURIComponent(chatLastTs);}
        fetch(url).then(function(r){return r.json();}).then(function(msgs){
            var el=document.getElementById('chatConvoMessages');
            if(initial){el.innerHTML='';if(!msgs.length){el.innerHTML='<div class="chat-convo-empty">No messages yet. Say hello!</div>';return;}}
            var shouldScroll=el.scrollTop+el.clientHeight>=el.scrollHeight-50;
            msgs.forEach(function(m){
                var isMine=m.sender_id===chatCurrentUserId;
                var b=document.createElement('div');b.className='chat-bubble '+(isMine?'sent':'received');
                var sender=isMine?'':'<div class="chat-bubble-sender">'+chatEsc(m.sender_name)+'</div>';
                b.innerHTML=sender+chatEsc(m.body)+'<div class="chat-bubble-time">'+chatFmtTime(m.created_at)+'</div>';
                el.appendChild(b);chatLastTs=m.created_at;
            });
            if(shouldScroll||initial)el.scrollTop=el.scrollHeight;
        });
    }

    function sendChatMsg(){
        var input=document.getElementById('chatMsgInput'),body=input.value.trim();
        if(!body||!chatThreadId)return;
        input.value='';input.style.height='auto';
        fetch('/dts/chat/threads/'+chatThreadId+'/messages',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'},body:JSON.stringify({body:body})})
        .then(function(r){return r.json();}).then(function(m){
            var el=document.getElementById('chatConvoMessages');
            var empty=el.querySelector('.chat-convo-empty');if(empty)empty.remove();
            var b=document.createElement('div');b.className='chat-bubble sent';
            b.innerHTML=chatEsc(m.body)+'<div class="chat-bubble-time">'+chatFmtTime(m.created_at)+'</div>';
            el.appendChild(b);el.scrollTop=el.scrollHeight;chatLastTs=m.created_at;chatLoadThreads();
        });
    }

    function closeConvo(){
        chatConvoOpen=false;chatThreadId=null;chatLastTs=null;
        document.getElementById('chatConvo').classList.remove('open');
        clearInterval(chatPoll);
        chatLoadThreads();
    }

    document.getElementById('chatUserSearch').addEventListener('input',function(){chatSearchUsers(this.value.trim());});
    document.getElementById('chatMsgInput').addEventListener('keydown',function(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendChatMsg();}});
    document.getElementById('chatMsgInput').addEventListener('input',function(){this.style.height='auto';this.style.height=Math.min(this.scrollHeight,60)+'px';});
    document.addEventListener('click',function(e){if(chatOpen&&!e.target.closest('.chat-panel')&&!e.target.closest('.chat-fab')){chatOpen=false;document.getElementById('chatPanel').classList.remove('open');}});

    function chatEsc(t){var d=document.createElement('div');d.textContent=t;return d.innerHTML;}
    function chatFmtTime(iso){var d=new Date(iso),now=new Date(),diff=Math.floor((now-d)/60000);if(diff<1)return'now';if(diff<60)return diff+'m';var h=Math.floor(diff/60);if(h<24)return h+'h';return d.toLocaleDateString('en-PH',{month:'short',day:'numeric'});}

    chatLoadThreads();
    chatThreadPoll=setInterval(chatLoadThreads,15000);
    </script>
</body>
</html>
