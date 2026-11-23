import '@fontsource/figtree/400.css';
import '@fontsource/figtree/500.css';
import '@fontsource/figtree/600.css';
import '@fontsource/poppins/600.css';
import '@fontsource/poppins/700.css';

import './shader-background';
import accessWizard from './access-wizard';
import { registerPush, registerServiceWorker } from './push';
import { selectorTema } from './tema';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.data('accessWizard', accessWizard);
Alpine.data('selectorTema', selectorTema);
registerPush(Alpine);
registerServiceWorker();

Alpine.start();

// Aparición suave al desplazarse (elementos con .reveal).
const revealables = document.querySelectorAll('.reveal');
if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
        entries.forEach((e) => {
            if (e.isIntersecting) { e.target.classList.add('is-visible'); io.unobserve(e.target); }
        });
    }, { threshold: 0.15 });
    revealables.forEach((el) => io.observe(el));
} else {
    revealables.forEach((el) => el.classList.add('is-visible'));
}
