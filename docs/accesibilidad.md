# Accesibilidad: Portal, Dashboard y Tickets

Meta: **WCAG 2.2, nivel AA**, en los tres sistemas. Estado a 2026-11-27: **parcialmente conforme** (entregas 1 a 5
terminadas en las ramas de trabajo, sin desplegar). No se declara conformidad. Resumen:
[Informe de conformidad](#informe-de-conformidad).

## Cómo medir

| Qué | Cómo |
|---|---|
| Revisión automática (axe) en claro, oscuro y 320 px | En `tools/capturas`: `node a11y.mjs <carpeta> [portal tickets dashboard] [--preferencias=t150.c]` |
| Hoja de cálculo del Dashboard en Chrome | En `tools/capturas`: `node hoja.mjs` |
| Panel de preferencias en Chrome | En `tools/capturas`: `node panel.mjs <carpeta>` |
| Arreglos comunes en Chrome (salto, errores, menús, ventanas, sesión) | En `tools/capturas`: `node base.mjs [sistemas]` |
| Teclado en las pantallas propias del Portal | En `tools/capturas`: `node portal-teclado.mjs [carpeta]` (usa los puertos 8040 y 8043) |
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

Lo que quedó para la entrega 4 al cerrar la entrega 2 (asistente de alta, calendarios, tablas, controles sin etiqueta,
nombres de enlaces, columnas ocultas en celular y pantallas que se salían a 320 px) se atendió en la entrega 4. Medición
posterior: [`accesibilidad/entrega-4.md`](accesibilidad/entrega-4.md).

## Pantalla por pantalla (entrega 4 y declaración)

### Portal


- Asistente de alta (Enter solo avanza desde un campo, foco en el campo con error o en el título del paso, progreso y
  errores anunciados), calendario como tabla con figura y texto por tipo, grupos de campos en los accesos, tema operable
  con flechas, captura ampliada con foco y desplazamiento, búsqueda de tutoriales anunciada, «Sin leer» visible, pausa
  del fondo animado, confirmaciones que cierran con Escape y componente `x-modal` eliminado (nadie lo usaba).
- Declaración pública en `/accesibilidad`, enlazada en los pies de página. **Al cambiar la matriz, revisar su texto y
  su fecha** (`resources/views/accesibilidad/contenido.blade.php`).
- Pruebas: `AccesibilidadTest` recorre todas las rutas con pantalla por tipo de cuenta (una ruta GET nueva debe
  agregarse ahí) y `tools/capturas/portal-teclado.mjs`.

### Dashboard

- Tablas con nombre y encabezados, botones repetidos con contexto, controles con etiqueta, reajuste a 320 px y filtros
  que ya no se envían al cambiar (botón **Ver**, **Aplicar**, **Filtrar** o **Guardar**).
- Pruebas: `tests/Feature/AccesibilidadRutasTest.php` (todas las rutas con pantalla, por rol), `a11y.mjs`, `hoja.mjs`
  y `base.mjs`.

### Tickets

- Tablas, niveles de encabezado, campos obligatorios indicados, errores visibles, acciones repetidas con contexto,
  colores de las gráficas y reajuste a 320 px.
- Pruebas: `tests/Feature/AccessibilityTest.php` (todas las rutas con pantalla, por rol), `a11y.mjs` y `base.mjs`.

## Tutoriales (entrega 5)

Guía pública [Accesibilidad](../resources/guias/portal/accesibilidad.md) en `/tutoriales/accesibilidad`; textos de las
guías al día (botones de filtro, calendario, panel) y las 32 guías anteriores con capturas regeneradas. Publicarlas
requiere desplegar el portal **junto con o después de** las ramas de accesibilidad del Dashboard y Tickets.

## Pendientes

Verificación que falta en los tres sistemas:

- Lector de pantalla (NVDA) y pruebas con personas usuarias de apoyos técnicos.
- Celulares reales y control por voz.
- Zoom 200 y 400 % y espaciado del texto (1.4.12).
- Recorrido completo con teclado (orden y visibilidad del foco).
- Criterios marcados `Por evaluar` en la matriz.
- Archivos que generan los sistemas (Excel, Word y PDF).

Fallos conocidos por corregir:

| Sistema | Fallo | Criterio |
|---|---|---|
| Dashboard | «Cargar documento» sube al elegir el archivo y, si detecta una tabla, abre otra pantalla sin avisarlo antes | 3.2.2 |
| Dashboard | La hoja de cálculo y la hoja de comprobación impresa no escalan con la preferencia de letra | 1.4.4 |
| Tickets | La leyenda de la gráfica de dona depende del color | 1.4.1 |
| Tickets | Los rechazos por regla de negocio no se asocian a un campo | 3.3.1 |
| Tickets | En Mi perfil, sin foto, el avatar grande muestra «F» (inicial de «Foto de perfil…») en vez de la inicial del nombre | Defecto visual, anterior a este trabajo |

## Auditorías (2026-11-27)

Revisión de seguridad y de código de los tres sistemas. Sin hallazgos de seguridad altos ni medios introducidos por
este trabajo. Se corrigieron: bordes de campo (error, foco y contraste alto), «Restablecer» entre sistemas, el aviso de
sesión (sesión rehecha, equipo suspendido y botón «Volver a entrar»), títulos con doble escapado, copiar y pegar con el
menú de la hoja abierto, captura de asistencia con selector sin aplicar, cambios sin guardar en partidas y estaciones, y
detalles menores.

Pendiente, **anterior a este trabajo**: en el Dashboard, «¿Olvidaste tu contraseña?» responde distinto si el correo
tiene cuenta.

## Hoja de cálculo del Dashboard (entrega 3)

- **Teclado:** flechas, F2, Escape y luego Tab para salir (en la primera o última columna Tab sale solo), Mayús+F10 o el
  botón «Acciones» para el menú, Alt+Mayús+flechas para el ancho de columna.
- **Lector de pantalla:** cuadrícula con filas y celdas; la activa se anuncia con posición, columna y contenido; el
  guardado y los avisos se anuncian y los errores se quedan hasta cerrarlos.
- **Alternativa:** «Ver como tabla» (se recuerda en la cookie `hoja_vista`). Permite editar los registros; no ofrece
  formato ni calcula fórmulas (muestra su texto), y sí crece con la letra grande.
- **Prueba:** `tools/capturas/hoja.mjs`. **Falta** probarla con NVDA y con quienes capturan a diario.

## Preferencias de cada persona (entrega 1)

Cookie `a11y` del dominio común: `t112`, `t125` o `t150` (letra), `c` (contraste), `m` (sin movimiento) y `e`
(enlaces subrayados). No sustituye a los arreglos: las pantallas deben cumplir sin activar nada.

Limitaciones conocidas: la hoja de cálculo del Dashboard y la hoja de comprobación impresa conservan su tamaño de
letra.

## Informe de conformidad

- **Fecha:** 2026-11-27.
- **Referencia:** WCAG 2.2, niveles A y AA.
- **Alcance:** pantallas web del Portal, Dashboard y Tickets en las ramas de trabajo de accesibilidad (código aún no
  desplegado), con datos ficticios. Quedan fuera los archivos generados (Excel, Word, PDF), los correos y los sitios
  externos a los que se enlaza.
- **Método:** revisión del código; pruebas automáticas de la estructura HTML por rol; axe en claro y oscuro y reajuste
  a 320 px (también con letra al 150 %); pruebas de teclado automatizadas en Chrome; revisión visual de las capturas.
- **Herramientas:** axe-core 4.14.1, Playwright 1.65.1, Google Chrome 158 (Windows 11, sin interfaz) y PHPUnit.
- **Resultado:**

| Sistema | Resultado | Cumple (con evidencia) | Resto |
|---|---|---|---|
| Portal | Parcialmente conforme | 5: 2.2.1, 2.2.2, 2.4.1, 2.4.2, 3.1.1 | 19 en parte, 31 por evaluar |
| Dashboard | Parcialmente conforme | 4: 2.2.1, 2.4.1, 2.5.7, 3.1.1 | 21 en parte, 30 por evaluar |
| Tickets | Parcialmente conforme | 4: 2.2.1, 2.4.1, 2.4.2, 3.1.1 | 18 en parte, 33 por evaluar |

- **Excepciones conocidas:** las de [Fallos conocidos](#pendientes).
- **No verificado:** NVDA u otro lector de pantalla, celulares reales, zoom 200 y 400 %, espaciado del texto, control
  por voz y pruebas con personas usuarias.
- **Declaración:** los tres sistemas son **parcialmente conformes**. «En parte» y «Por evaluar» no cuentan como
  cumplimiento.

## Matriz de criterios

`Falla` = visto en la medición o en el código. `En parte` = corregido lo común, falta revisar pantalla por pantalla o con
lector de pantalla. `Por evaluar` = sin evidencia todavía. Un criterio solo pasa a
`Cumple` con evidencia (prueba automática, de teclado, con lector de pantalla o con zoom).

| Criterio | Nivel | Portal | Dashboard | Tickets |
|---|---|---|---|---|
| 1.1.1 Contenido no textual | A | En parte (imágenes con `alt` y QR con nombre, AccesibilidadTest; las capturas de guías solo tienen descripción breve) | En parte (imágenes con `alt` y QR con nombre, AccesibilidadRutasTest; falta lector de pantalla) | En parte (imágenes con `alt` y QR con nombre, AccessibilityTest; falta lector de pantalla) |
| 1.2.1 Solo audio o solo video | A | Por evaluar | Por evaluar | Por evaluar |
| 1.2.2 Subtítulos grabados | A | Por evaluar | Por evaluar | Por evaluar |
| 1.2.3 Audiodescripción o alternativa | A | Por evaluar | Por evaluar | Por evaluar |
| 1.2.4 Subtítulos en directo | AA | Por evaluar | Por evaluar | Por evaluar |
| 1.2.5 Audiodescripción | AA | Por evaluar | Por evaluar | Por evaluar |
| 1.3.1 Información y relaciones | A | En parte (calendario como tabla, grupos de campos y `th` con alcance, AccesibilidadTest y portal-teclado.mjs; falta lector de pantalla) | En parte (tablas con nombre y `scope`, controles con etiqueta y un `h1` por pantalla, AccesibilidadRutasTest; falta lector de pantalla) | En parte (tablas, etiquetas y niveles de encabezado, AccessibilityTest; falta lector de pantalla) |
| 1.3.2 Secuencia significativa | A | Por evaluar | Por evaluar | Por evaluar |
| 1.3.3 Características sensoriales | A | Por evaluar | Por evaluar | Por evaluar |
| 1.3.4 Orientación | AA | Por evaluar | Por evaluar | Por evaluar |
| 1.3.5 Propósito de la entrada | AA | Por evaluar | Por evaluar | Por evaluar |
| 1.4.1 Uso del color | A | En parte (calendario, filtros y avisos sin leer con texto o figura; falta revisar el resto) | En parte (estados pendientes y cambiados con texto; falta revisar el resto) | En parte (estados con texto; la leyenda de la gráfica de dona depende del color) |
| 1.4.2 Control del audio | A | Por evaluar | Por evaluar | Por evaluar |
| 1.4.3 Contraste mínimo | AA | En parte (axe sin fallos; faltan textos sobre foto) | En parte (axe sin fallos en las 39 pantallas de a11y.mjs, claro y oscuro; no cubre todos los estados) | En parte (axe sin fallos en las 45 pantallas de a11y.mjs, claro y oscuro; no cubre todos los estados) |
| 1.4.4 Cambio de tamaño del texto | AA | En parte (con las preferencias del panel, incluida letra al 150 %, sin desborde a 320 px en las 18 pantallas de a11y.mjs; falta zoom 200 %) | En parte (sin desborde a 320 px con letra al 150 % en a11y.mjs; la hoja de cálculo no escala; falta zoom 200 %) | En parte (sin desborde a 320 px con letra al 150 % en a11y.mjs; falta zoom 200 %) |
| 1.4.5 Imágenes de texto | AA | Por evaluar | Por evaluar | Por evaluar |
| 1.4.10 Reajuste a 320 px | AA | En parte (sin desborde a 320 px en las 18 pantallas de a11y.mjs y en el asistente; falta cada guía y estado) | En parte (sin desborde a 320 px en las 39 pantallas de a11y.mjs; falta cada estado y zoom 400 %) | En parte (sin desborde a 320 px en las 45 pantallas de a11y.mjs; falta cada estado y zoom 400 %) |
| 1.4.11 Contraste no textual | AA | En parte (bordes y foco corregidos; sin medir) | En parte (bordes y foco corregidos; sin medir) | En parte (series de las gráficas con 3:1, AccessibilityTest; bordes y foco corregidos sin medir) |
| 1.4.12 Espaciado del texto | AA | Por evaluar | Por evaluar | Por evaluar |
| 1.4.13 Contenido al pasar el puntero o enfocar | AA | Por evaluar | Por evaluar | Por evaluar |
| 2.1.1 Teclado | A | En parte (asistente, calendario, tema, confirmaciones y capturas con teclado, portal-teclado.mjs; falta recorrido completo) | En parte (hoja con teclado, hoja.mjs; menús y búsqueda, base.mjs; falta recorrido completo) | En parte (menú, ventanas y salto con teclado, base.mjs; falta recorrido completo) |
| 2.1.2 Sin trampas de teclado | A | En parte (captura ampliada y ventanas cierran con Escape, portal-teclado.mjs y base.mjs; falta recorrido completo) | En parte (la hoja ya no atrapa el foco, hoja.mjs) | En parte (las ventanas cierran con Escape y devuelven el foco, base.mjs; falta recorrido completo) |
| 2.1.4 Atajos de una tecla | A | Por evaluar | Por evaluar | Por evaluar |
| 2.2.1 Tiempo ajustable | A | Cumple (aviso de sesión, base.mjs) | Cumple (aviso de sesión y errores que no se borran) | Cumple (aviso de sesión, base.mjs) |
| 2.2.2 Pausar, detener, ocultar | A | Cumple (botón para pausar el fondo y la tarjeta animada, recordado; portal-teclado.mjs) | Por evaluar | Por evaluar |
| 2.3.1 Tres destellos | A | Por evaluar | Por evaluar | Por evaluar |
| 2.4.1 Evitar bloques | A | Cumple (a11y.mjs y base.mjs) | Cumple (a11y.mjs y base.mjs) | Cumple (a11y.mjs y base.mjs) |
| 2.4.2 Título de las páginas | A | Cumple (título propio en las 17 revisadas) | En parte (título propio; revisar repetidos) | Cumple (45 títulos distintos en las 45 pantallas de a11y.mjs; título propio exigido en cada ruta, AccessibilityTest) |
| 2.4.3 Orden del foco | A | En parte (asistente y captura ampliada, portal-teclado.mjs; falta recorrido completo) | En parte (ventanas y menús; falta revisar cada una) | En parte (menú y ventanas, base.mjs; falta recorrido completo, incluido el visor) |
| 2.4.4 Propósito de los enlaces | A | En parte (enlaces y botones repetidos con contexto; falta revisión completa) | Por evaluar | En parte (acciones repetidas en tablas con contexto, AccessibilityTest; falta revisión completa) |
| 2.4.5 Múltiples vías | AA | Por evaluar | Por evaluar | Por evaluar |
| 2.4.6 Encabezados y etiquetas | AA | En parte (encabezados y etiquetas corregidos; falta revisión con personas) | En parte (encabezados, etiquetas y botones repetidos con contexto; falta revisión con personas) | En parte (encabezados y botones repetidos corregidos, AccessibilityTest; falta revisión con personas) |
| 2.4.7 Foco visible | AA | En parte (menús corregidos; falta recorrido con teclado) | En parte (menús corregidos; falta recorrido con teclado) | En parte (menús corregidos; falta recorrido con teclado) |
| 2.4.11 Foco no oculto | AA | Por evaluar | Por evaluar | Por evaluar |
| 2.5.1 Gestos del puntero | A | Por evaluar | Por evaluar | Por evaluar |
| 2.5.2 Cancelación del puntero | A | Por evaluar | Por evaluar | Por evaluar |
| 2.5.3 Etiqueta en el nombre | A | Por evaluar | En parte (a11y.mjs sin `label-content-name-mismatch`, regla que no cubre todos los casos; falta control por voz) | Por evaluar |
| 2.5.4 Activación por movimiento | A | Por evaluar | Por evaluar | Por evaluar |
| 2.5.7 Movimientos de arrastre | AA | Por evaluar | Cumple (ancho de columna por teclado y menú, hoja.mjs) | Por evaluar |
| 2.5.8 Tamaño del objetivo | AA | En parte (axe sin fallos de tamaño; falta revisar estados desplegados) | Por evaluar | Por evaluar |
| 3.1.1 Idioma de la página | A | Cumple (`lang="es"` en todas las rutas con pantalla, AccesibilidadTest) | Cumple (`lang="es"` en las pantallas de a11y.mjs y atributo exigido en todas las rutas con pantalla, AccesibilidadRutasTest; depende de `APP_LOCALE=es`) | Cumple (`lang="es"` en todas las rutas con pantalla, AccessibilityTest; depende de `APP_LOCALE=es`) |
| 3.1.2 Idioma de las partes | AA | En parte (paginación, correos y errores en español) | En parte (idioma español agregado) | En parte (paginación en español) |
| 3.2.1 Al recibir el foco | A | Por evaluar | Por evaluar | Por evaluar |
| 3.2.2 Al recibir entradas | A | Por evaluar | En parte (los filtros ya no se envían al cambiar, AccesibilidadRutasTest; pero la zona «Cargar documento» sube al elegir el archivo y, si detecta una tabla, abre otra pantalla sin avisarlo antes) | Por evaluar |
| 3.2.3 Navegación coherente | AA | Por evaluar | Por evaluar | Por evaluar |
| 3.2.4 Identificación coherente | AA | Por evaluar | Por evaluar | Por evaluar |
| 3.2.6 Ayuda coherente | A | Por evaluar | Por evaluar | Por evaluar |
| 3.3.1 Identificación de errores | A | En parte (errores asociados y con resumen; también en el asistente, portal-teclado.mjs; falta lector de pantalla) | En parte (errores asociados y con resumen) | En parte (cada error de validación se ve y se resume, AccessibilityTest; los rechazos por regla de negocio no se asocian al campo) |
| 3.3.2 Etiquetas o instrucciones | A | En parte (todo control con nombre, AccesibilidadTest; falta revisar instrucciones) | En parte (todo control con nombre, AccesibilidadRutasTest; falta revisar instrucciones) | En parte (controles con nombre y obligatorios indicados, AccessibilityTest; falta revisar instrucciones) |
| 3.3.3 Sugerencias ante errores | AA | Por evaluar | En parte (validación en español) | Por evaluar |
| 3.3.4 Prevención de errores | AA | Por evaluar | Por evaluar | Por evaluar |
| 3.3.7 Entrada redundante | A | Por evaluar | Por evaluar | Por evaluar |
| 3.3.8 Autenticación accesible | AA | Por evaluar | Por evaluar | Por evaluar |
| 4.1.2 Nombre, función, valor | A | En parte (menús, asistente, tema y calendario; falta lector de pantalla) | En parte (nombres, funciones y estados corregidos, AccesibilidadRutasTest y hoja.mjs; falta lector de pantalla) | En parte (nombres y estados, AccessibilityTest y base.mjs; falta lector de pantalla) |
| 4.1.3 Mensajes de estado | AA | En parte (avisos, pasos del asistente y resultados de búsqueda anunciados; falta lector de pantalla) | En parte (guardado y avisos anunciados; falta lector de pantalla) | En parte (avisos anunciados; falta lector de pantalla) |
