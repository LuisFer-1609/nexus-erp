import { computed, ref } from 'vue';
import { chat } from '@/routes/ia';
import { cancelar, confirmar } from '@/routes/ia/acciones';
import {
    destroy as destroyConversation,
    destroyAll as destroyAllConversations,
    index as listConversations,
    show as showConversation,
} from '@/routes/ia/conversaciones';
import { destroy as destroyMessageRoute } from '@/routes/ia/conversaciones/mensajes';
import { useNexusMood } from '@/composables/useNexusMood';

export type ChatArtifact = {
    type: 'report';
    id: string;
    title: string;
    url: string;
    preview_url?: string;
    thumb_url?: string;
    size: number;
};

export type ChatAttachment = {
    id: string;
    name: string;
    mime: string;
    size: number;
    url: string;
    thumb_url?: string | null;
};

export type ChatAction = {
    id: string;
    type: string;
    title: string;
    lines: string[];
    confirmLabel: string;
    status: 'pending' | 'working' | 'done' | 'cancelled';
    note?: string;
};

export type ChatReply = {
    id: number;
    role: 'user' | 'assistant';
    text: string;
};

export type ChatMessage = {
    id: number;
    role: 'user' | 'assistant';
    text: string;
    createdAt?: string;
    error?: boolean;
    attachments?: ChatAttachment[];
    artifacts?: ChatArtifact[];
    actions?: ChatAction[];
    replyTo?: ChatReply | null;
};

export type ConversationSummary = {
    id: string;
    title: string;
    last_message_at: string | null;
    preview: string;
};

type ServerMessage = {
    id: number;
    role: 'user' | 'assistant';
    text: string;
    created_at?: string;
    reply_to?: ChatReply | null;
    attachments?: ChatAttachment[];
    artifacts?: ChatArtifact[];
    actions?: Omit<ChatAction, 'note'>[];
};

type ChatResponse = {
    message?: string;
    message_id?: number | null;
    artifacts?: ChatArtifact[];
    conversation?: { id: string; title: string; last_message_at: string };
    user_message?: ServerMessage;
    assistant_message?: ServerMessage;
};

export const MAX_FILES = 3;
export const MAX_FILE_MB = 10;
export const ACCEPTED_FILES =
    '.pdf,.png,.jpg,.jpeg,.webp,.txt,.csv,.md,application/pdf,image/png,image/jpeg,image/webp,text/plain,text/csv,text/markdown';

const ACTIVE_KEY = 'nexus-chat-active';
const MAX_LENGTH = 1000;
const REQUEST_TIMEOUT_MS = 60_000;

const readActive = (): string | null => {
    try {
        return sessionStorage.getItem(ACTIVE_KEY);
    } catch {
        return null;
    }
};

const writeActive = (id: string | null): void => {
    try {
        if (id === null) {
            sessionStorage.removeItem(ACTIVE_KEY);
        } else {
            sessionStorage.setItem(ACTIVE_KEY, id);
        }
    } catch {
        // Sin almacenamiento disponible: solo se pierde la conversación abierta al recargar.
    }
};

// Estado compartido: el círculo flotante y la pantalla completa muestran la misma
// conversación. El contenido vive en el servidor; aquí solo se recuerda cuál está abierta.
const conversations = ref<ConversationSummary[]>([]);
const conversationsLoading = ref(false);
const activeId = ref<string | null>(readActive());
const messages = ref<ChatMessage[]>([]);
const loading = ref(false);
const opening = ref(false);
const replyTo = ref<ChatMessage | null>(null);
let initialized = false;
let localId = -1;

type Attempt = { text: string; files: File[]; reply: ChatMessage | null };
let lastAttempt: Attempt | null = null;

const xsrfToken = (): string => {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
};

const jsonHeaders = (): Record<string, string> => ({
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-XSRF-TOKEN': xsrfToken(),
});

const request = async <T>(
    url: string,
    init: Omit<RequestInit, 'headers'> & {
        headers?: Record<string, string>;
    } = {},
): Promise<{ ok: boolean; status: number; data: T }> => {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...init,
        headers: { ...jsonHeaders(), ...init.headers },
    });
    const data = (await response.json().catch(() => ({}))) as T;

    return { ok: response.ok, status: response.status, data };
};

