// Prueba en Chrome la accesibilidad de la hoja de cálculo del Dashboard: teclado, lector de pantalla y vista de tabla.
// Uso: node hoja.mjs
import AxeBuilder from '@axe-core/playwright';
import assert from 'node:assert/strict';
import path from 'node:path';
import { AQUI, detenerTodo, navegador } from './lib.mjs';
import { SISTEMAS, ejecutarPhp, levantar, sesion } from './sistemas.mjs';

const fallas = [];
const browser = await navegador();
try {
    const base = await levantar('dashboard');
    const id = ejecutarPhp('dashboard', [path.join(AQUI, 'semillas/hoja.php'), SISTEMAS.dashboard.dir]).trim();
    const p = await sesion(browser, base, 'alejandra.ruiz');
    const errores = [];
    p.on('pageerror', (e) => errores.push(e.message));
    await p.setViewportSize({ width: 1280, height: 900 });
    await p.goto(`/secciones/${id}`);
    await p.waitForLoadState('networkidle');

    const rejilla = p.locator('.hoja-rejilla');
    const activa = () => rejilla.evaluate((r) => document.getElementById(r.getAttribute('aria-activedescendant'))?.getAttribute('aria-label') ?? null);
    const enRejilla = () => p.evaluate(() => document.activeElement.classList.contains('hoja-rejilla'));
    const estado = (texto) => p.locator('[role="status"]', { hasText: texto }).locator('visible=true').first();
    const paso = async (nombre, cuerpo) => {
        try {
            await cuerpo();
            console.log(`  bien: ${nombre}`);
        } catch (e) {
            fallas.push(`${nombre}: ${e.message.split('\n').slice(0, 5).join(' | ')}`);
            await p.keyboard.press('Escape');
        }
    };

    await p.locator('.hoja-celda').first().waitFor();

    await paso('la celda activa se anuncia', async () => {
        await rejilla.focus();
        await p.keyboard.press('Control+Home');
        await p.waitForTimeout(150);
        assert.equal(await activa(), 'A1, Equipo: Radio portátil');
        await p.keyboard.press('ArrowDown');
        await p.keyboard.press('ArrowRight');
        await p.waitForTimeout(150);
        assert.equal(await activa(), 'B2, Cantidad: 12');
    });

    await paso('editar y anunciar el guardado', async () => {
        await p.keyboard.press('F2');
        const editor = p.locator('textarea.hoja-editor[aria-label="Editar B2, Cantidad"]');
        await editor.waitFor();
        await p.waitForTimeout(150);
        await editor.fill('14');
        await p.keyboard.press('Enter');
        await estado('Guardado').waitFor();
        await p.keyboard.press('ArrowUp');
        await p.waitForTimeout(150);
        assert.equal(await activa(), 'B2, Cantidad: 14');
    });

    await paso('Tab cambia de celda y Escape + Tab sale', async () => {
        await rejilla.focus();
        await p.keyboard.press('Control+Home');
        await p.keyboard.press('Tab');
        assert.ok(await enRejilla(), 'Tab dentro de la hoja cambia de celda');
        await p.keyboard.press('Escape');
        await p.keyboard.press('Tab');
        assert.ok(!(await enRejilla()), 'Escape y Tab salen');
        await rejilla.focus();
        await p.keyboard.press('Escape');
        await p.keyboard.press('Shift+Tab');
        assert.ok(!(await enRejilla()), 'Escape y Mayús+Tab salen');
    });

    await paso('en el borde, Tab sale', async () => {
        await rejilla.focus();
        await p.keyboard.press('Control+Home');
        await p.keyboard.press('Shift+Tab');
        assert.ok(!(await enRejilla()), 'Mayús+Tab en la primera columna');
        await rejilla.focus();
        await p.keyboard.press('Tab');
        await p.keyboard.press('Tab');
        assert.ok(await enRejilla());
        await p.keyboard.press('Tab');
        assert.ok(!(await enRejilla()), 'Tab en la última columna');
    });

    await paso('menú de acciones con teclado', async () => {
        await rejilla.focus();
        await p.keyboard.press('Control+Home');
        await p.keyboard.press('Shift+F10');
        await p.waitForTimeout(300);
        const enfocado = () => p.evaluate(() => document.activeElement.textContent.trim());
        assert.equal(await enfocado(), 'Insertar filas arriba');
        await p.keyboard.press('ArrowDown');
        assert.equal(await enfocado(), 'Insertar filas abajo');
        await p.keyboard.press('End');
        assert.equal(await enfocado(), 'Ordenar hoja Z → A');
        await p.keyboard.press('Escape');
        await p.waitForTimeout(200);
        assert.ok(await enRejilla(), 'Escape devuelve el foco');
    });

    await paso('ancho de columna sin arrastrar', async () => {
        const ancho = () => p.locator('[role="columnheader"]').first().evaluate((n) => n.offsetWidth);
        const antes = await ancho();
        await rejilla.focus();
        await p.keyboard.press('Control+Home');
        await p.keyboard.press('Alt+Shift+ArrowRight');
        await p.waitForTimeout(300);
        assert.equal(await ancho(), antes + 20);
        await estado('píxeles de ancho').waitFor();
    });

    await paso('el error se queda hasta cerrarlo', async () => {
        await rejilla.focus();
        await p.keyboard.press('Control+Home');
        await p.keyboard.press('ArrowRight');
        await p.keyboard.type('abc', { delay: 120 });
        await p.keyboard.press('Enter');
        await estado('no acepta ese valor').waitFor();
        await p.waitForTimeout(7000);
        await estado('no acepta ese valor').waitFor({ timeout: 500 });
        await p.getByRole('button', { name: 'Cerrar aviso' }).click();
        await p.getByRole('button', { name: 'Cerrar aviso' }).waitFor({ state: 'hidden' });
        assert.ok(await enRejilla());
    });

    await paso('con el ratón, el foco sigue en la cuadrícula', async () => {
        const celda = p.locator('[role="gridcell"][aria-label^="A2,"]');
        await celda.click();
        assert.ok(await enRejilla(), 'clic');
        await celda.click({ button: 'right' });
        await p.waitForTimeout(300);
        await p.keyboard.press('Escape');
        await p.waitForTimeout(200);
        assert.ok(await enRejilla(), 'clic derecho y Escape');
        await p.locator('.hoja-cuerpo').evaluate((n) => n.focus());
        assert.ok(await enRejilla(), 'la zona con desplazamiento no se queda el foco');
    });

    await paso('axe en la hoja (claro y oscuro)', async () => {
        for (const modo of ['light', 'dark']) {
            await p.emulateMedia({ colorScheme: modo });
            await p.reload();
            await p.locator('.hoja-celda').first().waitFor();
            await p.waitForTimeout(500);
            const r = await new AxeBuilder({ page: p }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
            assert.equal(r.violations.map((v) => `${modo} ${v.id}: ${v.nodes[0].target.join(' ')}`).join('; '), '');
        }
        await p.emulateMedia({ colorScheme: 'light' });
    });

    await paso('árbol de accesibilidad: filas y celdas', async () => {
        const arbol = await rejilla.ariaSnapshot();
        console.log(arbol.split(/\n/).slice(0, 14).join(String.fromCharCode(10)));
        assert.match(arbol, /grid "Hoja Radios"/, arbol);
        assert.match(arbol, /columnheader "Columna A: Equipo"/, arbol);
        assert.match(arbol, /rowgroup:[^A-Za-z]+row /, arbol);
        assert.match(arbol, /gridcell "A1, Equipo: Radio portátil"/, arbol);
    });

    await paso('ver como tabla se recuerda y permite editar', async () => {
        await p.getByRole('link', { name: /Ver como tabla/ }).click();
        await p.waitForLoadState('networkidle');
        assert.equal(await p.locator('.hoja-rejilla').count(), 0);
        assert.ok(await p.getByRole('cell', { name: 'Radio portátil' }).isVisible());
        await p.goto(`/secciones/${id}`);
        assert.equal(await p.locator('.hoja-rejilla').count(), 0, 'se recuerda');
        const editar = p.locator('main table button', { hasText: 'Editar' }).first();
        await editar.click();
        await p.locator('[role="dialog"][aria-modal="true"]').locator('visible=true').waitFor();
        await p.keyboard.press('Escape');
        await p.getByRole('link', { name: 'Ver como hoja de cálculo' }).click();
        await p.locator('.hoja-celda').first().waitFor();
    });

    assert.equal(errores.join(' | '), '');
} catch (e) {
    fallas.push(e.message.split('\n').slice(0, 6).join(' | '));
} finally {
    await browser.close();
    detenerTodo();
}
console.log(fallas.length ? `FALLAS:\n${fallas.join('\n')}` : 'Sin problemas');
process.exitCode = fallas.length ? 1 : 0;
