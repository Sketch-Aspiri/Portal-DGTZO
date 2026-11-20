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

        next() {
            if (!this.validate(this.step)) return;
            this.go(this.step + 1);
        },
        back() {
            this.go(this.step - 1);
        },
        go(i) {
            if (i < 0 || i >= this.total) return;
            this.errors = {};
            this.step = i;
            this.reached = Math.max(this.reached, i);
            this.$nextTick(() => {
                const card = document.getElementById(`wizard-step-${i}`);
                if (card) {
                    card.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
                    const first = card.querySelector('input:not([type=hidden]):not(.sr-only), textarea, select');
                    if (first) first.focus({ preventScroll: true });
                }
            });
        },
        done(i) {
            return i < this.reached || (i === this.reached && i < this.step);
        },
        submit(event) {
            for (let i = 0; i < 3; i++) {
                if (!this.validate(i)) {
                    event.preventDefault();
                    this.go(i);
                    this.validate(i);
                    return;
                }
            }
        },
    };
}
