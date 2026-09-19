<?php

namespace App\Http\Controllers;

use App\Models\TaskShare;
use App\Models\User;
use App\Models\VideoTask;
use App\Models\ExtraTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $notifications = $user->unreadNotifications()
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'data' => $n->data,
                'read_at' => null,
                'created_at' => $n->created_at->diffForHumans(),
            ]);

        $unreadCount = $user->unreadNotifications()->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function history(Request $request)
    {
        $user = Auth::user();

        // Notifications query
        $query = $user->notifications();

        if ($search = $request->input('search')) {
            $query->where('data->message', 'LIKE', "%{$search}%");
        }

        if ($status = $request->input('status')) {
            if ($status === 'unread') {
                $query->whereNull('read_at');
            } elseif ($status === 'read') {
                $query->whereNotNull('read_at');
            } elseif ($status === 'accepted') {
                $shareIds = TaskShare::whereNotNull('accepted_at')->pluck('id');
                $query->whereIn('data->task_share_id', $shareIds);
            } elseif ($status === 'rejected') {
                $shareIds = TaskShare::pluck('id');
                $query->whereNotNull('data->task_share_id')
                    ->whereNotIn('data->task_share_id', $shareIds);
            }
        }

        $notifications = $query->latest()->paginate(15)->withQueryString();

        // Pre-load share statuses to avoid N+1
        $shareIds = collect($notifications->items())->pluck('data.task_share_id')->filter()->unique()->toArray();
        $shareStatuses = TaskShare::whereIn('id', $shareIds)->pluck('accepted_at', 'id');
        $deletedShareIds = collect($shareIds)->diff($shareStatuses->keys())->toArray();

        $notifications->setCollection($notifications->getCollection()->map(function ($n) use ($shareStatuses, $deletedShareIds) {
            $shareId = $n['data']['task_share_id'] ?? null;
            $shareStatus = null;
            if ($shareId) {
                if (in_array($shareId, $deletedShareIds)) {
                    $shareStatus = 'rejected';
                } elseif ($shareStatuses->has($shareId)) {
                    $shareStatus = $shareStatuses[$shareId] ? 'accepted' : 'pending';
                }
            }
            $n['share_status'] = $shareStatus;
            return $n;
        }));

        // Shares query
        $userId = Auth::id();
        $sharesQuery = TaskShare::query()
            ->with(['sharedByUser', 'sharedWithUser', 'shareable'])
            ->where('shared_by_user_id', $userId)
            ->orWhere('shared_with_user_id', $userId);

        if ($request->filled('search')) {
            $search = $request->search;
            $sharesQuery->where(function ($q) use ($search) {
                $q->whereHas('sharedByUser', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('sharedWithUser', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $status = $request->status;
            if ($status === 'accepted') {
                $sharesQuery->whereNotNull('accepted_at');
            } elseif ($status === 'pending') {
                $sharesQuery->whereNull('accepted_at');
            }
        }

        if ($request->filled('type')) {
            $type = $request->type === 'video_task' ? VideoTask::class : ExtraTask::class;
            $sharesQuery->where('shareable_type', $type);
        }

        $shares = $sharesQuery->latest()->paginate(15)->withQueryString();

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'shares' => $shares->through(fn ($s) => [
                'id' => $s->id,
                'shareable_type' => class_basename($s->shareable_type),
                'shareable_id' => $s->shareable_id,
                'role' => $s->role,
                'status' => $s->isAccepted() ? 'accepted' : 'pending',
                'created_at' => $s->created_at->format('Y-m-d H:i'),
                'shared_by' => ['id' => $s->sharedByUser->id, 'name' => $s->sharedByUser->name],
                'shared_with' => ['id' => $s->sharedWithUser->id, 'name' => $s->sharedWithUser->name],
                'task' => $s->shareable ? [
                    'title' => $s->shareable->title ?? $s->shareable->task_title ?? 'Sin título',
                    'date' => $s->shareable->task_date ?? null,
                    'status' => $s->shareable->status ?? null,
                ] : null,
            ]),
            'filters' => $request->only(['search', 'status', 'type']),
        ]);
    }

    public function markAsRead(Request $request, string $id)
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json([
            'unread_count' => Auth::user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllAsRead()
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function clearAll()
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
