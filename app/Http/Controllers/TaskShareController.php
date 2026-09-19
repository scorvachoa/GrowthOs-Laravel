<?php

namespace App\Http\Controllers;

use App\Models\ExtraTask;
use App\Models\TaskShare;
use App\Models\User;
use App\Models\VideoTask;
use App\Notifications\TaskSharedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class TaskShareController extends Controller
{
    public function history(Request $request)
    {
        $userId = Auth::id();

        $query = TaskShare::query()
            ->with(['sharedByUser', 'sharedWithUser', 'shareable'])
            ->where('shared_by_user_id', $userId)
            ->orWhere('shared_with_user_id', $userId);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('sharedByUser', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('sharedWithUser', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $status = $request->status;
            if ($status === 'accepted') {
                $query->whereNotNull('accepted_at');
            } elseif ($status === 'pending') {
                $query->whereNull('accepted_at');
            } elseif ($status === 'rejected') {
                $shareIds = TaskShare::pluck('id');
                $query->whereNotIn('id', $shareIds);
            }
        }

        if ($request->filled('type')) {
            $type = $request->type === 'video_task' ? VideoTask::class : ExtraTask::class;
            $query->where('shareable_type', $type);
        }

        $shares = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('Shares/History', [
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

    public function orgUsers()
    {
        $user = Auth::user();
        $orgId = $user->activeOrganizationId();

        $users = User::query()
            ->where('organization_id', $orgId)
            ->where('id', '!=', $user->id)
            ->get(['id', 'name']);

        return response()->json($users);
    }

    public function update(Request $request)
    {
        $request->validate([
            'shareable_type' => ['required', 'in:video_tasks,extra_tasks'],
            'shareable_id' => ['required', 'integer'],
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'in:reader,editor'],
        ]);

        $modelClass = match ($request->shareable_type) {
            'video_tasks' => VideoTask::class,
            'extra_tasks' => ExtraTask::class,
        };

        $task = $modelClass::findOrFail($request->shareable_id);

        if ($task->created_by !== Auth::id()) {
            abort(403, 'Solo el creador puede compartir esta tarea');
        }

        $currentUserId = Auth::id();

        $existing = $task->shares()->where('shared_by_user_id', $currentUserId)->pluck('shared_with_user_id')->toArray();
        $task->shares()->where('shared_by_user_id', $currentUserId)->delete();

        foreach ($request->user_ids as $idx => $userId) {
            if ((int) $userId === $currentUserId) {
                continue;
            }

            $role = $request->roles[$idx] ?? 'editor';

            $share = $task->shares()->create([
                'shared_with_user_id' => $userId,
                'shared_by_user_id' => $currentUserId,
                'role' => $role,
            ]);

            if (! in_array((int) $userId, $existing)) {
                $recipient = User::find($userId);
                if ($recipient) {
                    $type = $request->shareable_type === 'video_tasks' ? 'video_task' : 'extra_task';
                    $recipient->notify(new TaskSharedNotification(
                        Auth::user(), $task, $type, $share
                    ));
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    public function unshare(Request $request)
    {
        $request->validate([
            'shareable_type' => ['required', 'in:video_tasks,extra_tasks'],
            'shareable_id' => ['required', 'integer'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $modelClass = match ($request->shareable_type) {
            'video_tasks' => VideoTask::class,
            'extra_tasks' => ExtraTask::class,
        };

        $task = $modelClass::findOrFail($request->shareable_id);

        if ($task->created_by !== Auth::id()) {
            abort(403, 'Solo el creador puede dejar de compartir');
        }

        $task->shares()
            ->where('shared_with_user_id', $request->user_id)
            ->where('shared_by_user_id', Auth::id())
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function accept(TaskShare $taskShare)
    {
        $user = Auth::user();

        if ($taskShare->shared_with_user_id !== $user->id) {
            abort(403, 'No puedes aceptar este share');
        }

        $taskShare->update(['accepted_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function reject(TaskShare $taskShare)
    {
        $user = Auth::user();

        if ($taskShare->shared_with_user_id !== $user->id) {
            abort(403, 'No puedes rechazar este share');
        }

        $taskShare->delete();

        return response()->json(['ok' => true]);
    }
}
