// Capturas de las guías del portal.  Uso: node portal.mjs [slug ...]
import { execFileSync } from 'node:child_process';
import crypto from 'node:crypto';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { AQUI, CLAVE, PORTAL, captura, detenerTodo, navegador, optimizar, persona, servidor, totp } from './lib.mjs';
import { SISTEMAS, ejecutarPhp, levantar, sesion } from './sistemas.mjs';

const PUERTO = 8010;
const BASE = `http://127.0.0.1:${PUERTO}`;
const PUENTE = 'http://127.0.0.1:8011';
const SECRETO = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

const baseDatos = path.join(os.tmpdir(), 'tutoriales_portal_capturas.sqlite');

/** Llaves VAPID de usar y tirar, para que aparezca el botón de notificaciones. */
function llaveVapid() {
    const par = crypto.createECDH('prime256v1');
    par.generateKeys();
    return { publica: par.getPublicKey('base64url'), privada: par.getPrivateKey('base64url') };
}

const vapid = llaveVapid();
const env = {
    ...process.env,
    APP_ENV: 'local',
    APP_DEBUG: 'false',
    APP_URL: BASE,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: baseDatos,
    SESSION_DRIVER: 'file',
    CACHE_STORE: 'file',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'array',
    VAPID_PUBLIC_KEY: vapid.publica,
    VAPID_PRIVATE_KEY: vapid.privada,
    VAPID_SUBJECT: 'mailto:capturas@ejemplo.mx',
    ECOSISTEMA_DASHBOARD_URL: PUENTE,
    ECOSISTEMA_TICKETS_URL: PUENTE,
    ECOSISTEMA_SSO_SECRET: '',
    PORTAL_PUENTE_SECRETO_DASHBOARD: 'x'.repeat(40),
    PORTAL_PUENTE_SECRETO_TICKETS: 'y'.repeat(40),
    CALENDARIO_CAPAS_DASHBOARD: 'true',
};

const artisan = (...args) => execFileSync('php', ['artisan', ...args], { cwd: PORTAL, env, stdio: 'pipe' });

function reiniciarBase() {
    fs.writeFileSync(baseDatos, '');
    artisan('cache:clear');
    artisan('migrate', '--force');
    execFileSync('php', [path.join(AQUI, 'semillas/portal.php')], { cwd: PORTAL, env, stdio: 'pipe' });
}

async function entrar(page, correo) {
    await page.goto('/login');
    await page.fill('#email', correo);
    await page.fill('#password', CLAVE);
    await page.getByRole('button', { name: /iniciar sesión|log in|entrar/i }).click();
    await page.waitForLoadState('networkidle');
    if (page.url().includes('/two-factor/challenge')) {
        await page.fill('#code', totp(SECRETO));
        await page.getByRole('button', { name: 'Verificar' }).click();
        await page.waitForURL('**/inicio');
    }
}

