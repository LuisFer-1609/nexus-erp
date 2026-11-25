<?php

namespace App\Http\Controllers\ia;

use App\Http\Controllers\Controller;
use App\Models\AiPendingAction;
use App\Models\User;
use App\Services\Ai\Actions\PendingActionService;
use App\Services\Ai\Conversations\ConversationService;
use App\Services\Ai\Tools\InvalidToolCallException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Confirmación o cancelación de las acciones que Nexus propone. Solo el usuario
 * al que se le propuso la acción, dentro de su empresa, puede resolverla.
 */
class IaActionController extends Controller
{
    public function confirm(Request $request, AiPendingAction $action, PendingActionService $actions, ConversationService $conversations): JsonResponse
    {
        $user = $this->authorizeAction($request, $action);

        try {
            $result = $actions->confirm($action, $user->name);
        } catch (InvalidToolCallException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);
            Log::warning('Nexus IA: falló una acción confirmada: '.$e->getMessage());

            return response()->json(['message' => 'No se pudo completar la acción. Intenta de nuevo o revisa los datos.'], 500);
        }

        $report = $result['report'];
        $artifacts = $report === null ? [] : [$report->toArtifact()];

        // Si la acción salió de una conversación guardada, el resultado queda en su historial.
        $messageId = null;
        $conversationId = $request->input('conversation_id');
        $conversation = is_string($conversationId) ? $conversations->find($user, $conversationId) : null;
        if ($conversation !== null) {
            $messageId = $conversations->addAssistantMessage($conversation, $result['message'], $artifacts)->id;
        }

        return response()->json([
            'message' => $result['message'],
            'message_id' => $messageId,
            'artifacts' => $artifacts,
        ]);
    }

    public function cancel(Request $request, AiPendingAction $action, PendingActionService $actions): JsonResponse
    {
        $this->authorizeAction($request, $action);

        $actions->cancel($action);

        return response()->json(['message' => 'Listo, cancelé la acción.']);
    }

    private function authorizeAction(Request $request, AiPendingAction $action): User
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User
                && $user->company_id !== null
                && $user->company_id === $action->company_id
                && $user->id === $action->user_id,
            404,
        );

        return $user;
    }
}
