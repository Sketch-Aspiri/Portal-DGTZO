// Capturas de las guías del Dashboard.  Uso: node dashboard.mjs [slug ...]
// DASHBOARD_DIR: copia del Dashboard (rama desplegada) con vendor y `npm run build`.
// Necesita un MySQL desechable (por omisión 127.0.0.1:3398, usuario root sin clave); ver docs/tutoriales.md.
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { AQUI, CLAVE, PORTAL, captura, detenerTodo, navegador, optimizar, persona, servidor } from './lib.mjs';

const DASHBOARD = process.env.DASHBOARD_DIR ?? path.resolve(PORTAL, '../dash_capturas');
const BASE = 'http://127.0.0.1:8030';

const env = {
    ...process.env,
    APP_ENV: 'local', APP_DEBUG: 'false', APP_URL: BASE,
    DB_CONNECTION: 'mysql', DB_HOST: '127.0.0.1', DB_PORT: process.env.CAPTURAS_MYSQL_PUERTO ?? '3398',
    DB_DATABASE: 'tutoriales_dashboard_capturas', DB_USERNAME: 'root', DB_PASSWORD: '',
    SESSION_DRIVER: 'file', SESSION_SECURE_COOKIE: 'false', CACHE_STORE: 'file', QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array',
    TWO_FACTOR_ENABLED: 'false', ECOSISTEMA_SSO_SECRET: '', ASISTENCIA_SHEETS_ACTIVO: 'false',
};

const php = (args) => execFileSync('php', args, { cwd: DASHBOARD, env, stdio: 'pipe' }).toString();

function reiniciarBase() {
    php(['artisan', 'cache:clear']);
    php(['artisan', 'migrate:fresh', '--seed', '--force']);
    return JSON.parse(php([path.join(AQUI, 'semillas/dashboard.php'), DASHBOARD]));
}

const CUENTAS = { jefe: 'marco.pena', admin: 'alejandra.ruiz', estacion: 'estacion.tulum', personal: 'ivan.castillo' };

async function entrar(browser, quien, opciones) {
    const p = await persona(browser, BASE, opciones);
    // Sin la burbuja del asistente, que tapa parte de las pantallas.
    await p.addInitScript(() => sessionStorage.setItem('temayin-bubble-dismissed', '1'));
    await p.goto('/login');
    await p.fill('input[name="email"]', `${CUENTAS[quien]}@ejemplo.mx`);
    await p.fill('input[name="password"]', CLAVE);
    await p.locator('form[action$="/login"] button:not([type="button"])').click();
    await p.waitForLoadState('networkidle');
    if (p.url().includes('/login')) throw new Error(`No se pudo entrar como ${quien}`);
    return p;
}

async function abrir(p, url) {
    const r = await p.goto(url);
    if (r.status() >= 400) throw new Error(`${url} respondió ${r.status()}`);
    await p.waitForLoadState('networkidle');
}

/** Captura una pantalla completa. */
async function pantalla(p, g, url, nombre) {
    await abrir(p, url);
    await captura(p, g, nombre, { expandir: true });
}

