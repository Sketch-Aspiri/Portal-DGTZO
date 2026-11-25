// Utilidades comunes para tomar las capturas de los tutoriales con el Chrome instalado.
import { chromium } from 'playwright-core';
import { spawn, execFileSync } from 'node:child_process';
import crypto from 'node:crypto';
import fs from 'node:fs';
import net from 'node:net';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

export const AQUI = path.dirname(fileURLToPath(import.meta.url));
export const PORTAL = path.resolve(AQUI, '../..');
export const CLAVE = 'Clave-Segura-2026!';

export const ESCRITORIO = { width: 1280, height: 800 };
export const CELULAR = { width: 390, height: 780 };

const procesos = [];

/** ¿Hay alguien escuchando? (solo abre la conexión: no depende de lo que conteste). */
function sondear(url) {
    const { hostname, port } = new URL(url);
    return new Promise((resolver, rechazar) => {
        const s = net.connect(Number(port), hostname, () => { s.end(); resolver(); });
        s.on('error', rechazar);
    });
}

/** Levanta un proceso en segundo plano y espera a que responda en la URL indicada. */
export async function servidor(comando, args, opciones, url) {
    const p = spawn(comando, args, { ...opciones, stdio: ['ignore', 'ignore', 'pipe'] });
    let errores = '';
    p.stderr.on('data', (d) => { errores = (errores + d).slice(-4000); });
    procesos.push(p);
    for (let i = 0; i < 100; i++) {
        if (p.exitCode !== null) break;
        try {
            await sondear(url);
            return p;
        } catch {
            await new Promise((r) => setTimeout(r, 200));
        }
    }
    throw new Error(`No respondió ${url}\n${errores}`);
}

export function detenerTodo() {
    for (const p of procesos.splice(0)) {
        if (p.exitCode !== null) continue;
        if (process.platform === 'win32') {
            try { execFileSync('taskkill', ['/pid', String(p.pid), '/t', '/f'], { stdio: 'ignore' }); } catch { /* ya terminó */ }
        } else {
            p.kill();
        }
    }
}

/** Código de 6 dígitos (TOTP) para las cuentas de prueba con verificación en dos pasos. */
export function totp(secretoBase32, momento = Date.now()) {
    const alfabeto = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';
    for (const c of secretoBase32.replace(/[\s=]/g, '').toUpperCase()) bits += alfabeto.indexOf(c).toString(2).padStart(5, '0');
    const clave = Buffer.from(bits.match(/.{8}/g).map((b) => parseInt(b, 2)));
    const contador = Buffer.alloc(8);
    contador.writeBigUInt64BE(BigInt(Math.floor(momento / 30000)));
    const h = crypto.createHmac('sha1', clave).update(contador).digest();
    const o = h[h.length - 1] & 0xf;
    return String((h.readUInt32BE(o) & 0x7fffffff) % 1_000_000).padStart(6, '0');
}

export async function navegador() {
    return chromium.launch({ channel: 'chrome', headless: true });
}

/** Contexto limpio (una persona). `celular` usa pantalla angosta y táctil. */
export async function persona(browser, base, { celular = false, conServiceWorker = false } = {}) {
    const contexto = await browser.newContext({
        baseURL: base,
        viewport: celular ? CELULAR : ESCRITORIO,
        deviceScaleFactor: celular ? 2 : 1,
        isMobile: celular,
        hasTouch: celular,
        locale: 'es-MX',
        timezoneId: 'America/Cancun',
        colorScheme: 'light',
        reducedMotion: 'reduce',
        serviceWorkers: conServiceWorker ? 'allow' : 'block',
    });
    const page = await contexto.newPage();
    page.setDefaultTimeout(15000);
    return page;
}

/**
 * Toma una captura. `resaltar`: selectores o locators que se marcan con un recuadro numerado.
 * `recortar`: selector o locator cuya zona (con margen) es lo único que se guarda. `completa`: página entera.
 */
export async function captura(page, guia, nombre, { resaltar = [], recortar = null, completa = false, expandir = false } = {}) {
    await page.waitForLoadState('networkidle');
    await page.evaluate(() => document.fonts.ready);
    await page.waitForTimeout(300);

    const locator = (s) => (typeof s === 'string' ? page.locator(s).first() : s);
    const ventana = page.viewportSize();
    if (expandir) {
        const alto = await page.evaluate(() => document.documentElement.scrollHeight);
        await page.setViewportSize({ width: ventana.width, height: Math.max(ventana.height, alto) });
        await page.waitForTimeout(300);
        completa = false;
    }
    const marcas = [].concat(resaltar);

    for (const [i, m] of marcas.entries()) {
        const el = locator(m);
        await el.waitFor();
        await el.evaluate((nodo, numero) => {
            const r = nodo.getBoundingClientRect();
            const caja = document.createElement('div');
            caja.className = '__marca';
            caja.style.cssText = `position:absolute;left:${r.left + scrollX - 5}px;top:${r.top + scrollY - 5}px;width:${r.width + 10}px;height:${r.height + 10}px;border:3px solid #e11d48;border-radius:10px;z-index:2147483647;pointer-events:none`;
            if (numero) {
                const n = document.createElement('span');
                n.textContent = numero;
                n.style.cssText = 'position:absolute;left:-12px;top:-12px;width:24px;height:24px;border-radius:50%;background:#e11d48;color:#fff;font:700 14px/24px sans-serif;text-align:center';
                caja.appendChild(n);
            }
            document.body.appendChild(caja);
        }, marcas.length > 1 ? String(i + 1) : '');
    }

    const carpeta = path.join(PORTAL, 'resources/guias/img', guia.sistema, guia.slug);
    fs.mkdirSync(carpeta, { recursive: true });
    const opciones = { path: path.join(carpeta, `${nombre}.png`), animations: 'disabled' };

    if (recortar) {
        const el = locator(recortar);
        await el.waitFor();
        const r = await el.evaluate((n) => {
            const b = n.getBoundingClientRect();
            return { x: b.left + scrollX, y: b.top + scrollY, width: b.width, height: b.height };
        });
        const margen = 20;
        const ancho = await page.evaluate(() => document.documentElement.scrollWidth);
        const x = Math.max(0, r.x - margen);
        const y = Math.max(0, r.y - margen);
        opciones.clip = { x, y, width: Math.min(ancho - x, r.width + margen * 2), height: r.height + margen * 2 };
        opciones.fullPage = true;
    } else {
        opciones.fullPage = completa;
    }

    await page.screenshot(opciones);
    if (expandir) await page.setViewportSize(ventana);
    await page.evaluate(() => document.querySelectorAll('.__marca').forEach((n) => n.remove()));
    console.log(`  ${guia.slug}/${nombre}.png`);
}

export function optimizar(sistema) {
    execFileSync('python', ['-I', path.join(AQUI, 'optimizar.py'), path.join(PORTAL, 'resources/guias/img', sistema)], { stdio: 'inherit' });
}
