<?php

namespace App\Http\Controllers;

use App\Services\Facebook\AuthService;
use App\Services\Facebook\FacebookGraphException;
use App\Services\Facebook\InstagramCommentsService;
use App\Services\Facebook\InstagramService;
use App\Services\Facebook\MessagesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class InstagramController extends Controller
{
    public function __construct(private readonly AuthService $facebook) {}

    public function index(Request $request, InstagramService $instagram)
    {
        $orgId = Auth::user()->activeOrganizationId();

        $days = (int) $request->query('days', 28);

        if (! in_array($days, [7, 28, 90], true)) {
            $days = 28;
        }

        return Inertia::render('Instagram/Index', [
            'organization_id' => $orgId ?: null,
            'connection' => $orgId ? $this->facebookPage($orgId) : null,
            'account' => $orgId ? $instagram->account($orgId) : null,
            'feed' => $orgId ? $instagram->feed($orgId) : null,
            'insights' => $orgId ? $instagram->insights($orgId, $days) : null,
            'days' => $orgId ? $days : null,
        ]);
    }

    public function comments(InstagramCommentsService $comments, InstagramService $instagram)
    {
        $orgId = Auth::user()->activeOrganizationId();

        return Inertia::render('Instagram/Comments', [
            'organization_id' => $orgId ?: null,
            'connection' => $orgId ? $this->facebookPage($orgId) : null,
            'account' => $orgId ? $instagram->account($orgId) : null,
            'comments' => $orgId ? $comments->threads($orgId) : null,
        ]);
    }

    public function reply(Request $request, InstagramCommentsService $comments)
    {
        $request->validate([
            'comment_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._\-]+$/'],
            'message' => 'required|string|max:1000',
            'organization_id' => 'sometimes|integer|exists:organizations,id',
        ]);

        try {
            $comments->reply($this->resolveOrgId($request), $request->input('comment_id'), $request->input('message'));
        } catch (FacebookGraphException $e) {
            return redirect()->back()->with('error', 'No se pudo responder: '.$e->getMessage());
        }

        return redirect()->back()->with('success', 'Comentario respondido correctamente');
    }

    public function publish(Request $request, InstagramService $instagram)
    {
        $request->validate([
            'caption' => 'nullable|string|max:2200',
            'image_url' => 'nullable|url|max:2000|required_without:video_url',
            'video_url' => 'nullable|url|max:2000|required_without:image_url',
            'reel' => 'sometimes|boolean',
            'organization_id' => 'sometimes|integer|exists:organizations,id',
        ]);

        try {
            $instagram->publish(
                $this->resolveOrgId($request),
                $request->input('caption'),
                $request->input('image_url') ?: null,
                $request->input('video_url') ?: null,
                (bool) $request->boolean('reel'),
            );
        } catch (FacebookGraphException $e) {
            return redirect()->back()->with('error', 'No se pudo publicar: '.$e->getMessage());
        }

        return redirect()->back()->with('success', 'Publicado en Instagram');
    }

    public function deleteMedia(Request $request, InstagramService $instagram)
    {
        $request->validate([
            'media_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._\-]+$/'],
            'organization_id' => 'sometimes|integer|exists:organizations,id',
        ]);

        try {
            $instagram->deleteMedia($this->resolveOrgId($request), $request->input('media_id'));
        } catch (FacebookGraphException $e) {
            return redirect()->back()->with('error', 'No se pudo eliminar: '.$e->getMessage());
        }

        return redirect()->back()->with('success', 'Publicación eliminada');
    }

    public function hideComment(Request $request, InstagramCommentsService $comments)
    {
        $request->validate([
            'comment_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._\-]+$/'],
            'hidden' => 'required|boolean',
            'organization_id' => 'sometimes|integer|exists:organizations,id',
        ]);

        try {
            $comments->hide($this->resolveOrgId($request), $request->input('comment_id'), $request->boolean('hidden'));
        } catch (FacebookGraphException $e) {
            return redirect()->back()->with('error', 'No se pudo actualizar el comentario: '.$e->getMessage());
        }

        return redirect()->back()->with('success', $request->boolean('hidden') ? 'Comentario ocultado' : 'Comentario visible de nuevo');
    }

    public function deleteComment(Request $request, InstagramCommentsService $comments)
    {
        $request->validate([
            'comment_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._\-]+$/'],
            'organization_id' => 'sometimes|integer|exists:organizations,id',
        ]);

        try {
            $comments->delete($this->resolveOrgId($request), $request->input('comment_id'));
        } catch (FacebookGraphException $e) {
            return redirect()->back()->with('error', 'No se pudo eliminar el comentario: '.$e->getMessage());
        }

        return redirect()->back()->with('success', 'Comentario eliminado');
    }

    public function likeComment(Request $request, InstagramCommentsService $comments)
    {
        $request->validate([
            'comment_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._\-]+$/'],
            'liked' => 'required|boolean',
            'organization_id' => 'sometimes|integer|exists:organizations,id',
        ]);

        try {
            $comments->setLike($this->resolveOrgId($request), $request->input('comment_id'), $request->boolean('liked'));
        } catch (FacebookGraphException $e) {
            return redirect()->back()->with('error', 'No se pudo enviar el like: '.$e->getMessage());
        }

        return redirect()->back()->with('success', $request->boolean('liked') ? 'Like enviado' : 'Like retirado');
    }

    public function messages(Request $request, MessagesService $messages, InstagramService $instagram)
    {
        $orgId = Auth::user()->activeOrganizationId();

        $conversationId = trim((string) $request->query('conversation', ''));

        if (! preg_match('/^[A-Za-z0-9._\-]{1,128}$/', $conversationId)) {
            $conversationId = '';
        }

        return Inertia::render('Instagram/Messages', [
            'organization_id' => $orgId ?: null,
            'connection' => $orgId ? $this->facebookPage($orgId) : null,
            'account' => $orgId ? $instagram->account($orgId) : null,
            'conversations' => $orgId ? $messages->conversations($orgId, 'instagram') : null,
            'thread' => ($orgId && $conversationId) ? $messages->messages($orgId, $conversationId) : null,
            'conversation_id' => $conversationId ?: null,
        ]);
    }

    public function sendMessage(Request $request, MessagesService $messages)
    {
        $request->validate([
            'conversation_id' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9._\-]+$/'],
            'message' => 'required|string|max:1000',
            'organization_id' => 'sometimes|integer|exists:organizations,id',
        ]);

        try {
            $messages->send($this->resolveOrgId($request), $request->input('conversation_id'), $request->input('message'));
        } catch (FacebookGraphException $e) {
            return redirect()->back()->with('error', 'No se pudo enviar: '.$e->getMessage());
        }

        return redirect()->back()->with('success', 'Mensaje enviado');
    }

    private function facebookPage(int $orgId): ?array
    {
        return $this->facebook->pageInfo($orgId);
    }

    private function resolveOrgId(Request $request): int
    {
        $user = Auth::user();
        $orgId = (int) $request->input('organization_id', $user->activeOrganizationId());

        if (! $orgId) {
            abort(403, 'No hay una empresa activa');
        }

        if (! $user->hasRole('Super Admin') && $orgId !== (int) $user->organization_id) {
            abort(403);
        }

        return $orgId;
    }
}