const failureMessage = (status: number, serverMessage?: string): string => {
    if (status === 401 || status === 419) {
        return 'Tu sesión expiró. Recarga la página e inicia sesión de nuevo.';
    }

    if (status === 429) {
        return 'Estás enviando mensajes muy rápido. Espera unos segundos e inténtalo de nuevo.';
    }

    return (
        serverMessage ??
        'Nexus no pudo responder en este momento. Intenta de nuevo en unos segundos.'
    );
};

const fromServer = (message: ServerMessage): ChatMessage => ({
    id: message.id,
    role: message.role,
    text: message.text,
    createdAt: message.created_at,
    replyTo: message.reply_to ?? null,
    attachments: message.attachments?.length ? message.attachments : undefined,
    artifacts: message.artifacts?.length ? message.artifacts : undefined,
    actions: message.actions?.length
        ? message.actions.map((action) => ({ ...action }))
        : undefined,
});

const pushLocal = (message: Omit<ChatMessage, 'id'>): number => {
    const id = localId--;
    messages.value.push({
        id,
        createdAt: new Date().toISOString(),
        ...message,
    });

    return id;
};

const excerpt = (text: string, limit = 160): string => {
    const flat = text.replace(/\s+/g, ' ').trim();

    return flat.length > limit ? `${flat.slice(0, limit - 1)}…` : flat;
};

const touchConversation = (
    conversation: { id: string; title: string; last_message_at: string },
    preview: string,
): void => {
    const summary: ConversationSummary = {
        id: conversation.id,
        title: conversation.title,
        last_message_at: conversation.last_message_at,
        preview: excerpt(preview, 90),
    };

    conversations.value = [
        summary,
        ...conversations.value.filter((item) => item.id !== summary.id),
    ];
};

export const validateFiles = (files: File[]): string | null => {
    if (files.length > MAX_FILES) {
        return `Puedes adjuntar hasta ${MAX_FILES} documentos por mensaje.`;
    }

    const tooBig = files.find((file) => file.size > MAX_FILE_MB * 1024 * 1024);

    if (tooBig) {
        return `«${tooBig.name}» pesa más de ${MAX_FILE_MB} MB.`;
    }

    return null;
};

const loadConversations = async (query = ''): Promise<void> => {
    conversationsLoading.value = true;

    try {
        const term = query.trim();
        const { ok, data } = await request<{
            conversations?: ConversationSummary[];
        }>(listConversations.url(term ? { query: { q: term } } : undefined));

        if (ok) {
            conversations.value = data.conversations ?? [];
        }
    } catch {
        // El historial no es crítico: el chat sigue funcionando sin él.
    } finally {
        conversationsLoading.value = false;
    }
};

const newConversation = (): void => {
    if (loading.value) {
        return;
    }

    activeId.value = null;
    messages.value = [];
    replyTo.value = null;
    lastAttempt = null;
    writeActive(null);
};

const openConversation = async (id: string): Promise<void> => {
    if (loading.value || opening.value) {
        return;
    }

    opening.value = true;
    replyTo.value = null;

    try {
        const { ok, status, data } = await request<{
            id?: string;
            messages?: ServerMessage[];
        }>(showConversation.url(id));

        if (ok && data.id) {
            activeId.value = data.id;
            messages.value = (data.messages ?? []).map(fromServer);
            writeActive(data.id);

            return;
        }

        if (status === 404) {
            // La conversación ya no existe (por ejemplo, se borró desde otra pestaña).
            conversations.value = conversations.value.filter(
                (item) => item.id !== id,
            );

            if (activeId.value === id) {
                activeId.value = null;
                messages.value = [];
                writeActive(null);
            }
        }
    } catch {
        // Se conserva lo que ya estaba en pantalla.
    } finally {
        opening.value = false;
    }
};

const initialize = (): void => {
    if (initialized) {
        return;
    }

    initialized = true;
    void loadConversations();

    if (activeId.value) {
        void openConversation(activeId.value);
    }
};

