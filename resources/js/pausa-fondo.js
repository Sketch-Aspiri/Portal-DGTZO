// Control visible para pausar el fondo en movimiento (WCAG 2.2.2). Los botones son <x-pausa-fondo>; la elección se
// recuerda en este navegador. Con "Reducir movimiento" (del panel o del sistema) no hay nada que pausar y se ocultan.
const CLAVE = 'fondo-pausado';

export function iniciarPausaFondo(documento = document, ventana = window) {
    const raiz = documento.documentElement;
    const botones = [...documento.querySelectorAll('[data-pausa-fondo]')];
    const sistema = ventana.matchMedia('(prefers-reduced-motion: reduce)');

    try {
        raiz.classList.toggle(CLAVE, ventana.localStorage.getItem(CLAVE) === '1');
    } catch {
        // Sin almacenamiento (ventana privada): la elección dura esta página.
    }

    const pintar = () => {
        const pausado = raiz.classList.contains(CLAVE);
        const quieto = sistema.matches || raiz.classList.contains('a11y-sin-movimiento');

        for (const boton of botones) {
            boton.hidden = quieto;
            boton.textContent = pausado ? boton.dataset.reanudar : boton.dataset.pausar;
        }
    };

    for (const boton of botones) {
        boton.addEventListener('click', () => {
            const pausado = raiz.classList.toggle(CLAVE);

            try {
                ventana.localStorage.setItem(CLAVE, pausado ? '1' : '0');
            } catch {
                // Ver arriba.
            }

            pintar();
            ventana.dispatchEvent(new CustomEvent('fondo-cambiado'));
        });
    }

    sistema.addEventListener('change', pintar);
    ventana.addEventListener('a11y-cambiado', pintar);
    pintar();
    ventana.dispatchEvent(new CustomEvent('fondo-cambiado'));
}
