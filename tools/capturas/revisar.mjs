// Recorre la sección de tutoriales con datos de prueba y guarda vistas para revisarla. Uso: node revisar.mjs <carpeta>
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { AQUI, CLAVE, PORTAL, detenerTodo, navegador, persona, servidor, totp } from './lib.mjs';

const salida = process.argv[2] ?? path.join(os.tmpdir(), 'tutoriales_revision');
fs.mkdirSync(salida, { recursive: true });

const BASE = 'http://127.0.0.1:8012';
const baseDatos = path.join(os.tmpdir(), 'tutoriales_revision_capturas.sqlite');
const env = {
    ...process.env,
    APP_ENV: 'local', APP_DEBUG: 'false', APP_URL: BASE,
    DB_CONNECTION: 'sqlite', DB_DATABASE: baseDatos,
    SESSION_DRIVER: 'file', CACHE_STORE: 'file', QUEUE_CONNECTION: 'sync', MAIL_MAILER: 'array',
};

const browser = await navegador();
const problemas = [];
try {
    fs.writeFileSync(baseDatos, '');
    execFileSync('php', ['artisan', 'migrate', '--force'], { cwd: PORTAL, env, stdio: 'pipe' });
    execFileSync('php', [path.join(AQUI, 'semillas/portal.php')], { cwd: PORTAL, env, stdio: 'pipe' });
    await servidor('php', ['artisan', 'serve', '--no-reload', '--port=8012'], { cwd: PORTAL, env }, BASE);

    async function recorrer(nombre, correo, opciones = {}) {
        const p = await persona(browser, BASE, opciones);
        p.on('pageerror', (e) => problemas.push(`${nombre}: ${e.message}`));
        p.on('console', (m) => m.type() === 'error' && problemas.push(`${nombre}: ${m.text()}`));
        if (opciones.oscuro) await p.emulateMedia({ colorScheme: 'dark' });
        if (correo) {
            await p.goto('/login');
            await p.fill('#email', correo);
            await p.fill('#password', CLAVE);
            await p.locator('form button:not([type="button"])').first().click();
            await p.waitForLoadState('networkidle');
            if (p.url().includes('/two-factor/challenge')) {
                await p.fill('#code', totp('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP'));
                await p.getByRole('button', { name: 'Verificar' }).click();
            }
            await p.waitForURL('**/inicio');
        }
        await p.goto('/tutoriales');
        await p.waitForLoadState('networkidle');
        await p.screenshot({ path: path.join(salida, `${nombre}-lista.png`), fullPage: true });
        const guias = await p.locator('a[href*="/tutoriales/"]').evaluateAll((as) => [...new Set(as.map((a) => a.getAttribute('href')))]);

        // Todas las guías visibles abren y sus capturas cargan.
        for (const url of guias) {
            const r = await p.goto(url);
            await p.waitForLoadState('networkidle');
            const rotas = await p.evaluate(async () => {
                const imagenes = [...document.querySelectorAll('.guia-contenido img')];
                imagenes.forEach((i) => { i.loading = 'eager'; });
                await Promise.all(imagenes.map((i) => i.decode().catch(() => {})));
                return imagenes.filter((i) => !i.naturalWidth).length;
            });
            const desborde = await p.evaluate(() => document.documentElement.scrollWidth - innerWidth);
            if (r.status() !== 200 || rotas || desborde > 1) problemas.push(`${nombre} ${url}: ${r.status()}, ${rotas} rotas, desborde ${desborde}px`);
        }

        // Buscador y ampliación de una captura.
        await p.goto('/tutoriales');
        await p.fill('#buscar-guia', 'contrasena');
        await p.screenshot({ path: path.join(salida, `${nombre}-busqueda.png`) });
        await p.goto(guias[0]);
        await p.waitForLoadState('networkidle');
        await p.screenshot({ path: path.join(salida, `${nombre}-guia.png`) });
        await p.locator('[data-captura]').first().click();
        await p.waitForTimeout(500);
        await p.screenshot({ path: path.join(salida, `${nombre}-ampliada.png`) });
        await p.keyboard.press('Escape');
        console.log(`${nombre}: ${guias.length} guías visibles`);
        await p.context().close();
    }

    await recorrer('administradora', 'alejandra.ruiz@ejemplo.mx');
    await recorrer('visitante', null);
    await recorrer('usuaria-celular', 'laura.gomez@ejemplo.mx', { celular: true });
    await recorrer('usuario-oscuro', 'diego.hernandez@ejemplo.mx', { oscuro: true });
} finally {
    await browser.close();
    detenerTodo();
    fs.rmSync(baseDatos, { force: true });
}
console.log(problemas.length ? problemas.join('\n') : 'Sin problemas');
console.log(salida);
