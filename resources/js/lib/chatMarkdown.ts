const escapeHtml = (value: string): string =>
    value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

const inline = (value: string): string =>
    value
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/`([^`]+)`/g, '<code>$1</code>');

/**
 * Convierte el texto de una respuesta en HTML sencillo (párrafos, listas,
 * negritas y código). Todo el contenido se escapa ANTES de dar formato, de
 * modo que el texto del modelo nunca puede inyectar HTML.
 */
export function renderChatMarkdown(text: string): string {
    const html: string[] = [];
    let items: string[] = [];

    const flushList = (): void => {
        if (items.length > 0) {
            html.push(
                `<ul>${items.map((item) => `<li>${item}</li>`).join('')}</ul>`,
            );
            items = [];
        }
    };

    for (const rawLine of escapeHtml(text).split('\n')) {
        const line = rawLine.trim();
        const bullet = /^[-*•]\s+(.*)$/.exec(line);

        if (bullet) {
            items.push(inline(bullet[1]));
            continue;
        }

        flushList();

        if (line !== '') {
            html.push(`<p>${inline(line)}</p>`);
        }
    }

    flushList();

    return html.join('');
}
