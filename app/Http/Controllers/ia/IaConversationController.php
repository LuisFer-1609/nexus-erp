<?php

namespace App\Http\Controllers\ia;

use App\Http\Controllers\Controller;
use App\Models\NexusConversation;
use App\Models\User;
use App\Services\Ai\Conversations\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Historial de conversaciones del usuario: listar, abrir, borrar una conversación
 * o un mensaje. Todo se resuelve contra el usuario autenticado y su empresa.
 */
class IaConversationController extends Controller
{
    public function index(Request $request, ConversationService $conversations): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        return response()->json([
            'conversations' => $conversations->listFor($this->user($request), $data['q'] ?? null),
        ]);
    }

    public function show(Request $request, string $conversation, ConversationService $conversations): JsonResponse
    {
        $found = $this->owned($request, $conversation, $conversations);

        return response()->json([
            'id' => $found->id,
            'title' => $found->title,
            'messages' => $conversations->messagesPayload($found),
        ]);
    }

    public function destroy(Request $request, string $conversation, ConversationService $conversations): JsonResponse
    {
        $conversations->deleteConversation($this->owned($request, $conversation, $conversations));

        return response()->json(['deleted' => true]);
    }

    public function destroyAll(Request $request, ConversationService $conversations): JsonResponse
    {
        $deleted = $conversations->deleteAllFor($this->user($request));

        return response()->json(['deleted' => $deleted]);
    }

    public function destroyMessage(Request $request, string $conversation, int $message, ConversationService $conversations): JsonResponse
    {
        $found = $this->owned($request, $conversation, $conversations);
        $target = $conversations->findMessage($found, $message);
        abort_if($target === null, 404);

        return response()->json([
            'deleted' => true,
            'conversation_deleted' => $conversations->deleteMessage($found, $target),
        ]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->company_id !== null, 403);

        return $user;
    }

    private function owned(Request $request, string $id, ConversationService $conversations): NexusConversation
    {
        $conversation = $conversations->find($this->user($request), $id);
        abort_if($conversation === null, 404);

        return $conversation;
    }
}
