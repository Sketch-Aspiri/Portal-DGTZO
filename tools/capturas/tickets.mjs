// Capturas de las guías de Tickets.  Uso: node tickets.mjs [slug ...]
// TICKETS_DIR: copia de Tickets (rama desplegada) con vendor y `npm run build`.
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { AQUI, CLAVE, PORTAL, captura, detenerTodo, navegador, optimizar, persona, servidor, totp } from './lib.mjs';

const TICKETS = process.env.TICKETS_DIR ?? path.resolve(PORTAL, '../tickets_capturas');
const BASE = 'http://127.0.0.1:8020';
const SECRETO = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
const baseDatos = path.join(os.tmpdir(), 'tutoriales_tickets_capturas.sqlite').replaceAll('\\', '/');

const env = {
    ...process.env,
    APP_ENV: 'local', APP_DEBUG: 'false', APP_URL: BASE,
    DB_CONNECTION: 'sqlite', DB_DATABASE: baseDatos,
    SESSION_DRIVER: 'file', CACHE_STORE: 'file', QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array',
    DEMO_USER_PASSWORD: CLAVE, PASSWORD_CHECK_BREACHED: 'false', CLOUD_SYNC_ENABLED: 'false',
    ECOSISTEMA_SSO_SECRET: '',
    // Como en producción: el push de Tickets sale por el portal.
    ECOSISTEMA_PUSH_DESTINO: 'portal',
};

const php = (args) => execFileSync('php', args, { cwd: TICKETS, env, stdio: 'pipe' }).toString();

function reiniciarBase() {
    fs.writeFileSync(baseDatos, '');
    php(['artisan', 'cache:clear']);
    php(['artisan', 'migrate', '--force']);
    php(['artisan', 'db:seed', '--force']);
    php(['artisan', 'db:seed', '--class=DemoDataSeeder', '--force']);
    return JSON.parse(php([path.join(AQUI, 'semillas/tickets.php'), TICKETS]));
}

/** Sesión nueva de una persona de ejemplo (alias del correo). */
async function entrar(browser, alias, opciones) {
    const p = await persona(browser, BASE, opciones);
    // Sin la burbuja del asistente, que tapa parte de las pantallas.
    await p.addInitScript(() => sessionStorage.setItem('temayin-bubble-dismissed', '1'));
    await p.goto('/login');
    await p.fill('input[name="email"]', `${alias}@ejemplo.mx`);
    await p.fill('input[name="password"]', CLAVE);
    await p.locator('form[action$="/login"] button:not([type="button"])').click();
    await p.waitForLoadState('networkidle');
    if (p.url().includes('two-factor')) {
        await p.fill('input[name="code"]', totp(SECRETO));
        await p.locator('form:has(input[name="code"]) button:not([type="button"])').first().click();
        await p.waitForLoadState('networkidle');
    }
    if (/login|two-factor/.test(p.url())) throw new Error(`No se pudo entrar como ${alias}: ${p.url()}`);
    return p;
}

async function abrir(p, url) {
    const r = await p.goto(url);
    if (r.status() >= 400) throw new Error(`${url} respondió ${r.status()}`);
}

const enlaceMenu = (p, ruta) => p.locator(`nav a[href$="${ruta}"]`).locator('visible=true').first();
const tarjeta = (p, selector) => p.locator(selector).first().locator('xpath=ancestor::section[1]');

