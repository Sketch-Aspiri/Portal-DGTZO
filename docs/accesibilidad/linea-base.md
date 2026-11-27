<!-- Medición antes de cualquier arreglo, generada por tools/capturas/a11y.mjs sobre datos ficticios. -->
# Revisión automática (2026-11-27)

## portal

- Pantallas distintas: 17 (36 revisiones por persona).
- Títulos distintos: 1. Sin exactamente un h1: 6. Sin enlace de salto: 17.
- Con desplazamiento horizontal a 320 px: /calendario.

| Impacto | Regla | Pantallas | Modos | Elementos (suma) | Ejemplo |
|---|---|---|---|---|---|
| serious | color-contrast | 8 | claro, oscuro | 64 | `.text-gray-500.mt-6.text-center` |

## tickets

- Pantallas distintas: 45 (87 revisiones por persona).
- Títulos distintos: 40. Sin exactamente un h1: 0. Sin enlace de salto: 4.
- Con desplazamiento horizontal a 320 px: /activities, /tracking, /chat/{id}.

| Impacto | Regla | Pantallas | Modos | Elementos (suma) | Ejemplo |
|---|---|---|---|---|---|
| serious | aria-prohibited-attr | 2 | claro, oscuro | 4 | `span[aria-label="&laquo; Anterior"]` |

## dashboard

- Pantallas distintas: 39 (75 revisiones por persona).
- Títulos distintos: 1. Sin exactamente un h1: 38. Sin enlace de salto: 39.
- Con desplazamiento horizontal a 320 px: /insumos, /afluencia/estaciones/{id}.
- No revisadas: estacion /insumos/1/formato: page.goto: Download is starting

| Impacto | Regla | Pantallas | Modos | Elementos (suma) | Ejemplo |
|---|---|---|---|---|---|
| critical | aria-required-children | 11 | claro, oscuro | 42 | `div[role="tablist"]` |
| critical | aria-required-parent | 11 | claro, oscuro | 168 | `button[\@click="tab = 'tabla'"]` |
| critical | select-name | 1 | claro, oscuro | 4 | `select` |
| serious | color-contrast | 9 | claro, oscuro | 46 | `.mt-8` |
| serious | scrollable-region-focusable | 2 | claro, oscuro | 28 | `.p-3` |
| serious | aria-progressbar-name | 1 | claro, oscuro | 6 | `.h-2` |
| serious | label-content-name-mismatch | 1 | claro, oscuro | 70 | `tr:nth-child(1) > .min-w-\[8\.5rem\]:nth-child(2) > .underline.text-brand-teal.mt-1` |
