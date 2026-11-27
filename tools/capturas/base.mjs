// Prueba en Chrome los arreglos comunes de accesibilidad. Uso: node base.mjs [sistemas]
import assert from 'node:assert/strict';
import { detenerTodo, navegador } from './lib.mjs';
import { levantar, sesion } from './sistemas.mjs';

const pedidos = process.argv.slice(2);
const SISTEMAS = pedidos.length ? pedidos : ['portal', 'tickets', 'dashboard'];
// En Tickets la administradora tiene verificación en dos pasos: el mismo código no sirve dos veces seguidas.
const QUIEN = { portal: 'laura.gomez', tickets: 'laura.gomez', dashboard: 'marco.pena' };
const SIN_AUTOFOCO = { portal: '/notificaciones', tickets: '/notifications', dashboard: '/notificaciones' };
const fallas = [];
const browser = await navegador();

const activo = (p, fn) => p.evaluate(fn);

async function prueba(nombre, alias, base, cuerpo, { reloj = false } = {}) {
    let p;
    try {
        p = await sesion(browser, base, alias);
        const errores = [];
        p.on('pageerror', (e) => errores.push(e.message));
        if (reloj) await p.clock.install();
        await cuerpo(p);
        assert.equal(errores.join(' | '), '');
        console.log(`  bien: ${nombre}`);
    } catch (e) {
        fallas.push(`${nombre}: ${e.message.split('\n').slice(0, 4).join(' | ')}`);
    } finally {
        await p?.context().close();
    }
}

const abrir = async (p, ruta) => {
    await p.goto(ruta);
    await p.waitForLoadState('networkidle');
    await p.waitForTimeout(400);
};

