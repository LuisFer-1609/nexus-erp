// Puente de WhatsApp de Nexus.
//
// Cada usuario vincula SU WhatsApp escaneando un QR (Dispositivos vinculados).
// Laravel habla con este servicio por HTTP local; el puente nunca se expone a internet.
//
//   POST   /sessions/:id/start   inicia (o reanuda) la sesión y empieza a generar el QR
//   GET    /sessions/:id         estado: disconnected | connecting | qr | connected (+ qr, phone)
//   DELETE /sessions/:id         cierra la sesión en WhatsApp y borra las credenciales
//   GET    /health
//
// Todas las rutas (salvo /health) exigen el encabezado `x-bridge-token`.
import { timingSafeEqual } from 'node:crypto';
import { mkdir, rm } from 'node:fs/promises';
import { createServer } from 'node:http';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import makeWASocket, {
    Browsers,
    DisconnectReason,
    fetchLatestBaileysVersion,
    useMultiFileAuthState,
} from '@whiskeysockets/baileys';
import pino from 'pino';
import QRCode from 'qrcode';

const here = dirname(fileURLToPath(import.meta.url));
const PORT = Number(process.env.WHATSAPP_BRIDGE_PORT ?? 3100);
const TOKEN = process.env.WHATSAPP_BRIDGE_TOKEN ?? '';
const AUTH_DIR =
    process.env.WHATSAPP_AUTH_DIR ??
    join(here, '..', 'storage', 'app', 'private', 'whatsapp');
const logger = pino({ level: process.env.WHATSAPP_LOG_LEVEL ?? 'silent' });

if (TOKEN.length < 16) {
    console.error(
        'Define WHATSAPP_BRIDGE_TOKEN (mínimo 16 caracteres) en el entorno.',
    );
    process.exit(1);
}

/** @type {Map<string, {status: string, qr: string|null, phone: string|null, socket: any, starting: boolean}>} */
const sessions = new Map();

const isValidId = (id) => /^\d{1,12}$/.test(id);

function snapshot(id) {
    const s = sessions.get(id);

    return {
        status: s?.status ?? 'disconnected',
        qr: s?.status === 'qr' ? s.qr : null,
        phone: s?.phone ?? null,
    };
}

async function start(id) {
    const existing = sessions.get(id);

    if (
        existing &&
        (existing.starting ||
            ['connecting', 'qr', 'connected'].includes(existing.status))
    ) {
        return;
    }

    const session = existing ?? {
        status: 'connecting',
        qr: null,
        phone: null,
        socket: null,
        starting: false,
    };
    session.status = 'connecting';
    session.starting = true;
    sessions.set(id, session);

    const dir = join(AUTH_DIR, id);
    await mkdir(dir, { recursive: true });
    const { state, saveCreds } = await useMultiFileAuthState(dir);
    const { version } = await fetchLatestBaileysVersion();

    const socket = makeWASocket({
        version,
        auth: state,
        logger,
        browser: Browsers.macOS('Nexus ERP'),
        printQRInTerminal: false,
        syncFullHistory: false,
        markOnlineOnConnect: false,
    });

    session.socket = socket;
    session.starting = false;
    socket.ev.on('creds.update', saveCreds);

    socket.ev.on('connection.update', async (update) => {
        if (update.qr) {
            session.qr = await QRCode.toDataURL(update.qr, {
                margin: 1,
                width: 280,
            });
            session.status = 'qr';
        }

        if (update.connection === 'open') {
            session.status = 'connected';
            session.qr = null;
            session.phone =
                (socket.user?.id ?? '').split(':')[0].split('@')[0] || null;
        }

        if (update.connection === 'close') {
            const code = update.lastDisconnect?.error?.output?.statusCode;
            session.socket = null;
            session.qr = null;

            if (code === DisconnectReason.loggedOut) {
                // El usuario cerró la sesión desde su teléfono: se borran las credenciales.
                session.status = 'disconnected';
                session.phone = null;
                await rm(dir, { recursive: true, force: true });

                return;
            }

            // Cierre transitorio (red, reinicio de WhatsApp): se reintenta solo.
            session.status = 'connecting';
            setTimeout(
                () =>
                    void start(id).catch(() => {
                        session.status = 'disconnected';
                    }),
                2000,
            );
        }
    });
}

async function stop(id) {
    const session = sessions.get(id);

    try {
        await session?.socket?.logout();
    } catch {
        // Si ya estaba cerrada, solo se limpia.
    }

    sessions.delete(id);
    await rm(join(AUTH_DIR, id), { recursive: true, force: true });
}

const authorized = (req) => {
    const given = Buffer.from(String(req.headers['x-bridge-token'] ?? ''));
    const expected = Buffer.from(TOKEN);

    return given.length === expected.length && timingSafeEqual(given, expected);
};

const send = (res, status, body) => {
    res.writeHead(status, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify(body));
};

const server = createServer(async (req, res) => {
    const url = new URL(req.url ?? '/', 'http://localhost');

    if (url.pathname === '/health') {
        return send(res, 200, { ok: true });
    }

    if (!authorized(req)) {
        return send(res, 401, { error: 'unauthorized' });
    }

    const match = /^\/sessions\/([^/]+)(?:\/(start))?$/.exec(url.pathname);

    if (!match || !isValidId(match[1])) {
        return send(res, 404, { error: 'not_found' });
    }

    const [, id, action] = match;

    try {
        if (req.method === 'POST' && action === 'start') {
            await start(id);

            return send(res, 202, snapshot(id));
        }

        if (req.method === 'GET' && !action) {
            return send(res, 200, snapshot(id));
        }

        if (req.method === 'DELETE' && !action) {
            await stop(id);

            return send(res, 200, snapshot(id));
        }
    } catch (error) {
        console.error('Error en el puente:', error?.message ?? error);

        return send(res, 500, { error: 'bridge_error' });
    }

    return send(res, 405, { error: 'method_not_allowed' });
});

// Solo escucha en la máquina local.
server.listen(PORT, '127.0.0.1', () =>
    console.log(`Puente de WhatsApp en 127.0.0.1:${PORT}`),
);
