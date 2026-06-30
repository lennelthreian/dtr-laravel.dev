<?php

namespace App\Http\Controllers;

use App\Models\DtsDocument;
use App\Models\DtsDocumentLog;
use App\Models\DtsNotification;
use App\Models\Office;
use App\Models\Section;
use App\Models\User;
use App\Services\UserLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DtsController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('dts.index');
        }
        return view('dts.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->filled('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();

            if ($user->trashed()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->withErrors([
                    'username' => 'Your account has been deleted.',
                ])->onlyInput('username');
            }

            if (!$user->is_active) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->withErrors([
                    'username' => 'Your account has been deactivated. Contact the administrator.',
                ])->onlyInput('username');
            }

            app(UserLogService::class)->login($user->id);

            return redirect()->intended(route('dts.index'));
        }

        return back()->withErrors([
            'username' => 'The provided credentials do not match our records.',
        ])->onlyInput('username');
    }

    public function index()
    {
        $user = auth()->user();

        $incomingCount = DtsDocument::where('recipient_id', $user->id)
            ->whereIn('status', ['pending', 'received'])
            ->count();

        $outgoingCount = DtsDocument::where('sender_id', $user->id)->count();

        $pendingCount = DtsDocument::where('recipient_id', $user->id)
            ->where('status', 'pending')
            ->count();

        $processedCount = DtsDocument::where(function ($q) use ($user) {
            $q->where('sender_id', $user->id)
              ->orWhere('recipient_id', $user->id);
        })->where('status', 'processed')->count();

        $totalDocuments = DtsDocument::count();

        $recentDocuments = DtsDocument::with(['sender', 'recipient', 'office', 'section'])
            ->where(function ($q) use ($user) {
                $q->where('sender_id', $user->id)
                  ->orWhere('recipient_id', $user->id);
            })
            ->latest()
            ->limit(5)
            ->get();

        $offices = Office::with('sections')->orderBy('name')->get();

        return view('dts.index', compact(
            'user', 'incomingCount', 'outgoingCount', 'pendingCount',
            'processedCount', 'totalDocuments', 'recentDocuments', 'offices'
        ));
    }

    public function documents(Request $request)
    {
        $user = auth()->user();
        $type = $request->query('type', 'all');

        $query = DtsDocument::with(['sender', 'recipient', 'office', 'section', 'creator']);

        if ($type === 'incoming') {
            $query->where('recipient_id', $user->id);
        } elseif ($type === 'outgoing') {
            $query->where('sender_id', $user->id);
        } elseif ($type === 'archived') {
            $query->onlyTrashed()->where(function ($q) use ($user) {
                $q->where('sender_id', $user->id)
                  ->orWhere('recipient_id', $user->id);
            });
        } else {
            $query->where(function ($q) use ($user) {
                $q->where('sender_id', $user->id)
                  ->orWhere('recipient_id', $user->id);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('tracking_number', 'like', '%' . $search . '%')
                  ->orWhere('title', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('status') && $type !== 'archived') {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $documents = $query->latest()->paginate(15)->appends($request->query());
        $offices = Office::orderBy('name')->get();

        return view('dts.documents.index', compact('documents', 'offices', 'type', 'user'));
    }

    public function create()
    {
        $user = auth()->user();
        $offices = Office::orderBy('name')->get();
        $users = User::where('id', '!=', $user->id)
            ->where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $trackingNumber = $this->generateTrackingNumber();

        return view('dts.documents.create', compact('offices', 'users', 'trackingNumber', 'user'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'type' => 'required|in:incoming,outgoing',
            'priority' => 'required|in:low,normal,high,urgent',
            'recipient_id' => 'nullable|exists:users,id',
            'date_received' => 'nullable|date',
            'deadline' => 'nullable|date',
            'remarks' => 'nullable|string',
        ]);

        $user = auth()->user();

        $data['tracking_number'] = $this->generateTrackingNumber();
        $data['sender_id'] = $user->id;
        $data['created_by'] = $user->id;
        $data['status'] = $data['type'] === 'incoming' ? 'received' : 'pending';

        if (!empty($data['recipient_id'])) {
            $recipient = User::find($data['recipient_id']);
            $data['office_id'] = $recipient->office_id;
            $data['section_id'] = $recipient->section_id;
        }

        $document = DtsDocument::create($data);

        $this->logAction($document, $user->id, 'created', null, $document->status,
            'Document created by ' . $user->name);

        if ($data['recipient_id'] && $data['type'] === 'outgoing') {
            $this->notify($data['recipient_id'], $document->id, 'new_document',
                $user->name . ' sent you a new document: ' . $document->title);
        }

        return redirect()->route('dts.documents.show', $document)
            ->with('success', 'Document registered successfully.');
    }

    public function show(DtsDocument $document)
    {
        $user = auth()->user();
        $document->load(['sender', 'recipient', 'office', 'section', 'creator',
            'logs' => function ($q) { $q->with('user')->latest(); }]);

        $offices = Office::orderBy('name')->get();
        $users = User::where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('dts.documents.show', compact('document', 'offices', 'users', 'user'));
    }

    public function edit(DtsDocument $document)
    {
        $user = auth()->user();
        $offices = Office::orderBy('name')->get();
        $users = User::where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('dts.documents.edit', compact('document', 'offices', 'users', 'user'));
    }

    public function update(Request $request, DtsDocument $document)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'type' => 'required|in:incoming,outgoing',
            'priority' => 'required|in:low,normal,high,urgent',
            'recipient_id' => 'nullable|exists:users,id',
            'date_received' => 'nullable|date',
            'deadline' => 'nullable|date',
            'remarks' => 'nullable|string',
        ]);

        if (!empty($data['recipient_id'])) {
            $recipient = User::find($data['recipient_id']);
            $data['office_id'] = $recipient->office_id;
            $data['section_id'] = $recipient->section_id;
        }

        $oldStatus = $document->status;
        $document->update($data);

        $this->logAction($document, auth()->id(), 'updated', $oldStatus, $document->status,
            'Document details updated');

        return redirect()->route('dts.documents.show', $document)
            ->with('success', 'Document updated successfully.');
    }

    public function destroy(DtsDocument $document)
    {
        $this->logAction($document, auth()->id(), 'archived', $document->status, null,
            'Document archived');

        $document->delete();

        return redirect()->route('dts.documents')
            ->with('success', 'Document archived successfully.');
    }

    public function restore($id)
    {
        $document = DtsDocument::onlyTrashed()->findOrFail($id);
        $document->restore();

        $this->logAction($document, auth()->id(), 'restored', null, $document->status,
            'Document restored from archive');

        return redirect()->route('dts.documents.show', $document)
            ->with('success', 'Document restored successfully.');
    }

    public function forward(Request $request, DtsDocument $document)
    {
        $data = $request->validate([
            'recipient_id' => 'required|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        $oldStatus = $document->status;
        $recipient = User::find($data['recipient_id']);
        $document->update([
            'recipient_id' => $data['recipient_id'],
            'office_id' => $recipient->office_id,
            'section_id' => $recipient->section_id,
            'status' => 'pending',
        ]);

        $recipient = User::find($data['recipient_id']);
        $this->logAction($document, auth()->id(), 'forwarded', $oldStatus, 'pending',
            'Forwarded to ' . $recipient->name . ($data['notes'] ? ' - ' . $data['notes'] : ''));

        $this->notify($data['recipient_id'], $document->id, 'forwarded',
            auth()->user()->name . ' forwarded document ' . $document->tracking_number . ' to you');

        if ($document->sender_id && $document->sender_id !== auth()->id()) {
            $this->notify($document->sender_id, $document->id, 'forwarded',
                'Your document ' . $document->tracking_number . ' was forwarded to ' . $recipient->name);
        }

        return redirect()->route('dts.documents.show', $document)
            ->with('success', 'Document forwarded successfully.');
    }

    public function receive(DtsDocument $document)
    {
        $oldStatus = $document->status;
        $document->update([
            'status' => 'received',
            'date_received' => now(),
            'recipient_id' => auth()->id(),
        ]);

        $this->logAction($document, auth()->id(), 'received', $oldStatus, 'received',
            'Document received');

        if ($document->sender_id && $document->sender_id !== auth()->id()) {
            $this->notify($document->sender_id, $document->id, 'received',
                auth()->user()->name . ' received your document ' . $document->tracking_number);
        }

        return redirect()->route('dts.documents.show', $document)
            ->with('success', 'Document marked as received.');
    }

    public function process(DtsDocument $document)
    {
        $oldStatus = $document->status;
        $document->update([
            'status' => 'processed',
            'date_actioned' => now(),
        ]);

        $this->logAction($document, auth()->id(), 'processed', $oldStatus, 'processed',
            'Document processed');

        if ($document->sender_id && $document->sender_id !== auth()->id()) {
            $this->notify($document->sender_id, $document->id, 'processed',
                auth()->user()->name . ' processed your document ' . $document->tracking_number);
        }

        return redirect()->route('dts.documents.show', $document)
            ->with('success', 'Document marked as processed.');
    }

    public function notifications()
    {
        $user = auth()->user();
        $notifications = DtsNotification::with('document')
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(20);

        $unreadCount = DtsNotification::where('user_id', $user->id)
            ->where('is_read', false)->count();

        return view('dts.notifications.index', compact('notifications', 'unreadCount', 'user'));
    }

    public function markNotificationRead($id)
    {
        $notification = DtsNotification::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $notification->update(['is_read' => true]);

        if ($notification->document_id && $notification->document) {
            return redirect()->route('dts.documents.show', $notification->document_id);
        }

        return redirect()->route('dts.notifications');
    }

    public function markAllNotificationsRead()
    {
        DtsNotification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return back()->with('success', 'All notifications marked as read.');
    }

    public function sectionsByOffice(Office $office)
    {
        return $office->sections()->orderBy('name')->pluck('name', 'id');
    }

    public function usersByOffice(Office $office)
    {
        $users = User::where('is_active', true)
            ->where('office_id', $office->id)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn($u) => ['id' => $u->id, 'name' => $u->name]);

        return response()->json($users);
    }

    public function usersBySection(Section $section)
    {
        $users = User::where('is_active', true)
            ->where('section_id', $section->id)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn($u) => ['id' => $u->id, 'name' => $u->name]);

        return response()->json($users);
    }

    public function printRoutingSlip(DtsDocument $document)
    {
        $document->load(['sender', 'recipient', 'office', 'section', 'creator',
            'logs' => function ($q) { $q->with('user')->orderBy('created_at'); }]);

        $routingEntries = $document->logs->filter(function ($log) {
            return in_array($log->action, ['forwarded', 'received', 'processed', 'created']);
        });

        $rows = [];
        foreach ($routingEntries as $entry) {
            $fromUser = $entry->action === 'created' ? $document->sender : $entry->user;
            $toUser = $entry->action === 'forwarded' ? $document->recipient
                : ($entry->action === 'received' ? $entry->user : $document->recipient);

            $fromName = optional($fromUser)->name ?? '-';
            $fromOfficeName = null;
            $fromSectionName = null;

            if ($fromUser) {
                if ($fromUser->office_id) {
                    $office = \App\Models\Office::find($fromUser->office_id);
                    $fromOfficeName = optional($office)->name;
                } else {
                    $fromOfficeName = $fromUser->office;
                }
                if ($fromUser->section_id) {
                    $section = \App\Models\Section::find($fromUser->section_id);
                    $fromSectionName = optional($section)->name;
                } else {
                    $fromSectionName = $fromUser->section;
                }
            }

            $fromDisplay = $fromName;
            if ($fromOfficeName) {
                $fromPart = $fromOfficeName;
                if ($fromSectionName) {
                    $fromPart .= ' - ' . $fromSectionName;
                }
                $fromDisplay .= '<br><span style="font-size:10px;color:#888;">' . e($fromPart) . '</span>';
            }

            $toName = optional($toUser)->name ?? '-';
            $toOfficeName = null;
            $toSectionName = null;

            if ($toUser) {
                if ($toUser->office_id) {
                    $toOffice = \App\Models\Office::find($toUser->office_id);
                    $toOfficeName = optional($toOffice)->name;
                } else {
                    $toOfficeName = $toUser->office;
                }
                if ($toUser->section_id) {
                    $toSection = \App\Models\Section::find($toUser->section_id);
                    $toSectionName = optional($toSection)->name;
                } else {
                    $toSectionName = $toUser->section;
                }
            }

            $toDisplay = $toName;
            if ($toOfficeName) {
                $toPart = $toOfficeName;
                if ($toSectionName) {
                    $toPart .= ' - ' . $toSectionName;
                }
                $toDisplay .= '<br><span style="font-size:10px;color:#888;">' . e($toPart) . '</span>';
            }

            $rows[] = (object) [
                'date' => $entry->created_at->format('M d, Y'),
                'time' => $entry->created_at->format('h:i A'),
                'from' => $fromDisplay,
                'to' => $toDisplay,
            ];
        }

        $settings = \App\Models\DtrSetting::getSettings();
        $logoPath = $settings['logo_path'] ?? null;
        $docUrl = route('dts.documents.show', $document);

        return view('dts.documents.routing-slip', compact('document', 'routingEntries', 'rows', 'logoPath', 'docUrl'));
    }

    private function generateTrackingNumber()
    {
        $year = date('Y');
        $count = DtsDocument::withTrashed()
            ->whereYear('created_at', $year)
            ->count() + 1;

        return 'DTS-' . $year . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
    }

    private function logAction($document, $userId, $action, $fromStatus, $toStatus, $notes)
    {
        DtsDocumentLog::create([
            'document_id' => $document->id,
            'user_id' => $userId,
            'action' => $action,
            'notes' => $notes,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
        ]);
    }

    private function notify($userId, $documentId, $type, $message)
    {
        DtsNotification::create([
            'user_id' => $userId,
            'document_id' => $documentId,
            'type' => $type,
            'message' => $message,
            'is_read' => false,
        ]);
    }
}
