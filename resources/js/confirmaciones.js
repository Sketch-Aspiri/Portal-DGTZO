// Confirmaciones hechas con <details data-confirmacion>: Escape las cierra y devuelve el foco a su botón.
export function iniciarConfirmaciones(documento = document) {
    documento.addEventListener('keydown', (evento) => {
        // Con una ventana abierta (panel de accesibilidad, aviso de sesión), Escape es de ella.
        if (evento.key !== 'Escape' || evento.defaultPrevented || documento.querySelector('dialog[open]')) {
            return;
        }

        const abierta = evento.target.closest?.('details[data-confirmacion][open]') ?? documento.querySelector('details[data-confirmacion][open]');

        if (abierta) {
            abierta.open = false;
            abierta.querySelector('summary').focus();
        }
    });
}
