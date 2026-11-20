import '@fontsource/figtree/400.css';
import '@fontsource/figtree/500.css';
import '@fontsource/figtree/600.css';
import '@fontsource/poppins/600.css';
import '@fontsource/poppins/700.css';

import './shader-background';
import accessWizard from './access-wizard';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.data('accessWizard', accessWizard);

Alpine.start();