export function useNexusChat() {
    initialize();
    const mood = useNexusMood();

    const send = async (
        raw: string,
        files: File[] = [],
        reply: ChatMessage | null = replyTo.value,
    ): Promise<void> => {
        const text = raw.trim();

        if (
            (text === '' && files.length === 0) ||
            text.length > MAX_LENGTH ||
            loading.value ||
            validateFiles(files) !== null
        ) {
            return;
        }

        const replyRef: ChatReply | null =
            reply && reply.id > 0
                ? { id: reply.id, role: reply.role, text: excerpt(reply.text) }
                : null;

        lastAttempt = { text, files, reply };
        replyTo.value = null;

        const localUserId = pushLocal({
            role: 'user',
            text,
            replyTo: replyRef,
            attachments: files.length
                ? files.map((file, index) => ({
                      id: `local-${index}`,
                      name: file.name,
                      mime: file.type || 'application/octet-stream',
                      size: file.size,
                      url: URL.createObjectURL(file),
                  }))
                : undefined,
        });
        loading.value = true;
        mood.onSend(text);

        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);

        try {
            const body = new FormData();
            body.append('message', text);

            if (activeId.value) {
                body.append('conversation_id', activeId.value);
            }

            if (replyRef) {
                body.append('reply_to', String(replyRef.id));
            }

            files.forEach((file) => body.append('files[]', file));

            const { ok, status, data } = await request<ChatResponse>(
                chat.url(),
                { method: 'POST', body, signal: controller.signal },
            );

            if (ok && data.assistant_message && data.user_message) {
                const index = messages.value.findIndex(
                    (message) => message.id === localUserId,
                );
                const confirmed = fromServer(data.user_message);

                if (index >= 0) {
                    // Se conserva la vista previa local: evita que la imagen parpadee al recargarse.
                    confirmed.attachments = messages.value[index].attachments
                        ? confirmed.attachments?.map((attachment, i) => ({
                              ...attachment,
                              url:
                                  messages.value[index].attachments?.[i]?.url ??
                                  attachment.url,
                          }))
                        : confirmed.attachments;
                    messages.value.splice(index, 1, confirmed);
                }

                messages.value.push(fromServer(data.assistant_message));
                lastAttempt = null;
                mood.onReply({
                    text: data.assistant_message.text,
                    hasReport: Boolean(
                        data.assistant_message.artifacts?.length,
                    ),
                });

                if (data.conversation) {
                    activeId.value = data.conversation.id;
                    writeActive(data.conversation.id);
                    touchConversation(
                        data.conversation,
                        data.assistant_message.text,
                    );
                }
            } else {
                pushLocal({
                    role: 'assistant',
                    text: failureMessage(status, data.message),
                    error: true,
                });
                mood.onReply({ failed: true });
            }
        } catch (error) {
            const aborted =
                error instanceof DOMException && error.name === 'AbortError';

            pushLocal({
                role: 'assistant',
                text: aborted
                    ? 'Nexus tardó demasiado en responder. Intenta de nuevo.'
                    : 'No hay conexión con el servidor. Revisa tu red e inténtalo de nuevo.',
                error: true,
            });
            mood.onReply({ failed: true });
        } finally {
            clearTimeout(timer);
            loading.value = false;
        }
    };

    /** Reenvía la última pregunta (con sus archivos y la cita) tras un error. */
    const retry = async (): Promise<void> => {
        const attempt = lastAttempt;

        if (!attempt || loading.value) {
            return;
        }

        // Se quita la pregunta que no se pudo responder y el aviso de error.
        while (messages.value.length > 0) {
            const removed = messages.value.pop();

            if (removed?.role === 'user') {
                break;
            }
        }

        await send(attempt.text, attempt.files, attempt.reply);
    };

    const findAction = (
        messageId: number,
        actionId: string,
    ): ChatAction | undefined =>
        messages.value
            .find((message) => message.id === messageId)
            ?.actions?.find((action) => action.id === actionId);

    /** Confirma una acción propuesta por Nexus (por ejemplo, generar una pre-nómina). */
    const confirmAction = async (
        messageId: number,
        actionId: string,
    ): Promise<void> => {
        const action = findAction(messageId, actionId);

        if (!action || action.status !== 'pending') {
            return;
        }

        action.status = 'working';
        action.note = undefined;

        try {
            const { ok, status, data } = await request<ChatResponse>(
                confirmar.url(actionId),
                {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        conversation_id: activeId.value,
                    }),
                },
            );

            if (ok) {
                action.status = 'done';
                const message: Omit<ChatMessage, 'id'> = {
                    role: 'assistant',
                    text: data.message ?? 'Listo, la acción se completó.',
                    artifacts: data.artifacts?.length
                        ? data.artifacts
                        : undefined,
                };

                if (data.message_id) {
                    messages.value.push({
                        id: data.message_id,
                        createdAt: new Date().toISOString(),
                        ...message,
                    });
                } else {
                    pushLocal(message);
                }

                mood.onReply({
                    text: message.text,
                    hasReport: Boolean(message.artifacts?.length),
                });

                return;
            }

            action.status = 'pending';
            action.note =
                status === 422
                    ? data.message
                    : failureMessage(status, data.message);
            mood.set('worried', 2600);
        } catch {
            action.status = 'pending';
            action.note =
                'No hay conexión con el servidor. Inténtalo de nuevo.';
        }
    };

    const cancelAction = async (
        messageId: number,
        actionId: string,
    ): Promise<void> => {
        const action = findAction(messageId, actionId);

        if (!action || action.status !== 'pending') {
            return;
        }

        action.status = 'cancelled';

        try {
            await request(cancelar.url(actionId), { method: 'POST' });
        } catch {
            // La acción vence sola a los 30 minutos aunque no llegue la cancelación.
        }
    };

    const startReply = (message: ChatMessage): void => {
        if (message.id > 0 && !message.error) {
            replyTo.value = message;
        }
    };

    const cancelReply = (): void => {
        replyTo.value = null;
    };

    /** Borra un mensaje de la conversación abierta (también en el servidor). */
    const deleteMessage = async (messageId: number): Promise<boolean> => {
        const target = messages.value.find((m) => m.id === messageId);

        if (!target || loading.value) {
            return false;
        }

        // Un mensaje que nunca llegó al servidor (por ejemplo, un error) solo se quita de la vista.
        if (messageId < 0 || !activeId.value) {
            messages.value = messages.value.filter((m) => m.id !== messageId);

            return true;
        }

        try {
            const { ok, data } = await request<{
                conversation_deleted?: boolean;
            }>(
                destroyMessageRoute.url({
                    conversation: activeId.value,
                    message: messageId,
                }),
                { method: 'DELETE' },
            );

            if (!ok) {
                return false;
            }

            if (replyTo.value?.id === messageId) {
                replyTo.value = null;
            }

            if (data.conversation_deleted) {
                const removedId = activeId.value;
                conversations.value = conversations.value.filter(
                    (item) => item.id !== removedId,
                );
                activeId.value = null;
                messages.value = [];
                writeActive(null);

                return true;
            }

            messages.value = messages.value
                .filter((m) => m.id !== messageId)
                .map((m) =>
                    m.replyTo?.id === messageId ? { ...m, replyTo: null } : m,
                );

            return true;
        } catch {
            return false;
        }
    };

    const deleteConversation = async (id: string): Promise<boolean> => {
        try {
            const { ok } = await request(destroyConversation.url(id), {
                method: 'DELETE',
            });

            if (!ok) {
                return false;
            }

            conversations.value = conversations.value.filter(
                (item) => item.id !== id,
            );

            if (activeId.value === id) {
                activeId.value = null;
                messages.value = [];
                replyTo.value = null;
                writeActive(null);
            }

            return true;
        } catch {
            return false;
        }
    };

    const deleteAllConversations = async (): Promise<boolean> => {
        try {
            const { ok } = await request(destroyAllConversations.url(), {
                method: 'DELETE',
            });

            if (!ok) {
                return false;
            }

            conversations.value = [];
            activeId.value = null;
            messages.value = [];
            replyTo.value = null;
            writeActive(null);

            return true;
        } catch {
            return false;
        }
    };

    return {
        messages,
        loading,
        opening,
        conversations,
        conversationsLoading,
        activeId,
        replyTo,
        maxLength: MAX_LENGTH,
        hasMessages: computed(() => messages.value.length > 0),
        lastFailed: computed(
            () => messages.value[messages.value.length - 1]?.error === true,
        ),
        send,
        retry,
        confirmAction,
        cancelAction,
        startReply,
        cancelReply,
        deleteMessage,
        deleteConversation,
        deleteAllConversations,
        loadConversations,
        openConversation,
        newConversation,
        clear: newConversation,
    };
}
