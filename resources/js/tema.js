/**
 * Selector de tema. Tres opciones: claro, oscuro o el del sistema (por defecto). Guarda la elección en una cookie del
 * dominio común (la misma del Dashboard y Tickets: elegir aquí cambia los tres) y en localStorage, y pone o quita la
 * clase "dark" en <html>; el script del <head> (layouts/partials/tema.blade.php) la aplica al cargar.
 */
const OPCIONES = ['sistema', 'claro', 'oscuro'];
const consultaOscuro = window.matchMedia('(prefers-color-scheme: dark)');

/**
 * Dominio de la cookie compartida: el dominio común (".zonaorientetm.tech", que incluye al portal en el dominio raíz y
 * a sus subdominios); en localhost o una IP, el host.
 */
function dominioCookie() {
    const host = window.location.hostname;
    const partes = host.split('.');

    if (/^[\d.]+$/.test(host) || partes.length < 2) {
        return '';
    }

    return `; domain=.${partes.slice(-2).join('.')}`;
}

function guardarCookie(tema) {
    const seguro = window.location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `tema=${tema}; max-age=31536000; path=/; SameSite=Lax${dominioCookie()}${seguro}`;
}

function leer() {
    const cookie = document.cookie.match(/(?:^|; )tema=(sistema|claro|oscuro)(?:;|$)/);

    if (cookie) {
        return cookie[1];
    }

    try {
        const valor = localStorage.getItem('tema');

        return OPCIONES.includes(valor) ? valor : 'sistema';
    } catch {
        return 'sistema';
    }
}

function aplicar(tema) {
    const oscuro = tema === 'oscuro' || (tema === 'sistema' && consultaOscuro.matches);
    document.documentElement.classList.toggle('dark', oscuro);
    window.dispatchEvent(new CustomEvent('tema-cambiado', { detail: { oscuro } }));
}

export function selectorTema() {
    return {
        tema: leer(),

        init() {
            consultaOscuro.addEventListener('change', () => {
                if (this.tema === 'sistema') {
                    aplicar('sistema');
                }
            });
        },

        elegir(tema) {
            this.tema = tema;

            try {
                if (tema === 'sistema') {
                    localStorage.removeItem('tema');
                } else {
                    localStorage.setItem('tema', tema);
                }
            } catch {
                // Sin almacenamiento (ventana privada): el cambio dura esta página.
            }

            guardarCookie(tema);
            aplicar(tema);
        },

        siguiente() {
            this.elegir(OPCIONES[(OPCIONES.indexOf(this.tema) + 1) % OPCIONES.length]);
        },

        get etiqueta() {
            return { sistema: 'Tema: del sistema', claro: 'Tema: claro', oscuro: 'Tema: oscuro' }[this.tema];
        },
    };
}
