// Revisión automática de accesibilidad (axe, WCAG 2.2 A y AA) sobre datos ficticios.
// Uso: node a11y.mjs <carpeta de salida> [portal tickets dashboard] [--preferencias=t150.c]
// Recorre los enlaces que ve cada persona (una pantalla por patrón de dirección), en claro, en oscuro y a 320 px.
// Solo detecta una parte de los problemas: no sustituye la revisión con teclado y lector de pantalla.
import AxeBuilder from '@axe-core/playwright';
import fs from 'node:fs';
import path from 'node:path';
import { detenerTodo, navegador } from './lib.mjs';
import { SISTEMAS, levantar, sesion } from './sistemas.mjs';

const args = process.argv.slice(2);
const preferencias = args.find((a) => a.startsWith('--preferencias='))?.split('=')[1] ?? '';
const [salida, ...pedidos] = args.filter((a) => !a.startsWith('--'));
if (!salida) throw new Error('Uso: node a11y.mjs <carpeta de salida> [sistemas] [--preferencias=...]');
fs.mkdirSync(salida, { recursive: true });

const ETIQUETAS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
// Direcciones que cierran sesión, descargan archivos o salen del sistema.
const OMITIR = /logout|salir|export|exportar|descargar|download|attachments|adjunto|avatars|foto|ecosistema|plantilla\.|\.\w{2,5}$/i;
const MAXIMO = 90;

const patron = (ruta) => ruta.replace(/\/\d+(?=\/|$)/g, '/{id}').replace(/(\/tutoriales)\/[^/]+$/, '$1/{guia}');

async function analizar(p) {
    await p.waitForLoadState('networkidle').catch(() => {});
    await p.waitForTimeout(250);
    const r = await new AxeBuilder({ page: p }).withTags(ETIQUETAS).analyze();

    return r.violations.map((v) => ({
        regla: v.id,
        impacto: v.impact,
        ayuda: v.help,
        elementos: v.nodes.length,
        ejemplos: v.nodes.slice(0, 3).map((n) => n.target.join(' ')),
    }));
}

async function revisar(p, base, ruta) {
    await p.setViewportSize({ width: 1280, height: 800 });
    await p.emulateMedia({ colorScheme: 'light' });
    const respuesta = await p.goto(ruta);
    const tipo = respuesta?.headers()['content-type'] ?? '';
    if (!respuesta || respuesta.status() >= 400 || !tipo.includes('text/html')) return null;

    const estructura = await p.evaluate(() => ({
        titulo: document.title,
        idioma: document.documentElement.lang,
        h1: document.querySelectorAll('h1').length,
        salto: Boolean(document.querySelector('a[href="#contenido"], a[href="#main-content"], a[href="#main"]')),
        enlaces: [...document.querySelectorAll('a[href]')].map((a) => a.href),
    }));
    const claro = await analizar(p);

    await p.emulateMedia({ colorScheme: 'dark' });
    await p.reload();
    const oscuro = await analizar(p);

    await p.emulateMedia({ colorScheme: 'light' });
    await p.setViewportSize({ width: 320, height: 700 });
    await p.reload();
    await p.waitForLoadState('networkidle').catch(() => {});
    const desborde = await p.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);

    const { enlaces, ...resto } = estructura;

    return { enlaces, pantalla: { ruta: patron(new URL(p.url()).pathname), ...resto, desborde320: Math.max(0, desborde), claro, oscuro } };
}

async function recorrer(browser, sistema, base) {
    const s = SISTEMAS[sistema];
    const pantallas = [];
    const omitidas = [];

    for (const [quien, alias] of Object.entries(s.personas)) {
        const p = await sesion(browser, base, alias);
        if (preferencias) await p.context().addCookies([{ name: 'a11y', value: preferencias, url: base }]);
        const cola = alias ? [new URL(p.url()).pathname] : [...s.inicios];
        const vistos = new Set(cola.map(patron));

        while (cola.length && vistos.size <= MAXIMO) {
            const ruta = cola.shift();
            let r = null;
            try {
                r = await revisar(p, base, ruta);
            } catch (e) {
                omitidas.push(`${quien} ${ruta}: ${e.message.split('\n')[0]}`);
            }
            if (!r) continue;
            pantallas.push({ persona: quien, ...r.pantalla });

            for (const href of r.enlaces) {
                const u = new URL(href);
                if (u.origin !== base || OMITIR.test(u.pathname) || vistos.has(patron(u.pathname))) continue;
                // Sin sesión solo se visitan las direcciones públicas indicadas y las guías.
                if (!alias && !u.pathname.startsWith('/tutoriales')) continue;
                vistos.add(patron(u.pathname));
                cola.push(u.pathname);
            }
        }
        await p.context().close();
        console.log(`  ${quien}: ${pantallas.filter((x) => x.persona === quien).length} pantallas`);
    }

    return { pantallas, omitidas };
}

function resumen(sistema, { pantallas, omitidas }) {
    const unicas = new Map(pantallas.map((p) => [p.ruta, p]));
    const reglas = new Map();
    for (const p of pantallas) {
        for (const modo of ['claro', 'oscuro']) {
            for (const v of p[modo]) {
                const r = reglas.get(v.regla) ?? { ...v, rutas: new Set(), modos: new Set(), elementos: 0 };
                r.rutas.add(p.ruta);
                r.modos.add(modo);
                r.elementos += v.elementos;
                reglas.set(v.regla, r);
            }
        }
    }
    const orden = { critical: 0, serious: 1, moderate: 2, minor: 3 };
    const filas = [...reglas.values()].sort((a, b) => orden[a.impacto] - orden[b.impacto] || b.rutas.size - a.rutas.size);
    const lista = [...unicas.values()];
    const titulos = new Set(lista.map((p) => p.titulo));

    return [
        `## ${sistema}`,
        '',
        `- Pantallas distintas: ${unicas.size} (${pantallas.length} revisiones por persona).`,
        `- Títulos distintos: ${titulos.size}. Sin exactamente un h1: ${lista.filter((p) => p.h1 !== 1).length}. Sin enlace de salto: ${lista.filter((p) => !p.salto).length}.`,
        `- Con desplazamiento horizontal a 320 px: ${lista.filter((p) => p.desborde320 > 1).map((p) => p.ruta).join(', ') || 'ninguna'}.`,
        ...(omitidas.length ? [`- No revisadas: ${omitidas.join('; ')}`] : []),
        '',
        '| Impacto | Regla | Pantallas | Modos | Elementos (suma) | Ejemplo |',
        '|---|---|---|---|---|---|',
        ...filas.map((r) => `| ${r.impacto} | ${r.regla} | ${r.rutas.size} | ${[...r.modos].join(', ')} | ${r.elementos} | \`${r.ejemplos[0]?.replaceAll('|', '\\|')}\` |`),
        '',
    ].join('\n');
}

const browser = await navegador();
const partes = [`# Revisión automática (${new Date().toISOString().slice(0, 10)}${preferencias ? `, preferencias ${preferencias}` : ''})`, ''];
try {
    for (const sistema of pedidos.length ? pedidos : Object.keys(SISTEMAS)) {
        console.log(sistema);
        const base = await levantar(sistema);
        const datos = await recorrer(browser, sistema, base);
        fs.writeFileSync(path.join(salida, `${sistema}.json`), JSON.stringify(datos, null, 2));
        partes.push(resumen(sistema, datos));
        fs.writeFileSync(path.join(salida, 'resumen.md'), partes.join('\n'));
        detenerTodo();
    }
} finally {
    await browser.close();
    detenerTodo();
}
console.log(path.join(salida, 'resumen.md'));
