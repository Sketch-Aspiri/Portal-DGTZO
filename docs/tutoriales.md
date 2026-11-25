# Tutoriales

Sección `/tutoriales` del portal: guías paso a paso con capturas del Portal, Tickets y Dashboard.

## Dónde está cada cosa

| Qué | Dónde |
|---|---|
| Catálogo (título, sistema, roles, fechas) | `config/guias.php` |
| Texto | `resources/guias/<sistema>/<slug>.md` |
| Capturas | `resources/guias/img/<sistema>/<slug>/NN-nombre.png` |
| Quién ve qué | `app/Services/Tutoriales/CatalogoGuias.php` |
| Guiones de captura | `tools/capturas/<sistema>.mjs` y `semillas/` |

Visibilidad: las guías `publica` se ven sin sesión; las demás, según el sistema y el rol de la cuenta. Quien administra el
portal las ve todas. Las capturas se sirven con el mismo permiso que su guía (nunca desde `public/`).

## Actualizar una guía (al cambiar algo en un sistema)

1. Ajusta el texto en su `.md`. Cada `## Título` es un paso del índice. Las imágenes se citan por su nombre:
   `![Descripción](01-pantalla.png)`.
2. Regenera sus capturas (abajo) y **revísalas una por una**.
3. En `config/guias.php` pon `'actualizada' => 'AAAA-MM-DD'` (muestra la etiqueta «Actualizado» 30 días).
4. `php vendor/bin/phpunit tests/Feature` (la prueba de coherencia detecta imágenes faltantes o sobrantes).
5. Publicar requiere desplegar el portal.

Guía nueva: agrega su entrada al catálogo, el `.md` y un bloque con el mismo slug en el guion de su sistema. Los slugs
son únicos entre sistemas (por eso llevan el prefijo `tickets-` o `dashboard-`).

## Regenerar capturas

Requisitos: Node, Google Chrome, Python con Pillow y, en `tools/capturas`, `npm install`.
Siempre usan **bases desechables con datos ficticios**: las semillas abortan si la base no termina en `_capturas`.
Nunca apuntarlas a una base con datos reales.

```bash
cd tools/capturas
node portal.mjs                 # todas las del portal
node tickets.mjs tickets-chat   # solo una
node dashboard.mjs
node revisar.mjs <carpeta>      # recorre la sección y avisa de guías o imágenes rotas
```

**Tickets y Dashboard** necesitan una copia de la versión publicada (con `vendor` copiado, `.env` desde
`.env.example`, `php artisan key:generate` y `npm run build`):

```bash
git -C ../tickets_tren_maya worktree add --detach ../tickets_capturas origin/main
git -C ../dashboard_tren_maya worktree add --detach ../dash_capturas origin/master
```

Si la copia ya existe, actualízala con `git checkout --detach origin/<rama>`, `composer install` y `npm run build`.
Otras rutas: variables `TICKETS_DIR` y `DASHBOARD_DIR`.

El Dashboard además necesita un MySQL desechable en el puerto 3398 (SQLite no sirve):

```bash
DATOS="$(cygpath -m "$TEMP")/tutoriales_mysql"
BIN="/c/Program Files/MySQL/MySQL Server 8.4/bin"
"$BIN/mysqld.exe" --no-defaults --initialize-insecure --datadir="$DATOS"      # solo la primera vez
"$BIN/mysqld.exe" --no-defaults --datadir="$DATOS" --port=3398 --bind-address=127.0.0.1 --mysqlx=OFF --console &
"$BIN/mysql.exe" --no-defaults -h127.0.0.1 -P3398 -uroot -e "CREATE DATABASE IF NOT EXISTS tutoriales_dashboard_capturas"
```
