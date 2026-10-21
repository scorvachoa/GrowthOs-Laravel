<?php

namespace App\Http\Controllers;

use App\Services\Facebook\AuthService;
use App\Services\Facebook\CommentsService;
use App\Services\Facebook\FacebookGraphException;
use App\Services\Facebook\MessagesService;
use App\Services\Facebook\PageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class FacebookController extends Controller
{
    public function __construct(private readonly AuthService $facebook) {}

    public function index(Request $request, PageService $pages)
    {
        $orgId = Auth::user()->activeOrganizationId();

        $days = (int) $request->query('days', 28);

        if (! in_array($days, [7, 28, 90], true)) {
            $days = 28;
        }

        return Inertia::render('Facebook/Index', [
            'organization_id' => $orgId ?: null,
            'connection' => $orgId ? $this->facebook->pageInfo($orgId) : null,
            'feed' => $orgId ? $pages->feed($orgId) : null,
            'insights' => $orgId ? $pages->insights($orgId, $days) : null,
            'days' => $orgId ? $days : null,
        ]);
    }

    public function comments(CommentsService $comments)
    {
        $orgId = Auth::user()->activeOrganizationId();

        return Inertia::render('Facebook/Comments', [
            'organization_id' => $orgId ?: null,
            'connection' => $orgId ? $this->facebook->pageInfo($orgId) : null,
            'comments' => $orgId ? $comments->threads($orgId) : null,
        ]);
    }

    public function reply(Request $request, CommentsService $comments)
    {
        $request->validate([
            'comment_id' => 'required|string|max:64',
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

    public function hideComment(Request $request, CommentsService $comments)
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

    public function deleteComment(Request $request, CommentsService $comments)
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

    public function messages(Request $request, MessagesService $messages)
    {
        $orgId = Auth::user()->activeOrganizationId();

        $conversationId = trim((string) $request->query('conversation', ''));

        if (! preg_match('/^[A-Za-z0-9._\-]{1,128}$/', $conversationId)) {
            $conversationId = '';
        }

        return Inertia::render('Facebook/Messages', [
            'organization_id' => $orgId ?: null,
            'connection' => $orgId ? $this->facebook->pageInfo($orgId) : null,
            'conversations' => $orgId ? $messages->conversations($orgId, 'messenger') : null,
            'thread' => ($orgId && $conversationId) ? $messages->messages($orgId, $conversationId) : null,
            'conversation_id' => $conversationId ?: null,
        ]);
    }

    public function send(Request $request, MessagesService $messages)
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

    public function publish(Request $request, PageService $pages)
    {
        $request->validate([
            'message' => 'required|string|max:10000',
            'link' => 'nullable|url|max:2000',
            'photo' => 'nullable|url|max:2000',
            'organization_id' => 'sometimes|integer|exists:organizations,id',
        ]);

        try {
            $pages->publish(
                $this->resolveOrgId($request),
                $request->input('message'),
                $request->input('link') ?: null,
                $request->input('photo') ?: null,
            );
        } catch (FacebookGraphException $e) {
            return redirect()->back()->with('error', 'No se pudo publicar: '.$e->getMessage());
        }

        return redirect()->back()->with('success', 'Publicado en Facebook');
    }

    public function updatePost(Request $request, PageService $pages)
    {
        $request->validate([
            'post_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._\-]+$/'],
            'message' => 'required|string|max:10000',
            'link' => 'nullable|url|max:2000',
            'organization_id' => 'sometimes|integer|exists:organizations,id',
        ]);

        try {
            $pages->updatePost(
                $this->resolveOrgId($request),
                $request->input('post_id'),
                $request->input('message'),
                $request->input('link') ?: null,
            );
        } catch (FacebookGraphException $e) {
            return redirect()->back()->with('error', 'No se pudo editar: '.$e->getMessage());
        }

        return redirect()->back()->with('success', 'Publicación actualizada');
    }

    public function deletePost(Request $request, PageService $pages)
    {
        $request->validate([
            'post_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._\-]+$/'],
            'organization_id' => 'sometimes|integer|exists:organizations,id',
        ]);

        try {
            $pages->deletePost($this->resolveOrgId($request), $request->input('post_id'));
        } catch (FacebookGraphException $e) {
            return redirect()->back()->with('error', 'No se pudo eliminar: '.$e->getMessage());
        }

        return redirect()->back()->with('success', 'Publicación eliminada');
    }

    public function connect(Request $request)
    {
        $request->validate([
            'access_token' => 'required|string|max:4096',
            'organization_id' => 'sometimes|integer|exists:organizations,id',
        ]);

        $connection = $this->facebook->connect($request->input('access_token'), $this->resolveOrgId($request));

        $message = $connection->expires_at
            ? 'Página de Facebook conectada correctamente (token de larga duración, válido hasta '.$connection->expires_at->format('d/m/Y').')'
            : 'Página de Facebook conectada correctamente (token de corta duración ~1h — pega un User Access Token para que dure 60 días)';

        return redirect()->back()->with('success', $message);
    }

    public function disconnect(Request $request)
    {
        $this->facebook->disconnect($this->resolveOrgId($request));

        return redirect()->back()->with('success', 'Página de Facebook desconectada');
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
