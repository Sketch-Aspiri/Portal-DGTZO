// Comprueba que los archivos compartidos por Portal, Dashboard y Tickets sigan idénticos.
// Uso: node tools/comparar_compartidos.mjs [carpetaDashboard] [carpetaTickets]
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const PORTAL = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const otros = {
    Dashboard: path.resolve(process.argv[2] ?? path.join(PORTAL, '../dash_a11y')),
    Tickets: path.resolve(process.argv[3] ?? path.join(PORTAL, '../tickets_a11y')),
};
const COMPARTIDOS = [
    'public/js/accesibilidad-inicial.js',
    'resources/js/accesibilidad.js',
    'resources/js/accesibilidad-base.js',
    'resources/views/components/accesibilidad-base.blade.php',
    'resources/views/components/saltar-contenido.blade.php',
    'resources/css/accesibilidad.css',
    'resources/views/components/panel-accesibilidad.blade.php',
    'resources/js/pantalla-carga.js',
];
const leer = (raiz, archivo) => fs.readFileSync(path.join(raiz, archivo), 'utf8').replaceAll('\r\n', '\n');

const distintos = [];
for (const archivo of COMPARTIDOS) {
    for (const [nombre, raiz] of Object.entries(otros)) {
        if (!fs.existsSync(path.join(raiz, archivo)) || leer(raiz, archivo) !== leer(PORTAL, archivo)) distintos.push(`${nombre}: ${archivo}`);
    }
}
console.log(distintos.length ? `Difieren del portal:\n${distintos.join('\n')}` : 'Archivos compartidos idénticos');
process.exitCode = distintos.length ? 1 : 0;