const guias = {
    async registro(b, g) {
        const p = await persona(b, BASE);
        await p.goto('/');
        await captura(p, g, '01-portada', { resaltar: p.locator('header').getByRole('link', { name: 'Crear cuenta' }) });
        await p.goto('/register');
        await p.fill('#name', 'Mariana Torres');
        await p.fill('#email', 'mariana.torres@ejemplo.mx');
        await p.fill('#password', CLAVE);
        await p.fill('#password_confirmation', CLAVE);
        await captura(p, g, '02-formulario', { completa: true });
        await p.locator('form[action$="/register"] button:not([type="button"])').click();
        await p.waitForURL('**/login');
        await captura(p, g, '03-enviada');

        await entrar(p, 'mariana.torres@ejemplo.mx');
        await p.fill('#puesto', 'Auxiliar de estación');
        await p.fill('#ubicacion', 'Estación Tulum');
        await captura(p, g, '04-sobre-ti', { completa: true });
        await p.locator('#wizard-step-0').getByRole('button', { name: 'Continuar' }).click();
        await p.waitForTimeout(700);
        await p.locator('input[name="accesos[dashboard][activo]"]').check();
        await p.locator('input[name="accesos[dashboard][rol]"][value="estacion"]').check();
        await p.selectOption('#estacion-dashboard', { label: 'Tulum' });
        await p.selectOption('#area-dashboard', { label: 'Recursos Humanos' });
        await captura(p, g, '05-sistemas', { completa: true });
        await p.locator('#wizard-step-1').getByRole('button', { name: 'Continuar' }).click();
        await p.waitForTimeout(700);
        await p.fill('textarea[name="funciones"]', 'Capturo la asistencia diaria de la estación y doy seguimiento a los insumos.');
        await captura(p, g, '06-funciones', { completa: true });
        await p.locator('#wizard-step-2').getByRole('button', { name: 'Continuar' }).click();
        await p.waitForTimeout(700);
        await captura(p, g, '07-revisar', { completa: true, resaltar: p.getByRole('button', { name: 'Enviar solicitud' }) });
        await p.getByRole('button', { name: 'Enviar solicitud' }).click();
        await p.waitForLoadState('networkidle');
        await captura(p, g, '08-en-revision', { completa: true });
    },

    async 'iniciar-sesion'(b, g) {
        const p = await persona(b, BASE);
        await p.goto('/login');
        await p.fill('#email', 'laura.gomez@ejemplo.mx');
        await p.fill('#password', CLAVE);
        await captura(p, g, '01-entrar', { resaltar: ['#email', '#password', p.locator('form button:not([type="button"])').first()] });
        await captura(p, g, '02-olvide', { resaltar: p.locator('a[href$="/forgot-password"]') });
        await p.goto('/forgot-password');
        await p.fill('#email', 'laura.gomez@ejemplo.mx');
        await captura(p, g, '03-recuperar');
        await entrar(p, 'laura.gomez@ejemplo.mx');
        await captura(p, g, '04-mis-sistemas', { completa: true });
        await p.getByRole('button', { name: 'Laura Gómez' }).click();
        await captura(p, g, '05-salir', { resaltar: p.getByRole('link', { name: /cerrar sesión|log out|salir/i }).locator('visible=true') });
    },

    async 'verificacion-dos-pasos'(b, g) {
        const p = await persona(b, BASE);
        await entrar(p, 'sofia.navarro@ejemplo.mx');
        await p.waitForURL('**/two-factor/setup');
        await captura(p, g, '01-configurar', { completa: true });
        const secreto = (await p.locator('body').innerText()).match(/\b[A-Z2-7]{16,64}\b/)[0];
        await p.fill('#code', totp(secreto));
        await captura(p, g, '02-codigo', { resaltar: ['#code', p.getByRole('button', { name: 'Confirmar y activar' })], completa: true });
        await p.getByRole('button', { name: 'Confirmar y activar' }).click();
        await p.waitForURL('**/two-factor/recovery');
        await captura(p, g, '03-recuperacion', { completa: true });

        const q = await persona(b, BASE);
        await q.goto('/login');
        await q.fill('#email', 'alejandra.ruiz@ejemplo.mx');
        await q.fill('#password', CLAVE);
        await q.locator('form button:not([type="button"])').first().click();
        await q.waitForURL('**/two-factor/challenge');
        await q.getByText('Usar un código de recuperación').click();
        await captura(p === q ? p : q, g, '04-al-entrar', { completa: true });
    },

    async 'app-y-notificaciones'(b, g) {
        const p = await persona(b, BASE, { conServiceWorker: true });
        await p.context().grantPermissions(['notifications'], { origin: BASE });
        await entrar(p, 'laura.gomez@ejemplo.mx');
        await captura(p, g, '01-campana', { resaltar: p.locator('nav a[href$="/notificaciones"]').locator('visible=true').first() });
        await p.goto('/notificaciones');
        await captura(p, g, '02-centro', { completa: true });

        const m = await persona(b, BASE, { celular: true });
        await entrar(m, 'laura.gomez@ejemplo.mx');
        await captura(m, g, '03-celular');
    },

    async accesibilidad(b, g) {
        const p = await persona(b, BASE);
        await p.setViewportSize({ width: 1280, height: 900 });
        await entrar(p, 'laura.gomez@ejemplo.mx');
        const boton = p.getByRole('button', { name: 'Accesibilidad' }).locator('visible=true').first();
        await captura(p, g, '01-boton', { resaltar: boton });
        await boton.click();
        await captura(p, g, '02-panel');
        const panel = p.locator('dialog[open]');
        await panel.locator('input[value="125"]').check();
        await panel.locator('input[data-a11y="contraste"]').check();
        await panel.getByRole('button', { name: 'Listo' }).click();
        await captura(p, g, '03-aplicado');
        await p.context().clearCookies({ name: 'a11y' });
        await p.evaluate(() => localStorage.removeItem('a11y'));

        await p.reload();
        await p.waitForLoadState('networkidle');
        await p.keyboard.press('Tab');
        await captura(p, g, '05-saltar');
        // El aviso real aparece cinco minutos antes de que venza la sesión; aquí se abre a mano.
        await p.reload();
        await p.waitForLoadState('networkidle');
        await p.evaluate(() => document.querySelector('dialog[data-a11y-sesion]').showModal());
        await captura(p, g, '08-sesion');

        const m = await persona(b, BASE, { celular: true });
        await entrar(m, 'laura.gomez@ejemplo.mx');
        await m.locator('nav button[aria-expanded]').locator('visible=true').first().click();
        const enMenu = m.getByRole('button', { name: 'Accesibilidad' }).locator('visible=true').first();
        await enMenu.scrollIntoViewIfNeeded();
        await captura(m, g, '04-celular', { resaltar: enMenu });

        // La hoja de cálculo es del Dashboard: se levanta su copia (DASHBOARD_DIR) con datos ficticios.
        const dashboard = await levantar('dashboard');
        const seccion = ejecutarPhp('dashboard', [path.join(AQUI, 'semillas/hoja.php'), SISTEMAS.dashboard.dir]).trim();
        const d = await sesion(b, dashboard, 'alejandra.ruiz');
        await d.goto(`/secciones/${seccion}`);
        await d.locator('.hoja-celda').first().waitFor();
        // La hoja queda al principio de la ventana para que se vean sus renglones.
        const subir = async (enlace) => {
            await d.waitForLoadState('networkidle');
            await enlace.evaluate((n) => window.scrollTo(0, n.getBoundingClientRect().top + scrollY - 30));
        };
        const verTabla = d.getByRole('link', { name: /Ver como tabla/ });
        await subir(verTabla);
        await captura(d, g, '06-hoja', { resaltar: [verTabla, d.getByRole('button', { name: /^Acciones/ }).locator('visible=true').first()] });
        await verTabla.click();
        const verHoja = d.getByRole('link', { name: 'Ver como hoja de cálculo' });
        await verHoja.waitFor();
        await subir(verHoja);
        await captura(d, g, '07-tabla');
    },

    async perfil(b, g) {
        const p = await persona(b, BASE);
        await entrar(p, 'laura.gomez@ejemplo.mx');
        await p.getByRole('button', { name: 'Laura Gómez' }).click();
        await captura(p, g, '01-abrir', { resaltar: p.getByRole('link', { name: /perfil|profile/i }).locator('visible=true').first() });
        await p.goto('/profile');
        await captura(p, g, '02-perfil', { completa: true });
        await captura(p, g, '03-foto', { recortar: p.locator('form[action$="/profile/avatar"]').locator('xpath=ancestor::section[1]') });
        await captura(p, g, '04-contrasena', { recortar: p.locator('#update_password_current_password').locator('xpath=ancestor::section[1]') });
        await p.goto('/inicio');
        await captura(p, g, '05-tema', { resaltar: p.locator('nav [x-data^="selectorTema"]').locator('visible=true').first() });
    },

    async calendario(b, g) {
        const p = await persona(b, BASE);
        await entrar(p, 'laura.gomez@ejemplo.mx');
        await captura(p, g, '01-abrir', { completa: true, resaltar: p.locator('a[href$="/calendario"]').first() });
        await p.goto('/calendario');
        await captura(p, g, '02-mes', { completa: true });
        await captura(p, g, '03-filtros', { resaltar: p.getByRole('link', { name: 'Fecha límite', exact: true }).first() });
    },

    async 'calendario-administrar'(b, g) {
        const p = await persona(b, BASE);
        await entrar(p, 'alejandra.ruiz@ejemplo.mx');
        await p.goto('/calendario');
        await captura(p, g, '01-nueva', { resaltar: p.getByRole('link', { name: 'Nueva entrada' }) });
        await p.goto('/calendario/eventos/nuevo');
        await p.fill('input[name="titulo"]', 'Junta de seguridad');
        await captura(p, g, '02-formulario', { completa: true });
        await p.goto('/calendario');
        await captura(p, g, '03-editar', { completa: true, resaltar: p.locator('a[href$="/editar"]').locator('visible=true').first() });
    },

    async cuentas(b, g) {
        const p = await persona(b, BASE);
        await entrar(p, 'alejandra.ruiz@ejemplo.mx');
        await p.goto('/usuarios');
        await captura(p, g, '01-solicitudes', { completa: true, resaltar: p.getByRole('link', { name: /Carlos Ortega/ }) });
        await p.getByRole('link', { name: /Carlos Ortega/ }).click();
        await captura(p, g, '02-revisar', { completa: true, resaltar: p.getByRole('button', { name: 'Aprobar' }) });
        await p.goto('/usuarios/nueva');
        await captura(p, g, '03-crear', { completa: true });
        await p.goto('/usuarios?estado=active&q=Laura');
        await captura(p, g, '04-buscar', { resaltar: '#q' });
        await p.getByRole('link', { name: /Laura Gómez/ }).click();
        await captura(p, g, '05-administrar', { completa: true });
    },
};

const pedidas = process.argv.slice(2);
const slugs = pedidas.length ? pedidas : Object.keys(guias);

const browser = await navegador();
try {
    await servidor('php', ['-S', '127.0.0.1:8011', path.join(AQUI, 'semillas/puente_falso.php')], { cwd: AQUI, env }, PUENTE);
    reiniciarBase();
    await servidor('php', ['artisan', 'serve', '--no-reload', `--port=${PUERTO}`], { cwd: PORTAL, env }, BASE);

    for (const slug of slugs) {
        if (!guias[slug]) throw new Error(`No hay guion para «${slug}»`);
        console.log(slug);
        // Cada guía parte de los mismos datos.
        reiniciarBase();
        fs.rmSync(path.join(PORTAL, 'resources/guias/img/portal', slug), { recursive: true, force: true });
        await guias[slug](browser, { sistema: 'portal', slug });
    }
    optimizar('portal');
} finally {
    await browser.close();
    detenerTodo();
    fs.rmSync(baseDatos, { force: true });
}
