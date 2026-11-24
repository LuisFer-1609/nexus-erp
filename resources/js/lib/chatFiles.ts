export type FileKind = 'image' | 'pdf' | 'sheet' | 'text';

export type ViewerItem = {
    url: string;
    name: string;
    mime: string;
    /** Enlace de descarga si es distinto al de la vista previa (por ejemplo, un reporte). */
    downloadUrl?: string;
    /** Primera página ya renderizada (PDF): se muestra mientras carga el documento. */
    thumb?: string | null;
};

export const fileKind = (mime: string, name = ''): FileKind => {
    const lower = name.toLowerCase();

    if (mime.startsWith('image/')) {
        return 'image';
    }

    if (mime === 'application/pdf' || lower.endsWith('.pdf')) {
        return 'pdf';
    }

    if (mime === 'text/csv' || lower.endsWith('.csv')) {
        return 'sheet';
    }

    return 'text';
};

const BADGES: Record<FileKind, { label: string; classes: string }> = {
    image: { label: 'IMG', classes: 'bg-sky-600 text-white' },
    pdf: { label: 'PDF', classes: 'bg-rose-600 text-white' },
    sheet: { label: 'CSV', classes: 'bg-emerald-600 text-white' },
    text: { label: 'TXT', classes: 'bg-slate-500 text-white' },
};

export const fileBadge = (
    mime: string,
    name = '',
): { label: string; classes: string } => {
    const kind = fileKind(mime, name);
    const extension = name.includes('.')
        ? (name.split('.').pop() ?? '').toUpperCase()
        : '';

    if (kind === 'text' && extension !== '' && extension.length <= 4) {
        return { ...BADGES.text, label: extension };
    }

    return BADGES[kind];
};

export const formatSize = (bytes: number): string => {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    return bytes < 1024 * 1024
        ? `${Math.max(1, Math.round(bytes / 1024))} kB`
        : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
};

const timeFormatter = new Intl.DateTimeFormat('es-MX', {
    hour: 'numeric',
    minute: '2-digit',
});

export const formatTime = (iso?: string): string => {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);

    return Number.isNaN(date.getTime()) ? '' : timeFormatter.format(date);
};
