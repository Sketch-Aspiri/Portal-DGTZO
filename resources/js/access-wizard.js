// Asistente de solicitud de acceso: estado, validación por paso y resumen. Los campos son inputs reales con `name`,
// así que el formulario se envía igual aunque un paso esté colapsado.
export default function accessWizard(config) {
    const initial = config.initial || {};
    const sys = (key, extra) => ({
        activo: false, rol: '', estacion_id: '', area: '', team_ids: [], coordination_ids: [], detalle: '',
        ...extra,
        ...(initial.accesos && initial.accesos[key] ? initial.accesos[key] : {}),
    });

    return {
        step: 0,
        reached: 0,
        errors: {},
        notice: '',
        marked: [],
        total: 4,
        catalogos: config.catalogos || {},
        roles: config.roles || {},
        labels: config.labels || {},
        form: {
            puesto: initial.puesto || '',
            ubicacion: initial.ubicacion || '',
            telefono: initial.telefono || '',
            funciones: initial.funciones || '',
            justificacion: initial.justificacion || '',
        },
        sys: { dashboard: sys('dashboard'), tickets: sys('tickets') },

        init() {
            // Los enlaces del resumen de errores (accesibilidad-base.js) pueden llevar a un paso cerrado: se abre antes
            // de enfocar el campo, porque lo cerrado es inerte.
            document.addEventListener('click', (event) => {
                const link = event.target.closest('.a11y-resumen a[href^="#"]');
                const field = link && document.getElementById(link.getAttribute('href').slice(1));
                const card = field && field.closest('[id^="wizard-step-"]');
                if (!card) return;
                this.step = parseInt(card.id.replace('wizard-step-', ''), 10);
                this.reached = Math.max(this.reached, this.step);
                setTimeout(() => field.focus(), 50);
            }, true);

            // Con errores del servidor se abre el primer paso que falló.
            if (config.errorStep !== undefined && config.errorStep !== null) {
                this.reached = this.total - 1;
                this.step = config.errorStep;
            }
            // Normaliza ids que llegan como texto desde old().
            ['team_ids', 'coordination_ids'].forEach((f) => {
                this.sys.tickets[f] = (this.sys.tickets[f] || []).map((v) => parseInt(v, 10));
            });
            ['dashboard', 'tickets'].forEach((k) => {
                if (this.sys[k].activo === '1' || this.sys[k].activo === 1) this.sys[k].activo = true;
                this.sys[k].estacion_id = this.sys[k].estacion_id ? String(this.sys[k].estacion_id) : '';
                this.sys[k].area = this.sys[k].area || '';
                this.sys[k].detalle = this.sys[k].detalle || '';
            });
        },

        get progress() {
            return (this.step / (this.total - 1)) * 100;
        },

        activeKeys() {
            return Object.keys(this.sys).filter((k) => this.sys[k].activo);
        },

        hasCatalog(key) {
            return !!this.catalogos[key];
        },

        toggleSystem(key) {
            // Con un solo rol posible se preselecciona.
            const roles = Object.keys(this.roles[key] || {});
            if (this.sys[key].activo && roles.length === 1) this.sys[key].rol = roles[0];
        },

        teamGroups() {
            const cat = this.catalogos.tickets;
            if (!cat) return [];
            const groups = {};
            cat.equipos.forEach((e) => {
                const g = `${e.estacion} › ${e.coordinacion}`;
                (groups[g] = groups[g] || []).push(e);
            });
            return Object.entries(groups).map(([name, items]) => ({ name, items }));
        },

        stationName(id) {
            const s = ((this.catalogos.dashboard || {}).estaciones || []).find((x) => x.id === parseInt(id, 10));
            return s ? s.nombre : '';
        },
        areaName(clave) {
            const a = ((this.catalogos.dashboard || {}).areas || []).find((x) => x.clave === clave);
            return a ? a.nombre : '';
        },
        teamNames(ids) {
            const eq = (this.catalogos.tickets || {}).equipos || [];
            return ids.map((id) => (eq.find((e) => e.id === parseInt(id, 10)) || {}).nombre).filter(Boolean);
        },
        coordNames(ids) {
            const co = (this.catalogos.tickets || {}).coordinaciones || [];
            return ids.map((id) => (co.find((c) => c.id === parseInt(id, 10)) || {}).nombre).filter(Boolean);
        },
        roleName(key) {
            return (this.roles[key] || {})[this.sys[key].rol] || '';
        },

        validate(step) {
            const e = {};
            const L = this.labels;
            if (step === 0) {
                if (this.form.puesto.trim().length < 3) e.puesto = L.req_puesto;
                if (this.form.ubicacion.trim().length < 2) e.ubicacion = L.req_ubicacion;
            }
            if (step === 1) {
                const keys = this.activeKeys();
                if (keys.length === 0) e.sistemas = L.req_system;
                keys.forEach((k) => {
                    const s = this.sys[k];
                    if (!s.rol) e[`${k}_rol`] = L.req_role;
                    if (this.hasCatalog(k)) {
                        if (k === 'dashboard' && !s.estacion_id) e.dashboard_estacion = L.req_station;
                        if (k === 'tickets' && s.rol === 'empleado' && s.team_ids.length === 0) e.tickets_equipos = L.req_teams;
                        if (k === 'tickets' && s.rol === 'coordinador' && s.coordination_ids.length === 0) e.tickets_coord = L.req_coordinations;
                    }
                });
            }
            if (step === 2 && this.form.funciones.trim().length < 10) e.funciones = L.req_functions;
            this.errors = e;
            return Object.keys(e).length === 0;
        },

        /** Enter en un campo avanza de paso en vez de enviar; en botones, enlaces y áreas de texto hace lo suyo. */
        enter(event) {
            const field = event.target;
            if (field.tagName !== 'INPUT' || ['button', 'submit'].includes(field.type) || this.step >= this.total - 1) return;
            event.preventDefault();
            this.next();
        },

        next() {
            if (!this.validate(this.step)) {
                this.showErrors();
                return;
            }
            this.go(this.step + 1);
        },
        back() {
            this.go(this.step - 1);
        },
        go(i) {
            if (i < 0 || i >= this.total) return;
            this.clearMarks();
            this.errors = {};
            this.step = i;
            this.reached = Math.max(this.reached, i);
            this.notice = (config.announce || '')
                .replace(':step', i + 1)
                .replace(':total', this.total)
                .replace(':name', (config.stepNames || [])[i] || '');
            this.$nextTick(() => {
                const card = document.getElementById(`wizard-step-${i}`);
                if (card) {
                    const still = window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.classList.contains('a11y-sin-movimiento');
                    card.scrollIntoView({ behavior: still ? 'auto' : 'smooth', block: 'start' });
                    // Sin campos (la revisión), el foco va al título del paso para no quedarse en lo que se cerró.
                    const first = card.querySelector('input:not([type=hidden]):not(.sr-only), textarea, select')
                        || card.querySelector('h2');
                    first.focus({ preventScroll: true });
                }
            });
        },

        /** Controles a los que se refiere cada error de validate(). */
        controlsFor(key) {
            const form = this.$root.closest('form') || this.$root;
            const byName = (name) => [...form.querySelectorAll(`[name="${name}"]`)];
            if (key === 'sistemas') return Object.keys(this.sys).flatMap((k) => byName(`accesos[${k}][activo]`));
            if (key.endsWith('_rol')) return byName(`accesos[${key.slice(0, -4)}][rol]`);
            if (key === 'dashboard_estacion') return byName('accesos[dashboard][estacion_id]');
            if (key === 'tickets_equipos') return byName('accesos[tickets][team_ids][]');
            if (key === 'tickets_coord') return byName('accesos[tickets][coordination_ids][]');
            return byName(key);
        },

        clearMarks() {
            this.marked.forEach(({ control, id }) => {
                const rest = (control.getAttribute('aria-describedby') || '').split(' ').filter((x) => x && x !== id);
                if (rest.length) control.setAttribute('aria-describedby', rest.join(' '));
                else control.removeAttribute('aria-describedby');
                control.removeAttribute('aria-invalid');
            });
            this.marked = [];
        },

        /** Marca cada campo con error, lo asocia a su mensaje, lo anuncia y enfoca el primero. */
        showErrors() {
            this.clearMarks();
            this.notice = Object.values(this.errors).join(' ');
            this.$nextTick(() => {
                let first = null;
                Object.keys(this.errors).forEach((key) => {
                    const id = `error-${key}`;
                    this.controlsFor(key).forEach((control) => {
                        const ids = (control.getAttribute('aria-describedby') || '').split(' ').filter(Boolean);
                        control.setAttribute('aria-describedby', [...ids.filter((x) => x !== id), id].join(' '));
                        control.setAttribute('aria-invalid', 'true');
                        this.marked.push({ control, id });
                        first = first || control;
                    });
                });
                if (first) first.focus();
            });
        },

        done(i) {
            return i < this.reached || (i === this.reached && i < this.step);
        },
        submit(event) {
            for (let i = 0; i < 3; i++) {
                if (!this.validate(i)) {
                    event.preventDefault();
                    const found = this.errors;
                    this.go(i);
                    this.errors = found;
                    this.$nextTick(() => this.showErrors());
                    return;
                }
            }
        },
    };
}
