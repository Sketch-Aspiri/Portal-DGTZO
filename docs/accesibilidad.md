# Accesibilidad: Portal, Dashboard y Tickets

Meta: **WCAG 2.2, nivel AA**, en los tres sistemas. Estado a 2026-11-27: **en trabajo; todavía no se declara
conformidad**.

## Cómo medir

| Qué | Cómo |
|---|---|
| Revisión automática (axe) en claro, oscuro y 320 px | En `tools/capturas`: `node a11y.mjs <carpeta> [portal tickets dashboard] [--preferencias=t150.c]` |
| Panel de preferencias en Chrome | En `tools/capturas`: `node panel.mjs <carpeta>` |
| Arreglos comunes en Chrome (salto, errores, menús, ventanas, sesión) | En `tools/capturas`: `node base.mjs [sistemas]` |
| Archivos compartidos sin divergencias | En la raíz: `node tools/comparar_compartidos.mjs` |

Usan los worktrees `../dash_a11y` y `../tickets_a11y` (variables `DASHBOARD_DIR` y `TICKETS_DIR`), bases desechables
con datos ficticios y, para el Dashboard, el MySQL desechable del puerto 3398 (ver `tutoriales.md`).

## Medición inicial

Antes de cualquier cambio: [`accesibilidad/linea-base.md`](accesibilidad/linea-base.md). La herramienta automática
detecta solo una parte: no vio la trampa de teclado de la hoja de cálculo ni los diálogos sin manejo de foco, que
salieron de la revisión del código.

## Arreglos comunes (entrega 2)

Medición posterior: [`accesibilidad/entrega-2.md`](accesibilidad/entrega-2.md) (después de ella se corrigieron los tres
fallos de contraste que quedaban). Piezas compartidas: `accesibilidad-base.js` (errores, ventanas, avisos, zonas con
desplazamiento y sesión), `<x-accesibilidad-base />`, `<x-saltar-contenido />` y la ruta `GET /sesion/viva`.

- Cada pantalla toma su título del `<h1>` (o de la ranura `title`): toda pantalla nueva necesita un solo `<h1>`.
- Un aviso de resultado lleva `data-aviso` para que se anuncie.
- Una ventana propia lleva `role="dialog"`, `aria-modal="true"`, nombre y cierre con Escape; el foco lo maneja el
  script.

Pendiente (entregas 3 y 4): hoja de cálculo, asistente de alta, calendarios, `scope` y nombre en tablas, controles sin
etiqueta (agenda de personal), nombres de los enlaces de horarios, columnas ocultas en celular y pantallas que se salen
a 320 px (calendario del portal; actividades, seguimiento y chat de Tickets; insumos y flujo de pasajeros del Dashboard).

## Preferencias de cada persona (entrega 1)

Cookie `a11y` del dominio común: `t112`, `t125` o `t150` (letra), `c` (contraste), `m` (sin movimiento) y `e`
(enlaces subrayados). No sustituye a los arreglos: las pantallas deben cumplir sin activar nada.

Limitaciones conocidas: la hoja de cálculo del Dashboard y la hoja de comprobación impresa conservan su tamaño de
letra.

## Matriz de criterios

`Falla` = visto en la medición o en el código. `En parte` = corregido lo común, falta revisar pantalla por pantalla o con
lector de pantalla. `Por evaluar` = sin evidencia todavía. Un criterio solo pasa a
`Cumple` con evidencia (prueba automática, de teclado, con lector de pantalla o con zoom).