const guias = {
    async 'tickets-primeros-pasos'(b, g) {
        const p = await entrar(b, 'laura.gomez');
        await abrir(p, '/dashboard');
        await captura(p, g, '01-inicio', { completa: true });
        await captura(p, g, '02-menu', { resaltar: [enlaceMenu(p, '/tickets/pending'), enlaceMenu(p, '/tickets'), enlaceMenu(p, '/notifications')] });
        await abrir(p, '/tickets/pending');
        await captura(p, g, '03-pendientes', { completa: true });
        await abrir(p, '/notifications');
        await captura(p, g, '04-notificaciones', { completa: true });
        await abrir(p, '/profile');
        await captura(p, g, '05-perfil', { completa: true });
        const m = await entrar(b, 'laura.gomez', { celular: true });
        await abrir(m, '/tickets/pending');
        await captura(m, g, '06-celular');
    },

    async 'tickets-solicitudes'(b, g, ids) {
        const p = await entrar(b, 'laura.gomez');
        await abrir(p, '/tickets');
        await captura(p, g, '01-lista', { completa: true, resaltar: p.locator('a[href*="/tickets/create"]').locator('visible=true').last() });
        await abrir(p, '/tickets/create');
        await p.fill('input[name="title"]', 'Falla en la impresora de la oficina');
        await p.fill('textarea[name="description"]', 'La impresora marca error de papel desde la mañana.');
        await captura(p, g, '02-nuevo', { completa: true });
        await p.locator('main form:has(input[name="title"]) button[type="submit"]').click();
        await p.waitForURL(/\/tickets\/\d+$/);
        await captura(p, g, '03-creado', { completa: true });
        await abrir(p, `/tickets/${ids.bolsa}`);
        await captura(p, g, '04-tomar', { resaltar: p.locator('form[action$="/take"] button').first() });
    },

    async 'tickets-avance'(b, g, ids) {
        const p = await entrar(b, 'laura.gomez');
        await abrir(p, `/tickets/${ids.curso}`);
        await captura(p, g, '01-detalle', { completa: true });
        await captura(p, g, '02-siguiente', { resaltar: p.locator('form[action$="/transition"] button').locator('visible=true').first() });
        await captura(p, g, '03-comentar', { recortar: tarjeta(p, 'form[action$="/comments"]') });
        await captura(p, g, '04-adjuntar', { recortar: tarjeta(p, 'form[action$="/attachments"]') });
        const c = await entrar(b, 'diego.hernandez');
        await abrir(c, `/tickets/${ids.revision}`);
        await c.locator('main details > summary').first().click();
        await captura(c, g, '05-revisar', { completa: true, resaltar: c.locator('form[action$="/transition"] button').locator('visible=true').first() });
    },

    async 'tickets-asignar'(b, g, ids) {
        const p = await entrar(b, 'diego.hernandez');
        await abrir(p, `/tickets/${ids.bolsa}`);
        await captura(p, g, '01-asignar', { recortar: tarjeta(p, 'form[action$="/assignments"]') });
        await abrir(p, '/tickets/pending?scope=team');
        await captura(p, g, '02-equipo', { completa: true });
        await abrir(p, '/tickets');
        await captura(p, g, '03-exportar', { completa: true, resaltar: p.locator('a[href*="/tickets/export"]').first() });
    },

    async 'tickets-actividades'(b, g, ids) {
        const p = await entrar(b, 'diego.hernandez');
        await abrir(p, '/activities');
        await captura(p, g, '01-lista', { completa: true, resaltar: p.locator('a[href*="/activities/create"]').locator('visible=true').last() });
        await abrir(p, '/activities/create');
        await p.fill('input[name="title"]', 'Reporte semanal de limpieza');
        await p.fill('textarea[name="description"]', 'Recorrido de la estación y reporte con evidencias.');
        await captura(p, g, '02-nueva', { completa: true });
        await abrir(p, `/activities/${ids.actividad}`);
        await captura(p, g, '03-detalle', { completa: true });
        await captura(p, g, '04-subtareas', { recortar: '#subtareas' });
        await abrir(p, '/activities/templates');
        await captura(p, g, '05-plantillas', { completa: true });
    },

    async 'tickets-correos'(b, g, ids) {
        const p = await entrar(b, 'diego.hernandez');
        await abrir(p, '/incoming-emails');
        await captura(p, g, '01-bandeja', { completa: true });
        await abrir(p, `/incoming-emails/${ids.correo}`);
        await captura(p, g, '02-correo', { completa: true });
        await abrir(p, `/incoming-emails/${ids.correo}/convert`);
        await captura(p, g, '03-convertir', { completa: true });
        await abrir(p, '/inbox-settings');
        await captura(p, g, '04-mi-bandeja', { completa: true });
    },

    async 'tickets-seguimiento'(b, g) {
        const p = await entrar(b, 'marco.pena');
        await abrir(p, '/tracking');
        await p.waitForTimeout(1200);
        await captura(p, g, '01-panel', { completa: true });
        await captura(p, g, '02-filtros', { resaltar: p.locator('main form').first() });
    },

    async 'tickets-chat'(b, g, ids) {
        const p = await entrar(b, 'laura.gomez');
        await abrir(p, '/chat');
        await captura(p, g, '01-lista');
        await abrir(p, `/chat/${ids.conversacion}`);
        await captura(p, g, '02-conversacion');
    },

    async 'tickets-administracion'(b, g, ids) {
        const p = await entrar(b, 'alejandra.ruiz');
        await abrir(p, '/users');
        await captura(p, g, '01-usuarios', { completa: true });
        await abrir(p, `/users/${ids.usuario}`);
        await captura(p, g, '02-usuario', { completa: true });
        await abrir(p, '/teams');
        await captura(p, g, '03-equipos', { completa: true });
        await abrir(p, '/categories');
        await captura(p, g, '04-categorias', { completa: true });
        await abrir(p, '/audit-log');
        await captura(p, g, '05-bitacora');
    },

    async 'tickets-estructura'(b, g, ids) {
        const p = await entrar(b, 'alejandra.ruiz');
        await abrir(p, '/stations');
        await captura(p, g, '01-estaciones', { completa: true });
        await abrir(p, '/coordinations');
        await captura(p, g, '02-coordinaciones', { completa: true });
        await abrir(p, '/mailboxes');
        await captura(p, g, '03-buzones', { completa: true });
        await abrir(p, `/coordinations/${ids.coordinacion}/mailbox`);
        await captura(p, g, '04-buzon', { completa: true });
    },
};

const pedidas = process.argv.slice(2);
const slugs = pedidas.length ? pedidas : Object.keys(guias);
const fallas = [];

const browser = await navegador();
try {
    reiniciarBase();
    await servidor('php', ['artisan', 'serve', '--no-reload', '--port=8020'], { cwd: TICKETS, env }, BASE);

    for (const slug of slugs) {
        if (!guias[slug]) throw new Error(`No hay guion para «${slug}»`);
        console.log(slug);
        // Cada guía parte de los mismos datos y sin intentos de acceso acumulados.
        const ids = reiniciarBase();
        fs.rmSync(path.join(PORTAL, 'resources/guias/img/tickets', slug), { recursive: true, force: true });
        try {
            await guias[slug](browser, { sistema: 'tickets', slug }, ids);
        } catch (e) {
            fallas.push(`${slug}: ${e.message.split('\n').slice(0, 4).join(' | ')}`);
        }
        for (const c of browser.contexts()) await c.close();
    }
    optimizar('tickets');
} finally {
    await browser.close();
    detenerTodo();
    fs.rmSync(baseDatos, { force: true });
}
if (fallas.length) {
    console.error('FALLAS:\n' + fallas.join('\n'));
    process.exitCode = 1;
}
