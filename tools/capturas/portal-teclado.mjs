// Prueba con teclado, en Chrome, las pantallas propias del portal: asistente de alta, tema, calendario, confirmaciones,
// captura ampliada, buscador de tutoriales y pausa del fondo. Uso: node portal-teclado.mjs [carpeta de capturas]
import assert from 'node:assert/strict';
import fs from 'node:fs';
import http from 'node:http';
import path from 'node:path';
import { detenerTodo, navegador } from './lib.mjs';
import { SISTEMAS, levantar, sesion } from './sistemas.mjs';

const salida = process.argv[2];
if (salida) fs.mkdirSync(salida, { recursive: true });

// Catálogos ficticios para que el asistente muestre estaciones, equipos y coordinaciones.
const catalogos = {
    dashboard: { estaciones: [{ id: 5, nombre: 'Tulum' }], areas: [{ clave: 'rh', nombre: 'Recursos Humanos' }] },
    tickets: {
        equipos: [{ id: 1, nombre: 'Soporte', estacion: 'Oficina Zonal', coordinacion: 'Sistemas' }],
        coordinaciones: [{ id: 10, nombre: 'Sistemas', estacion: 'Oficina Zonal' }],
    },
};
const falso = http.createServer((req, res) => {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify(catalogos[req.url.split('/')[1]] ?? {}));
});
await new Promise((r) => falso.listen(8043, '127.0.0.1', r));
for (const [clave, nombre] of [['dashboard', 'DASHBOARD'], ['tickets', 'TICKETS']]) {
    SISTEMAS.portal.env[`ECOSISTEMA_${nombre}_URL`] = `http://127.0.0.1:8043/${clave}`;
    SISTEMAS.portal.env[`PORTAL_PUENTE_SECRETO_${nombre}`] = 'secreto-ficticio-para-pruebas-0123456789abcdef';
}
// El servidor falso vive en este proceso: PHP lo consulta mientras Node espera sin bloquearse.

const fallas = [];
const browser = await navegador();
const base = await levantar('portal');

const foco = (p) => p.evaluate(() => {
    const e = document.activeElement;
    return { tag: e.tagName, id: e.id, name: e.getAttribute('name'), texto: (e.textContent ?? '').replace(/\s+/g, ' ').trim() };
});
const espera = (p, ms = 500) => p.waitForTimeout(ms);
const abrir = async (p, ruta) => {
    await p.goto(ruta);
    await p.waitForLoadState('networkidle');
    await espera(p, 400);
};
const desborde = (p) => p.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
const foto = async (p, nombre) => salida && p.screenshot({ path: path.join(salida, `${nombre}.png`), fullPage: true });

async function prueba(nombre, alias, cuerpo) {
    let p;
    try {
        p = await sesion(browser, base, alias);
        const errores = [];
        p.on('pageerror', (e) => errores.push(e.message));
        p.on('console', (m) => m.type() === 'error' && errores.push(m.text()));
        await cuerpo(p);
        assert.equal(errores.join(' | '), '');
        console.log(`  bien: ${nombre}`);
    } catch (e) {
        fallas.push(`${nombre}: ${e.message.split('\n').slice(0, 6).join(' | ')}`);
        await foto(p, `falla-${fallas.length}`).catch(() => {});
    } finally {
        await p?.context().close();
    }
}