| Criterio | Nivel | Portal | Dashboard | Tickets |
|---|---|---|---|---|
| 1.1.1 Contenido no textual | A | Por evaluar | Falla (código QR sin nombre) | Falla (código QR sin nombre) |
| 1.2.1 Solo audio o solo video | A | Por evaluar | Por evaluar | Por evaluar |
| 1.2.2 Subtítulos grabados | A | Por evaluar | Por evaluar | Por evaluar |
| 1.2.3 Audiodescripción o alternativa | A | Por evaluar | Por evaluar | Por evaluar |
| 1.2.4 Subtítulos en directo | AA | Por evaluar | Por evaluar | Por evaluar |
| 1.2.5 Audiodescripción | AA | Por evaluar | Por evaluar | Por evaluar |
| 1.3.1 Información y relaciones | A | Falla | Falla | Falla |
| 1.3.2 Secuencia significativa | A | Por evaluar | Por evaluar | Por evaluar |
| 1.3.3 Características sensoriales | A | Por evaluar | Por evaluar | Por evaluar |
| 1.3.4 Orientación | AA | Por evaluar | Por evaluar | Por evaluar |
| 1.3.5 Propósito de la entrada | AA | Por evaluar | Por evaluar | Por evaluar |
| 1.4.1 Uso del color | A | Falla (calendario) | Falla | Por evaluar |
| 1.4.2 Control del audio | A | Por evaluar | Por evaluar | Por evaluar |
| 1.4.3 Contraste mínimo | AA | En parte (axe sin fallos; faltan textos sobre foto) | En parte (axe sin fallos en lo corregido) | En parte (axe sin fallos) |
| 1.4.4 Cambio de tamaño del texto | AA | Por evaluar | Por evaluar | Por evaluar |
| 1.4.5 Imágenes de texto | AA | Por evaluar | Por evaluar | Por evaluar |
| 1.4.10 Reajuste a 320 px | AA | Falla (calendario) | Falla | Falla |
| 1.4.11 Contraste no textual | AA | En parte (bordes y foco corregidos; sin medir) | En parte (bordes y foco corregidos; sin medir) | En parte (bordes y foco corregidos; sin medir) |
| 1.4.12 Espaciado del texto | AA | Por evaluar | Por evaluar | Por evaluar |
| 1.4.13 Contenido al pasar el puntero o enfocar | AA | Por evaluar | Por evaluar | Por evaluar |
| 2.1.1 Teclado | A | Falla (asistente de alta) | Falla (hoja de cálculo) | Por evaluar |
| 2.1.2 Sin trampas de teclado | A | Por evaluar | Falla (hoja de cálculo) | Por evaluar |
| 2.1.4 Atajos de una tecla | A | Por evaluar | Por evaluar | Por evaluar |
| 2.2.1 Tiempo ajustable | A | Cumple (aviso de sesión, base.mjs) | En parte (aviso de sesión; la hoja borra avisos solos) | Cumple (aviso de sesión, base.mjs) |
| 2.2.2 Pausar, detener, ocultar | A | Por evaluar (el panel detiene el fondo animado) | Por evaluar | Por evaluar |
| 2.3.1 Tres destellos | A | Por evaluar | Por evaluar | Por evaluar |
| 2.4.1 Evitar bloques | A | Cumple (a11y.mjs y base.mjs) | Cumple (a11y.mjs y base.mjs) | Cumple (a11y.mjs y base.mjs) |
| 2.4.2 Título de las páginas | A | Cumple (título propio en las 17 revisadas) | En parte (título propio; revisar repetidos) | En parte (título propio; revisar repetidos) |
| 2.4.3 Orden del foco | A | Falla (asistente de alta, captura ampliada por revisar) | En parte (ventanas y menús; falta revisar cada una) | En parte (menú y ventanas; falta revisar el visor) |
| 2.4.4 Propósito de los enlaces | A | Por evaluar | Por evaluar | Por evaluar |
| 2.4.5 Múltiples vías | AA | Por evaluar | Por evaluar | Por evaluar |
| 2.4.6 Encabezados y etiquetas | AA | Falla | Falla | Falla (botones repetidos) |
| 2.4.7 Foco visible | AA | En parte (menús corregidos; falta recorrido con teclado) | En parte (menús corregidos; falta recorrido con teclado) | En parte (menús corregidos; falta recorrido con teclado) |
| 2.4.11 Foco no oculto | AA | Por evaluar | Por evaluar | Por evaluar |
| 2.5.1 Gestos del puntero | A | Por evaluar | Por evaluar | Por evaluar |
| 2.5.2 Cancelación del puntero | A | Por evaluar | Por evaluar | Por evaluar |
| 2.5.3 Etiqueta en el nombre | A | Por evaluar | Falla | Por evaluar |
| 2.5.4 Activación por movimiento | A | Por evaluar | Por evaluar | Por evaluar |
| 2.5.7 Movimientos de arrastre | AA | Por evaluar | Falla (ancho de columnas) | Por evaluar |
| 2.5.8 Tamaño del objetivo | AA | Por evaluar | Por evaluar | Por evaluar |
| 3.1.1 Idioma de la página | A | Por evaluar | Por evaluar | Por evaluar |
| 3.1.2 Idioma de las partes | AA | En parte (paginación, correos y errores en español) | En parte (idioma español agregado) | En parte (paginación en español) |
| 3.2.1 Al recibir el foco | A | Por evaluar | Por evaluar | Por evaluar |
| 3.2.2 Al recibir entradas | A | Por evaluar | Falla (envío al cambiar) | Por evaluar |
| 3.2.3 Navegación coherente | AA | Por evaluar | Por evaluar | Por evaluar |
| 3.2.4 Identificación coherente | AA | Por evaluar | Por evaluar | Por evaluar |
| 3.2.6 Ayuda coherente | A | Por evaluar | Por evaluar | Por evaluar |
| 3.3.1 Identificación de errores | A | En parte (errores asociados y con resumen) | En parte (errores asociados y con resumen) | En parte (errores asociados y con resumen) |
| 3.3.2 Etiquetas o instrucciones | A | Falla | Falla | Por evaluar |
| 3.3.3 Sugerencias ante errores | AA | Por evaluar | En parte (validación en español) | Por evaluar |
| 3.3.4 Prevención de errores | AA | Por evaluar | Por evaluar | Por evaluar |
| 3.3.7 Entrada redundante | A | Por evaluar | Por evaluar | Por evaluar |
| 3.3.8 Autenticación accesible | AA | Por evaluar | Por evaluar | Por evaluar |
| 4.1.2 Nombre, función, valor | A | En parte (menús corregidos) | Falla (hoja de cálculo) | En parte (menú y ventanas) |
| 4.1.3 Mensajes de estado | AA | En parte (avisos anunciados; falta lector de pantalla) | Falla (autoguardado sin anuncio) | En parte (avisos anunciados; falta lector de pantalla) |
