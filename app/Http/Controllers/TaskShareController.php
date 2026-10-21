<?php

namespace App\Http\Controllers;

use App\Models\ExtraTask;
use App\Models\TaskShare;
use App\Models\User;
use App\Models\VideoTask;
use App\Services\TaskShareService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TaskShareController extends Controller
{
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

    public function update(Request $request, TaskShareService $shareService)
    {
        $orgId = Auth::user()->activeOrganizationId();

        $request->validate([
            'shareable_type' => ['required', 'in:video_tasks,extra_tasks'],
            'shareable_id' => ['required', 'integer'],
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('organization_id', $orgId))],
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

        $shareService->sync($task, $request->user_ids, $request->roles ?? [], existingScopedToAuthor: true);

        return response()->json(['ok' => true]);
    }

    public function unshare(Request $request)
    {
        $orgId = Auth::user()->activeOrganizationId();

        $request->validate([
            'shareable_type' => ['required', 'in:video_tasks,extra_tasks'],
            'shareable_id' => ['required', 'integer'],
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('organization_id', $orgId))],
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
