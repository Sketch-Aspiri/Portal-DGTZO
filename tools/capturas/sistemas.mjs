// Cómo levantar cada sistema con datos ficticios en bases desechables, y cómo entrar. Lo usa a11y.mjs.
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { AQUI, CLAVE, PORTAL, persona, servidor, totp } from './lib.mjs';

const SECRETO = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

// DB_URL vacío: una dirección de base en el .env no puede ganar a la base desechable.
const comun = { DB_URL: '', APP_ENV: 'local', APP_DEBUG: 'false', SESSION_DRIVER: 'file', CACHE_STORE: 'file', QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array' };

const sqlite = (nombre) => ({ DB_CONNECTION: 'sqlite', DB_DATABASE: path.join(os.tmpdir(), nombre).replaceAll('\\', '/') });

export const SISTEMAS = {
    portal: {
        dir: process.env.PORTAL_DIR ?? PORTAL,
        puerto: 8040,
        env: { ...sqlite('a11y_portal_capturas.sqlite') },
        preparar: [['artisan', 'migrate', '--force']],
        semilla: 'semillas/portal.php',
        inicios: ['/', '/login', '/register', '/forgot-password', '/tutoriales', '/accesibilidad'],
        personas: {
            visitante: null,
            admin: 'alejandra.ruiz',
            usuaria: 'laura.gomez',
            pendiente: 'patricia.leon',
        },
    },
    tickets: {
        dir: process.env.TICKETS_DIR ?? path.resolve(PORTAL, '../tickets_a11y'),
        puerto: 8041,
        env: {
            ...sqlite('a11y_tickets_capturas.sqlite'),
            DEMO_USER_PASSWORD: CLAVE, PASSWORD_CHECK_BREACHED: 'false', CLOUD_SYNC_ENABLED: 'false',
            ECOSISTEMA_SSO_SECRET: '', ECOSISTEMA_PUSH_DESTINO: 'portal',
        },
        preparar: [
            ['artisan', 'migrate', '--force'],
            ['artisan', 'db:seed', '--force'],
            ['artisan', 'db:seed', '--class=DemoDataSeeder', '--force'],
        ],
        semilla: 'semillas/tickets.php',
        inicios: ['/login', '/register', '/forgot-password'],
        personas: {
            visitante: null,
            admin: 'alejandra.ruiz',
            coordinador: 'diego.hernandez',
            empleada: 'laura.gomez',
        },
    },
    dashboard: {
        dir: process.env.DASHBOARD_DIR ?? path.resolve(PORTAL, '../dash_a11y'),
        puerto: 8042,
        env: {
            DB_CONNECTION: 'mysql', DB_HOST: '127.0.0.1', DB_PORT: process.env.CAPTURAS_MYSQL_PUERTO ?? '3398',
            DB_DATABASE: 'tutoriales_dashboard_capturas', DB_USERNAME: 'root', DB_PASSWORD: '',
            SESSION_SECURE_COOKIE: 'false', TWO_FACTOR_ENABLED: 'false', ECOSISTEMA_SSO_SECRET: '', ASISTENCIA_SHEETS_ACTIVO: 'false',
        },
        preparar: [['artisan', 'migrate:fresh', '--seed', '--force']],
        semilla: 'semillas/dashboard.php',
        inicios: ['/login'],
        personas: {
            visitante: null,
            admin: 'alejandra.ruiz',
            jefe: 'marco.pena',
            estacion: 'estacion.tulum',
            personal: 'ivan.castillo',
        },
    },
};

function entorno(nombre) {
    const s = SISTEMAS[nombre];

    return { ...process.env, ...comun, ...s.env, APP_URL: `http://127.0.0.1:${s.puerto}` };
}

/** Ejecuta PHP en la carpeta del sistema, con su base desechable. */
export function ejecutarPhp(nombre, args) {
    return execFileSync('php', args, { cwd: SISTEMAS[nombre].dir, env: entorno(nombre), stdio: 'pipe' }).toString();
}

/** Base limpia con datos de ejemplo y el servidor local arriba. Devuelve la URL base. */
export async function levantar(nombre) {
    const s = SISTEMAS[nombre];
    const base = `http://127.0.0.1:${s.puerto}`;
    const env = entorno(nombre);
    const php = (args) => ejecutarPhp(nombre, args);

    if (env.DB_CONNECTION === 'sqlite') fs.writeFileSync(env.DB_DATABASE, '');
    php(['artisan', 'config:clear']);
    php(['artisan', 'cache:clear']);
    for (const orden of s.preparar) php(orden);
    // La semilla del portal usa el proyecto donde vive; las otras reciben la carpeta del sistema.
    php([path.join(s.dir === PORTAL ? AQUI : path.join(PORTAL, 'tools/capturas'), s.semilla), s.dir]);
    await servidor('php', ['artisan', 'serve', '--no-reload', `--port=${s.puerto}`], { cwd: s.dir, env }, base);

    return base;
}

/** Abre una sesión nueva; `alias` es la parte local del correo de ejemplo (null = sin sesión). */
export async function sesion(browser, base, alias) {
    const p = await persona(browser, base);
    // Sin la burbuja del asistente, que tapa parte de las pantallas.
    await p.addInitScript(() => sessionStorage.setItem('temayin-bubble-dismissed', '1'));
    if (!alias) return p;

    await p.goto('/login');
    await p.fill('input[name="email"]', `${alias}@ejemplo.mx`);
    await p.fill('input[name="password"]', CLAVE);
    await p.locator('form[action$="/login"] button:not([type="button"])').first().click();
    await p.waitForLoadState('networkidle');
    if (p.url().includes('two-factor')) {
        await p.fill('input[name="code"]', totp(SECRETO));
        await p.locator('form:has(input[name="code"]) button:not([type="button"])').first().click();
        await p.waitForLoadState('networkidle');
    }
    if (/\/login|two-factor/.test(p.url())) throw new Error(`No se pudo entrar como ${alias}: ${p.url()}`);

    return p;
}
