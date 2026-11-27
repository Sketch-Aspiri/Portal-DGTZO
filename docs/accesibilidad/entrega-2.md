<!-- Medición al terminar los arreglos comunes (entrega 2). -->
# Revisión automática (2026-11-27)

## portal

- Pantallas distintas: 17 (39 revisiones por persona).
- Títulos distintos: 17. Sin exactamente un h1: 0. Sin enlace de salto: 0.
- Con desplazamiento horizontal a 320 px: /calendario.

| Impacto | Regla | Pantallas | Modos | Elementos (suma) | Ejemplo |
|---|---|---|---|---|---|
| serious | color-contrast | 1 | claro, oscuro | 36 | `#wizard-step-1 > header > .min-w-0.flex-1 > h2` |

## tickets

- Pantallas distintas: 45 (90 revisiones por persona).
- Títulos distintos: 43. Sin exactamente un h1: 0. Sin enlace de salto: 0.
- Con desplazamiento horizontal a 320 px: /activities, /tracking, /chat/{id}.

| Impacto | Regla | Pantallas | Modos | Elementos (suma) | Ejemplo |
|---|---|---|---|---|---|

## dashboard

- Pantallas distintas: 39 (76 revisiones por persona).
- Títulos distintos: 35. Sin exactamente un h1: 0. Sin enlace de salto: 0.
- Con desplazamiento horizontal a 320 px: /insumos, /afluencia/estaciones/{id}.
- No revisadas: estacion /insumos/1/formato: page.goto: Download is starting

| Impacto | Regla | Pantallas | Modos | Elementos (suma) | Ejemplo |
|---|---|---|---|---|---|
| critical | select-name | 1 | claro, oscuro | 4 | `select` |
| serious | color-contrast | 3 | claro, oscuro | 13 | `label[for="observaciones_equipos"]` |
| serious | label-content-name-mismatch | 1 | claro, oscuro | 70 | `tr:nth-child(1) > .min-w-\[8\.5rem\]:nth-child(2) > .underline.text-brand-teal.mt-1` |