try {
    for (const s of SISTEMAS) {
        console.log(s);
        const base = await levantar(s);
        const quien = QUIEN[s];

        await prueba(`${s} saltar al contenido`, quien, base, async (p) => {
            await abrir(p, SIN_AUTOFOCO[s]);
            await p.evaluate(() => document.activeElement?.blur());
            await p.keyboard.press('Tab');
            assert.equal(await activo(p, () => document.activeElement.getAttribute('href')), '#contenido');
            await p.keyboard.press('Enter');
            assert.equal(await activo(p, () => document.activeElement.id), 'contenido');
            assert.match(await p.title(), / · /);
            assert.equal(await p.locator('h1').count(), 1);
        });

        await prueba(`${s} errores de formulario`, null, base, async (p) => {
            await abrir(p, '/login');
            await Promise.all([
                p.waitForNavigation(),
                p.evaluate(() => {
                    const form = document.querySelector('form[action$="/login"]');
                    form.querySelector('[name="email"]').value = 'nadie@ejemplo.mx';
                    form.querySelector('[name="password"]').value = 'incorrecta';
                    HTMLFormElement.prototype.submit.call(form);
                }),
            ]);
            await p.waitForTimeout(600);
            assert.ok(await activo(p, () => document.activeElement.classList.contains('a11y-resumen')), 'el foco va al resumen');
            const campo = p.locator('[name="email"]');
            assert.equal(await campo.getAttribute('aria-invalid'), 'true');
            const descripcion = await campo.getAttribute('aria-describedby');
            assert.ok(descripcion, 'el campo describe su error');
            for (const id of descripcion.split(' ')) assert.equal(await p.locator(`[id="${id}"]`).count(), 1);
            await p.locator('.a11y-resumen a').first().click();
            assert.equal(await activo(p, () => document.activeElement.name), 'email');
        });

        await prueba(`${s} menú en celular`, quien, base, async (p) => {
            await p.setViewportSize({ width: 360, height: 740 });
            await abrir(p, '/profile');
            const boton = p.locator({ portal: 'button[aria-controls="menu-celular"]', tickets: 'button[aria-controls="menu-principal"]', dashboard: 'button[aria-label="Abrir menú de navegación"]' }[s]);
            assert.equal(await boton.getAttribute('aria-expanded'), 'false');
            assert.ok(await boton.getAttribute('aria-label'), 'el botón tiene nombre');
            if (s === 'tickets') assert.ok(await activo(p, () => document.querySelector('#menu-principal').inert), 'cerrado no es enfocable');
            await boton.focus();
            await p.keyboard.press('Enter');
            await p.waitForTimeout(400);
            assert.equal(await boton.getAttribute('aria-expanded'), 'true');
            if (s !== 'tickets') await p.keyboard.press('Tab');
            await p.keyboard.press('Escape');
            await p.waitForTimeout(400);
            assert.equal(await boton.getAttribute('aria-expanded'), 'false', 'Escape cierra');
            assert.ok(await boton.evaluate((b) => b === document.activeElement), 'el foco vuelve al botón');
        });

        if (s !== 'tickets') {
            await prueba(`${s} menú desplegable`, quien, base, async (p) => {
                await abrir(p, '/profile');
                const boton = p.locator('nav button[aria-expanded]', { hasText: s === 'portal' ? 'Laura' : 'Marco' }).first();
                await boton.focus();
                await p.keyboard.press('Enter');
                await p.waitForTimeout(400);
                assert.equal(await boton.getAttribute('aria-expanded'), 'true');
                await p.keyboard.press('Tab');
                await p.keyboard.press('Escape');
                await p.waitForTimeout(300);
                assert.equal(await boton.getAttribute('aria-expanded'), 'false');
                assert.ok(await boton.evaluate((b) => b === document.activeElement), 'el foco vuelve al botón');
            });
        }

        if (s !== 'portal') {
            await prueba(`${s} ventana emergente`, s === 'tickets' ? 'alejandra.ruiz' : quien, base, async (p) => {
                let boton;
                if (s === 'tickets') {
                    await abrir(p, '/users');
                    const fichas = await p.locator('main a[href*="/users/"]').evaluateAll((as) => as.map((a) => a.getAttribute('href')));
                    for (const url of [...new Set(fichas)]) {
                        await abrir(p, url);
                        boton = p.locator('[data-modal="deactivate-user"]').first();
                        if (await boton.count()) break;
                    }
                } else {
                    await abrir(p, '/profile');
                    boton = p.locator('main button', { hasText: /Delete Account|Eliminar cuenta/ }).first();
                }
                assert.ok(await boton.count(), 'hay un botón que abre la ventana');
                await boton.focus();
                await p.keyboard.press('Enter');
                const ventana = p.locator('[role="dialog"][aria-modal="true"]').locator('visible=true');
                await ventana.waitFor();
                await p.waitForTimeout(600);
                const dentro = () => activo(p, () => Boolean(document.activeElement.closest('[role="dialog"][aria-modal="true"]')));
                assert.ok(await dentro(), 'el foco entra');
                for (let i = 0; i < 8; i++) {
                    await p.keyboard.press(i % 3 ? 'Tab' : 'Shift+Tab');
                    assert.ok(await dentro(), 'el foco no sale');
                }
                await p.keyboard.press('Escape');
                await p.waitForTimeout(700);
                assert.equal(await ventana.count(), 0, 'Escape cierra');
                assert.ok(await boton.evaluate((b) => b === document.activeElement), 'el foco vuelve');
            });
        }

        await prueba(`${s} aviso de sesión`, quien, base, async (p) => {
            await abrir(p, '/profile');
            const aviso = p.locator('dialog[data-a11y-sesion]');
            assert.equal(await aviso.getAttribute('open'), null);
            await p.clock.fastForward('01:56:00');
            await p.locator('dialog[data-a11y-sesion][open]').waitFor();
            assert.ok(await aviso.locator('[data-sesion-entrar]').isHidden(), 'con la sesión viva no se ofrece volver a entrar');
            await aviso.getByRole('button', { name: 'Seguir conectado' }).click();
            await p.locator('dialog[data-a11y-sesion][open]').waitFor({ state: 'detached', timeout: 3000 }).catch(() => {});
            assert.equal(await p.locator('dialog[data-a11y-sesion][open]').count(), 0, 'continuar cierra el aviso');
        }, { reloj: true });

        detenerTodo();
    }
} finally {
    await browser.close();
    detenerTodo();
}
console.log(fallas.length ? `FALLAS:\n${fallas.join('\n')}` : 'Sin problemas');
process.exitCode = fallas.length ? 1 : 0;