try {
    await prueba('asistente de alta', 'patricia.leon', async (p) => {
        await abrir(p, '/cuenta');
        const paso = () => p.locator('form[x-data] ol li[aria-current="step"]').textContent();
        const aviso = () => p.locator('[data-asistente-aviso]').textContent();
        const boton = (n, texto) => p.locator(`#wizard-step-${n}`).getByRole('button', { name: texto });
        assert.match(await paso(), /1\./);

        await p.locator('#puesto').focus();
        await p.keyboard.press('Enter');
        await espera(p);
        assert.equal((await foco(p)).id, 'puesto', 'el foco va al campo con error');
        assert.equal(await p.locator('#puesto').getAttribute('aria-invalid'), 'true');
        assert.match(await p.locator('#puesto').getAttribute('aria-describedby'), /error-puesto/);
        assert.match(await aviso(), /Escribe tu puesto/);

        await p.keyboard.type('Auxiliar de estación');
        await p.locator('#ubicacion').fill('Tulum');
        await p.keyboard.press('Enter');
        await espera(p, 700);
        assert.match(await paso(), /2\./);
        assert.equal((await foco(p)).name, 'accesos[dashboard][activo]');
        assert.match(await aviso(), /Paso 2 de 4: Sistemas/);
        assert.equal(await p.locator('#puesto[aria-invalid]').count(), 0, 'la marca se quita al avanzar');

        await boton(1, 'Continuar').focus();
        await p.keyboard.press('Enter');
        await espera(p);
        assert.equal((await foco(p)).name, 'accesos[dashboard][activo]');
        assert.match(await p.locator('[name="accesos[dashboard][activo]"]').getAttribute('aria-describedby'), /error-sistemas/);

        await boton(1, 'Atrás').focus();
        await p.keyboard.press('Enter');
        await espera(p, 700);
        assert.match(await paso(), /1\./, 'Enter en Atrás retrocede');
        assert.equal((await foco(p)).id, 'puesto');
        await boton(0, 'Continuar').focus();
        await p.keyboard.press('Enter');
        await espera(p, 700);

        for (const sistema of ['dashboard', 'tickets']) {
            await p.locator(`[name="accesos[${sistema}][activo]"]`).focus();
            await p.keyboard.press('Space');
            await espera(p);
        }
        await p.locator('[name="accesos[dashboard][rol]"][value="estacion"]').focus();
        await p.keyboard.press('Space');
        await p.locator('[name="accesos[tickets][rol]"][value="empleado"]').focus();
        await p.keyboard.press('Space');
        await boton(1, 'Continuar').focus();
        await p.keyboard.press('Enter');
        await espera(p);
        assert.equal((await foco(p)).id, 'estacion-dashboard', 'el primer error es la estación');
        await p.locator('#estacion-dashboard').selectOption('5');
        // Los equipos quedan agrupados con nombre.
        assert.equal(await p.getByRole('group', { name: 'Tus equipos' }).count(), 1);
        assert.equal(await p.getByRole('group', { name: 'Oficina Zonal › Sistemas' }).count(), 1);
        await p.getByRole('checkbox', { name: 'Soporte' }).focus();
        await p.keyboard.press('Space');
        await boton(1, 'Continuar').focus();
        await p.keyboard.press('Enter');
        await espera(p, 700);
        assert.match(await paso(), /3\./);
        assert.equal((await foco(p)).id, 'funciones');

        await p.keyboard.type('Capturo la asistencia diaria.');
        await p.keyboard.press('Enter');
        await p.keyboard.type('Y los insumos.');
        assert.match(await p.locator('#funciones').inputValue(), /\n/, 'Enter en el área de texto hace un salto');
        assert.match(await paso(), /3\./);
        await boton(2, 'Continuar').focus();
        await p.keyboard.press('Enter');
        await espera(p, 700);
        assert.match(await paso(), /4\./);
        assert.equal((await foco(p)).id, 'wizard-step-3-titulo', 'en la revisión el foco va al título');

        // «Editar» con Enter abre ese paso (antes Enter lo secuestraba).
        await p.getByRole('button', { name: /^Editar ?: Sobre ti$/ }).focus();
        await p.keyboard.press('Enter');
        await espera(p, 700);
        assert.match(await paso(), /1\./);

        // Enviar con un paso anterior incompleto: regresa, marca y enfoca, sin pantalla de carga.
        await p.locator('#puesto').fill('');
        await p.getByRole('button', { name: /^Editar ?: Revisa y envía$/ }).focus();
        await p.keyboard.press('Enter');
        await espera(p, 700);
        await boton(3, 'Enviar solicitud').focus();
        await p.keyboard.press('Enter');
        await espera(p, 900);
        assert.match(await paso(), /1\./);
        assert.equal((await foco(p)).id, 'puesto');
        assert.equal(await p.locator('#puesto').getAttribute('aria-invalid'), 'true');
        assert.equal(await p.locator('.pantalla-carga[data-estado="visible"]').count(), 0);
        await foto(p, 'asistente-error');

        // Error del servidor: el resumen lleva al campo aunque su paso esté cerrado.
        await Promise.all([
            p.waitForNavigation(),
            p.evaluate(() => {
                const form = document.querySelector('form[x-data]');
                form.querySelector('#puesto').value = 'Auxiliar';
                form.querySelector('#telefono').value = '9'.repeat(60);
                HTMLFormElement.prototype.submit.call(form);
            }),
        ]);
        await espera(p, 700);
        assert.equal(await p.evaluate(() => document.activeElement.className), 'a11y-resumen');
        assert.ok(await p.evaluate(() => document.querySelector('.a11y-resumen').getBoundingClientRect().bottom
            <= document.querySelector('form[x-data]').getBoundingClientRect().top), 'el resumen va arriba del formulario');
        await boton(0, 'Continuar').click();
        await espera(p, 700);
        assert.match(await paso(), /2\./);
        await p.locator('.a11y-resumen a').first().click();
        await espera(p, 700);
        assert.match(await paso(), /1\./);
        assert.equal((await foco(p)).id, 'telefono');

        await p.setViewportSize({ width: 320, height: 700 });
        await espera(p);
        assert.ok((await desborde(p)) <= 1, 'sin desborde a 320 px');
    });

    await prueba('selector de tema con flechas', 'laura.gomez', async (p) => {
        await abrir(p, '/notificaciones');
        await p.locator('button[aria-label="Accesibilidad"]').locator('visible=true').click();
        const grupo = p.locator('dialog[open] [role="radiogroup"]');
        const marcado = () => grupo.locator('[aria-checked="true"]').getAttribute('data-tema');
        assert.equal(await grupo.locator('[tabindex="0"]').count(), 1, 'una sola opción en el orden de Tab');
        await grupo.locator('[tabindex="0"]').focus();
        assert.equal(await marcado(), 'sistema');
        await p.keyboard.press('ArrowRight');
        assert.equal(await marcado(), 'claro');
        assert.equal(await p.evaluate(() => document.activeElement.dataset.tema), 'claro');
        await p.keyboard.press('End');
        assert.equal(await marcado(), 'oscuro');
        await p.keyboard.press('ArrowDown');
        assert.equal(await marcado(), 'sistema', 'da la vuelta');
        await p.keyboard.press('Tab');
        assert.equal(await p.evaluate(() => Boolean(document.activeElement.closest('[role="radiogroup"]'))), false, 'Tab sale del grupo');
    });

    await prueba('perfil sin foco automático', 'laura.gomez', async (p) => {
        await abrir(p, '/profile');
        assert.equal((await foco(p)).tag, 'BODY');
    });

    await prueba('calendario', 'alejandra.ruiz', async (p) => {
        // Cuatro entradas hoy para que aparezca «+N más».
        let editar;
        for (let n = 1; n <= 4; n++) {
            await abrir(p, '/calendario/eventos/nuevo');
            await p.locator('#titulo').fill(`Entrada de prueba con un nombre bastante largo ${n}`);
            const hoy = await p.evaluate(() => new Date().toLocaleDateString('en-CA', { timeZone: 'America/Cancun' }));
            await p.locator('#fecha_inicio').fill(hoy);
            await p.locator('#hora_inicio').fill(`1${n}:00`);
            await Promise.all([p.waitForNavigation(), p.locator('main form button[type="submit"]').click()]);
        }
        await abrir(p, '/calendario');
        assert.equal(await p.getByRole('table', { name: /Calendario de/ }).count(), 1);
        assert.equal(await p.getByRole('columnheader').count(), 7);
        assert.equal(await p.getByRole('columnheader', { name: 'Miércoles' }).count(), 1);
        assert.match(await p.locator('td [aria-current="date"]').textContent(), /Hoy/);
        const mas = p.locator('td summary').first();
        await mas.focus();
        await p.keyboard.press('Enter');
        await espera(p);
        assert.ok(await p.locator('td details[open] li a').first().isVisible());
        await p.keyboard.press('Tab');
        assert.equal(await p.evaluate(() => Boolean(document.activeElement.closest('td details'))), true, 'se llega con Tab a las ocultas');
        editar = await p.evaluate(() => document.activeElement.getAttribute('href'));
        await foto(p, 'calendario');

        for (const [ancho, letra] of [[320, ''], [320, 't150'], [768, 't150'], [1280, 't150']]) {
            await p.context().addCookies([{ name: 'a11y', value: letra || 'n', url: base }]);
            await p.setViewportSize({ width: ancho, height: 800 });
            await abrir(p, '/calendario');
            assert.ok((await desborde(p)) <= 1, `sin desborde a ${ancho} px ${letra}: ${await desborde(p)}`);
            await foto(p, `calendario-${ancho}${letra}`);
        }
        await p.context().addCookies([{ name: 'a11y', value: 'n', url: base }]);

        await abrir(p, editar);
        const resumen = p.locator('details[data-confirmacion] > summary');
        await resumen.focus();
        await p.keyboard.press('Enter');
        await p.keyboard.press('Tab');
        await p.keyboard.press('Escape');
        assert.equal(await p.locator('details[data-confirmacion][open]').count(), 0, 'Escape cierra la confirmación');
        assert.ok(await resumen.evaluate((s) => s === document.activeElement), 'el foco vuelve');
    });

    // El mismo código de verificación en dos pasos no sirve dos veces: se espera al siguiente.
    await new Promise((r) => setTimeout(r, 30500 - (Date.now() % 30000)));

    await prueba('confirmaciones de cuentas', 'alejandra.ruiz', async (p) => {
        await abrir(p, '/usuarios?estado=active&q=laura');
        await abrir(p, await p.locator('main a[href*="/usuarios/"]:not([href$="/nueva"])').filter({ hasText: 'Laura' }).first().getAttribute('href'));
        for (const ancho of [1280, 320]) {
            await p.setViewportSize({ width: ancho, height: 800 });
            await espera(p);
            const todas = p.locator('details[data-confirmacion]');
            const total = await todas.count();
            assert.ok(total >= 2, 'hay confirmaciones');
            for (let i = 0; i < total; i++) {
                const resumen = todas.nth(i).locator('summary').first();
                await resumen.focus();
                await p.keyboard.press('Enter');
                const caja = await todas.nth(i).locator('form').boundingBox();
                assert.ok(caja.x >= 0 && caja.x + caja.width <= ancho + 1, `confirmación ${i} dentro de la pantalla a ${ancho} px: ${JSON.stringify(caja)}`);
                await p.keyboard.press('Tab');
                await p.keyboard.press('Escape');
                assert.equal(await todas.nth(i).getAttribute('open'), null);
                assert.ok(await resumen.evaluate((s) => s === document.activeElement));
            }
        }
    });

    await prueba('captura ampliada', null, async (p) => {
        await p.setViewportSize({ width: 360, height: 640 });
        await abrir(p, '/tutoriales/registro');
        const boton = p.locator('.guia-contenido button.captura:not(.captura-celular)').first();
        await boton.focus();
        await p.keyboard.press('Enter');
        const ventana = p.getByRole('dialog');
        await ventana.waitFor();
        await espera(p, 700);
        assert.equal((await foco(p)).texto, 'Cerrar');
        await p.keyboard.press('Tab');
        assert.equal(await p.evaluate(() => document.activeElement.getAttribute('role')), 'region');
        await p.keyboard.press('ArrowRight');
        await p.keyboard.press('ArrowRight');
        await espera(p);
        assert.ok(await p.evaluate(() => document.activeElement.scrollLeft > 0), 'las flechas desplazan la imagen');
        await foto(p, 'captura-ampliada');
        await p.keyboard.press('Tab');
        assert.equal((await foco(p)).texto, 'Cerrar', 'el foco no sale');
        await p.keyboard.press('Shift+Tab');
        assert.equal(await p.evaluate(() => document.activeElement.getAttribute('role')), 'region');
        await p.keyboard.press('Escape');
        await espera(p);
        assert.equal(await ventana.count(), 0);
        assert.ok(await boton.evaluate((b) => b === document.activeElement), 'el foco vuelve a la captura');
    });

    await prueba('buscador de tutoriales', null, async (p) => {
        await abrir(p, '/tutoriales');
        const estado = p.locator('main [role="status"]').first();
        assert.equal(await estado.textContent(), '');
        await p.locator('#buscar-guia').fill('verificacion');
        await espera(p);
        assert.match(await estado.textContent(), /1 guía coincide|guías coinciden/);
        await p.locator('#buscar-guia').fill('zzzzzz');
        await espera(p);
        assert.match(await estado.textContent(), /Ninguna guía/);
    });

    await prueba('pausa del fondo', null, async (p) => {
        await p.emulateMedia({ reducedMotion: 'no-preference' });
        // Cuenta los cuadros que dibuja el fondo.
        await p.addInitScript(() => {
            const dibujar = WebGLRenderingContext.prototype.drawArrays;
            window.cuadros = 0;
            WebGLRenderingContext.prototype.drawArrays = function (...args) {
                window.cuadros += 1;
                return dibujar.apply(this, args);
            };
        });
        const cuadros = () => p.evaluate(() => window.cuadros);
        for (const [ruta, ancho] of [['/', 1280], ['/', 320], ['/login', 1280], ['/login', 320]]) {
            await p.setViewportSize({ width: ancho, height: 800 });
            await abrir(p, ruta);
            await p.evaluate(() => localStorage.removeItem('fondo-pausado'));
            await abrir(p, ruta);
            const boton = p.locator('[data-pausa-fondo]').locator('visible=true');
            assert.equal(await boton.count(), 1, `un botón de pausa en ${ruta} a ${ancho}`);
            await foto(p, `fondo${ruta === '/' ? '-inicio' : '-entrar'}-${ancho}`);
            const inicial = await cuadros();
            await espera(p);
            const seMovia = (await cuadros()) > inicial;
            await boton.focus();
            await p.keyboard.press('Enter');
            assert.equal((await boton.textContent()).trim(), 'Reanudar animación');
            if (ruta === '/') assert.equal(await p.locator('.floaty').evaluate((e) => getComputedStyle(e).animationPlayState), 'paused');
            // El lienzo deja de cambiar.
            await espera(p);
            const antes = await cuadros();
            await espera(p, 900);
            assert.equal(await cuadros(), antes, 'el fondo está quieto');
            if (seMovia) {
                await boton.press('Enter');
                await espera(p);
                assert.ok((await cuadros()) > antes, 'se reanuda');
                await boton.press('Enter');
            } else {
                console.log(`    (sin WebGL en ${ruta} a ${ancho} px: no se comprobó el lienzo en marcha)`);
            }
            await abrir(p, ruta);
            assert.equal((await p.locator('[data-pausa-fondo]').locator('visible=true').textContent()).trim(), 'Reanudar animación', 'se recuerda');
            assert.ok((await desborde(p)) <= 1);
        }
        await p.emulateMedia({ reducedMotion: 'reduce' });
        await abrir(p, '/');
        assert.equal(await p.locator('[data-pausa-fondo]').locator('visible=true').count(), 0, 'sin movimiento no hay botón');
    });

    await prueba('enlace a la declaración', null, async (p) => {
        for (const ruta of ['/', '/login', '/tutoriales']) {
            await abrir(p, ruta);
            await p.getByRole('link', { name: 'Declaración de accesibilidad' }).focus();
            await Promise.all([p.waitForNavigation(), p.keyboard.press('Enter')]);
            assert.match(p.url(), /\/accesibilidad$/);
        }
        await p.setViewportSize({ width: 320, height: 700 });
        await abrir(p, '/accesibilidad');
        assert.ok((await desborde(p)) <= 1);
    });
} finally {
    await browser.close();
    detenerTodo();
    falso.close();
}
console.log(fallas.length ? `FALLAS:\n${fallas.join('\n')}` : 'Sin problemas');
process.exitCode = fallas.length ? 1 : 0;