const guias = {
    async 'dashboard-inicio'(b, g) {
        const e = await entrar(b, 'estacion');
        await pantalla(e, g, '/dashboard', '01-inicio-estacion');
        const j = await entrar(b, 'jefe');
        await pantalla(j, g, '/dashboard', '02-inicio-zona');
        await captura(j, g, '03-menu', { resaltar: j.getByText('RR.HH.', { exact: false }).locator('visible=true').first() });
        await pantalla(e, g, '/profile', '04-perfil');
        const m = await entrar(b, 'estacion', { celular: true });
        await abrir(m, '/dashboard');
        await captura(m, g, '05-celular');
    },

    async 'dashboard-personal'(b, g, ids) {
        const p = await entrar(b, 'jefe');
        await pantalla(p, g, '/agenda/personal', '01-lista');
        await pantalla(p, g, `/agenda/personal/${ids.empleado}`, '02-ficha');
        await pantalla(p, g, '/agenda/personal/create', '03-nuevo');
    },

    async 'dashboard-asistencia'(b, g) {
        const p = await entrar(b, 'estacion');
        await p.setViewportSize({ width: 1680, height: 900 });
        await pantalla(p, g, '/asistencia/captura', '01-captura');
        await p.locator('nav[aria-label="Pasos de la captura"] button').nth(1).click();
        await captura(p, g, '02-comisionados', { expandir: true });
        await p.locator('nav[aria-label="Pasos de la captura"] button').nth(2).click();
        await captura(p, g, '03-oficio', { expandir: true });
    },

    async 'dashboard-asistencia-zona'(b, g, ids) {
        const p = await entrar(b, 'jefe');
        await pantalla(p, g, '/asistencia/zona', '01-zona');
        await pantalla(p, g, `/asistencia/zona/${ids.tulum}`, '02-estacion');
    },

    async 'dashboard-horarios'(b, g) {
        const e = await entrar(b, 'estacion');
        await pantalla(e, g, '/horarios', '01-semana');
        await pantalla(e, g, '/horarios?editar=1', '02-solicitar');
        const j = await entrar(b, 'jefe');
        await pantalla(j, g, '/horarios/solicitudes', '03-solicitudes');
        await pantalla(j, g, '/horarios/importar', '04-importar');
    },

    async 'dashboard-vacaciones'(b, g) {
        const e = await entrar(b, 'estacion');
        await pantalla(e, g, '/vacaciones', '01-rol');
        await pantalla(e, g, '/vacaciones/seleccion', '02-revision');
        const x = await entrar(b, 'personal');
        await pantalla(x, g, '/vacaciones/mi', '03-mis-vacaciones');
        const j = await entrar(b, 'jefe');
        await pantalla(j, g, '/vacaciones/seleccion/calendario', '04-calendario');
    },

    async 'dashboard-cumpleanos'(b, g) {
        const p = await entrar(b, 'estacion');
        await pantalla(p, g, '/cumpleanos', '01-cumpleanos');
    },

    async 'dashboard-insumos'(b, g, ids) {
        const e = await entrar(b, 'estacion');
        await pantalla(e, g, `/insumos/${ids.insumos.borrador}`, '01-solicitud');
        const j = await entrar(b, 'jefe');
        await pantalla(j, g, '/insumos', '02-zona');
        await pantalla(j, g, `/insumos/${ids.insumos.enviada}`, '03-revisar');
    },

    async 'dashboard-insumos-zona'(b, g, ids) {
        const p = await entrar(b, 'jefe');
        await pantalla(p, g, '/insumos/catalogo', '01-catalogo');
        await pantalla(p, g, '/insumos/proveedores', '02-proveedores');
        await pantalla(p, g, `/insumos/inventario?estacion=${ids.tulum}`, '03-inventario');
        await pantalla(p, g, '/insumos/cotizaciones/nueva', '04-cotizacion');
        await pantalla(p, g, '/insumos/facturas', '05-facturas');
    },

    async 'dashboard-papeleria'(b, g, ids) {
        const e = await entrar(b, 'estacion');
        await pantalla(e, g, '/papeleria', '01-solicitudes');
        await pantalla(e, g, `/papeleria/${ids.papeleria.borrador}`, '02-solicitud');
        const j = await entrar(b, 'jefe');
        await pantalla(j, g, `/papeleria/${ids.papeleria.enviada}`, '03-revisar');
    },

    async 'dashboard-agua'(b, g, ids) {
        const e = await entrar(b, 'estacion');
        await pantalla(e, g, `/agua/${ids.agua.borrador}`, '01-registro');
        const j = await entrar(b, 'jefe');
        await pantalla(j, g, '/agua', '02-tablero');
        await pantalla(j, g, `/agua/${ids.agua.enviada}`, '03-revisar');
    },

    async 'dashboard-afluencia'(b, g, ids) {
        const e = await entrar(b, 'estacion');
        await pantalla(e, g, `/afluencia/estaciones/${ids.tulum}`, '01-captura');
        const j = await entrar(b, 'jefe');
        await pantalla(j, g, '/afluencia', '02-tablero');
    },

    async 'dashboard-ptar'(b, g, ids) {
        const e = await entrar(b, 'estacion');
        await pantalla(e, g, `/ptar/${ids.ptar.borrador}`, '01-reporte');
        const j = await entrar(b, 'jefe');
        await pantalla(j, g, '/ptar', '02-tablero');
        await pantalla(j, g, `/ptar/${ids.ptar.enviada}`, '03-revisar');
        await pantalla(j, g, '/ptar/hallazgos', '04-hallazgos');
    },

    async 'dashboard-administracion'(b, g, ids) {
        const p = await entrar(b, 'admin');
        await pantalla(p, g, '/usuarios', '01-usuarios');
        await pantalla(p, g, `/usuarios/${ids.usuario}/editar`, '02-editar');
        await pantalla(p, g, '/equipos', '03-equipos');
        await pantalla(p, g, '/secciones/administrar', '04-menu');
    },
};

const pedidas = process.argv.slice(2);
const slugs = pedidas.length ? pedidas : Object.keys(guias);
const fallas = [];

const browser = await navegador();
try {
    reiniciarBase();
    await servidor('php', ['artisan', 'serve', '--no-reload', '--port=8030'], { cwd: DASHBOARD, env }, BASE);

    for (const slug of slugs) {
        if (!guias[slug]) throw new Error(`No hay guion para «${slug}»`);
        console.log(slug);
        const ids = reiniciarBase();
        fs.rmSync(path.join(PORTAL, 'resources/guias/img/dashboard', slug), { recursive: true, force: true });
        try {
            await guias[slug](browser, { sistema: 'dashboard', slug }, ids);
        } catch (e) {
            fallas.push(`${slug}: ${e.message.split('\n').slice(0, 4).join(' | ')}`);
        }
        for (const c of browser.contexts()) await c.close();
    }
    optimizar('dashboard');
} finally {
    await browser.close();
    detenerTodo();
}
if (fallas.length) {
    console.error('FALLAS:\n' + fallas.join('\n'));
    process.exitCode = 1;
}
