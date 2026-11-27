// Prueba el panel de accesibilidad en un navegador real. Uso: node panel.mjs <carpeta de capturas> [sistemas]
import fs from 'node:fs';
import path from 'node:path';
import assert from 'node:assert/strict';
import { detenerTodo, navegador } from './lib.mjs';
import { SISTEMAS, levantar, sesion } from './sistemas.mjs';

const [salida, ...pedidos] = process.argv.slice(2);
if (!salida) throw new Error('Uso: node panel.mjs <carpeta> [sistemas]');
fs.mkdirSync(salida, { recursive: true });

const CON_SESION = { portal: 'laura.gomez', tickets: 'laura.gomez', dashboard: 'marco.pena' };
const clases = (p) => p.evaluate(() => document.documentElement.className);

const browser = await navegador();
const fallas = [];

async function comprobar(sistema, base, alias, { ancho, alto, oscuro }) {
    const nombre = `${sistema}-${alias ? 'sesion' : 'visitante'}-${ancho}${oscuro ? '-oscuro' : ''}`;
    const p = await sesion(browser, base, alias);
    const errores = [];
    p.on('pageerror', (e) => errores.push(e.message));
    p.on('console', (m) => m.type() === 'error' && errores.push(m.text()));
    await p.setViewportSize({ width: ancho, height: alto });
    if (oscuro) await p.emulateMedia({ colorScheme: 'dark' });
    await p.goto(alias ? p.url() : '/login');
    await p.waitForLoadState('networkidle');

    // En celular el botón está dentro del menú.
    const boton = p.locator('button[aria-haspopup="dialog"]:has-text("Accesibilidad"), button[aria-label="Accesibilidad"]').locator('visible=true');
    // En celular el botón está dentro del menú, cerrado o fuera de la pantalla.
    const mostrarBoton = async () => {
        const caja = (await boton.count()) ? await boton.first().boundingBox() : null;
        if (caja && caja.x >= 0) return;
        const menu = sistema === 'tickets' ? p.locator('button[aria-label="Abrir menú"], button[aria-label^="Abrir"]').locator('visible=true').first() : p.locator('nav button.rounded-md').locator('visible=true').last();
        await menu.click();
        await p.waitForTimeout(400);
    };
    await mostrarBoton();
    await boton.first().focus();
    await p.keyboard.press('Enter');
    const dialogo = p.locator('dialog[open]');
    await dialogo.waitFor();
    assert.ok(await p.evaluate(() => document.activeElement.closest('dialog[open]') !== null), 'el foco entra al panel');
    await p.screenshot({ path: path.join(salida, `${nombre}-panel.png`) });

    await dialogo.locator('input[value="150"]').check();
    await dialogo.locator('input[data-a11y="contraste"]').check();
    await dialogo.locator('input[data-a11y="movimiento"]').check();
    await dialogo.locator('input[data-a11y="subrayado"]').check();
    const puestas = await clases(p);
    for (const c of ['a11y-texto-150', 'a11y-contraste', 'a11y-sin-movimiento', 'a11y-subrayado']) assert.ok(puestas.includes(c), `falta ${c}`);
    const cookie = (await p.context().cookies()).find((c) => c.name === 'a11y');
    assert.equal(cookie?.value, 't150.c.m.e');
    assert.equal(await dialogo.locator('[role="status"]').textContent(), 'Preferencia guardada.');

    await p.keyboard.press('Escape');
    await dialogo.waitFor({ state: 'detached' }).catch(() => {});
    assert.equal(await p.locator('dialog[open]').count(), 0, 'Escape cierra');
    assert.ok(await p.evaluate(() => document.activeElement.getAttribute('aria-haspopup') === 'dialog'), 'el foco vuelve al botón');

    await p.reload();
    await p.waitForLoadState('networkidle');
    assert.ok((await clases(p)).includes('a11y-texto-150'), 'se conserva al recargar');
    assert.equal(await p.evaluate(() => getComputedStyle(document.documentElement).fontSize), '24px');
    const desborde = await p.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    await p.screenshot({ path: path.join(salida, `${nombre}-150.png`), fullPage: true });
    if (desborde > 1) fallas.push(`${nombre}: desborde de ${desborde}px con letra al 150 %`);

    // Impresión: la letra vuelve a su tamaño.
    await p.emulateMedia({ media: 'print' });
    assert.equal(await p.evaluate(() => getComputedStyle(document.documentElement).fontSize), '16px');
    await p.emulateMedia({ media: 'screen' });

    await mostrarBoton();
    await boton.first().click();
    await dialogo.waitFor();
    assert.ok(await dialogo.locator('input[value="150"]').isChecked(), 'el panel muestra lo guardado');
    await dialogo.getByRole('button', { name: 'Restablecer' }).click();
    assert.ok(!(await clases(p)).includes('a11y-'), 'restablecer quita todo');
    assert.ok(!(await p.context().cookies()).some((c) => c.name === 'a11y' && c.value), 'restablecer borra la cookie');

    if (errores.length) fallas.push(`${nombre}: ${errores.join(' | ')}`);
    await p.context().close();
    console.log(`  ${nombre}: bien`);
}

try {
    for (const sistema of pedidos.length ? pedidos : Object.keys(SISTEMAS)) {
        console.log(sistema);
        const base = await levantar(sistema);
        for (const [alias, opciones] of [
            [null, { ancho: 1280, alto: 800 }],
            [null, { ancho: 320, alto: 700 }],
            [CON_SESION[sistema], { ancho: 1280, alto: 800 }],
            [CON_SESION[sistema], { ancho: 360, alto: 740, oscuro: true }],
        ]) {
            try {
                await comprobar(sistema, base, alias, opciones);
            } catch (e) {
                fallas.push(`${sistema} ${alias ?? 'visitante'} ${opciones.ancho}: ${e.message.split('\n').slice(0, 5).join(' | ')}`);
                for (const c of browser.contexts()) await c.close();
            }
        }
        detenerTodo();
    }
} finally {
    await browser.close();
    detenerTodo();
}
console.log(fallas.length ? `FALLAS:\n${fallas.join('\n')}` : 'Sin problemas');
process.exitCode = fallas.length ? 1 : 0;
