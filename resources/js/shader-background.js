// Fondo "silk" en WebGL (sin dependencias), portado del componente ShaderBackground
// de 21st.dev. Uso: <canvas data-shader-bg data-colors="#043D39,#06534D,#1F7460,#70C2AD">.
// Si WebGL no existe, el canvas no se pinta y se ve lo que haya detrás.

const VERT = `attribute vec2 a_position;
void main() { gl_Position = vec4(a_position, 0.0, 1.0); }`;

const FRAG = `precision mediump float;
uniform vec3 u_colors[4];
uniform vec2 u_resolution;
uniform float u_time;
uniform float u_scale;
uniform float u_intensity;
uniform float u_contrast;
uniform float u_grain;
uniform float u_seed;
uniform vec2 u_offset;

float grainHash(vec2 p) {
  vec3 p3 = fract(vec3(p.xyx) * 0.1031);
  p3 += dot(p3, p3.yzx + 33.33);
  return fract((p3.x + p3.y) * p3.z);
}

vec3 palette(float x) {
  float f = clamp(x, 0.0, 1.0) * 3.0;
  vec3 col = u_colors[0];
  col = mix(col, u_colors[1], smoothstep(0.0, 1.0, clamp(f, 0.0, 1.0)));
  col = mix(col, u_colors[2], smoothstep(0.0, 1.0, clamp(f - 1.0, 0.0, 1.0)));
  col = mix(col, u_colors[3], smoothstep(0.0, 1.0, clamp(f - 2.0, 0.0, 1.0)));
  return col;
}

void main() {
  vec2 p = (gl_FragCoord.xy - 0.5 * u_resolution) / min(u_resolution.x, u_resolution.y);
  p = p * u_scale + u_offset;
  vec2 q = p * 1.6;
  float amp = 0.25 + u_intensity * 0.85;
  for (float i = 1.0; i < 5.0; i += 1.0) {
    q.x += amp / i * cos(i * 2.4 * q.y + u_time * 0.8 + u_seed);
    q.y += amp / i * cos(i * 1.7 * q.x + u_time * 0.6);
  }
  vec3 col = palette(0.5 + 0.5 * sin(q.x + q.y));
  col = (col - 0.5) * u_contrast + 0.5;
  col += (grainHash(gl_FragCoord.xy + vec2(u_seed * 17.0, u_seed * 31.0)) - 0.5) * u_grain;
  gl_FragColor = vec4(clamp(col, 0.0, 1.0), 1.0);
}`;

const hex = (h) => {
    const n = parseInt(h.replace('#', ''), 16);
    return [(n >> 16) / 255, ((n >> 8) & 255) / 255, (n & 255) / 255];
};

function start(canvas) {
    const gl = canvas.getContext('webgl', { antialias: false });
    if (!gl) return;

    const compile = (type, src) => {
        const s = gl.createShader(type);
        gl.shaderSource(s, src);
        gl.compileShader(s);
        return s;
    };
    const program = gl.createProgram();
    gl.attachShader(program, compile(gl.VERTEX_SHADER, VERT));
    gl.attachShader(program, compile(gl.FRAGMENT_SHADER, FRAG));
    gl.linkProgram(program);
    if (!gl.getProgramParameter(program, gl.LINK_STATUS)) return;
    gl.useProgram(program);

    gl.bindBuffer(gl.ARRAY_BUFFER, gl.createBuffer());
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 3, -1, -1, 3]), gl.STATIC_DRAW);
    const loc = gl.getAttribLocation(program, 'a_position');
    gl.enableVertexAttribArray(loc);
    gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);

    const colors = (canvas.dataset.colors || '#043D39,#06534D,#1F7460,#70C2AD')
        .split(',').map((c) => hex(c.trim())).slice(0, 4);
    while (colors.length < 4) colors.push(colors[colors.length - 1]);

    const u = (n) => gl.getUniformLocation(program, n);
    gl.uniform3fv(u('u_colors'), new Float32Array(colors.flat()));
    gl.uniform1f(u('u_scale'), 1.26);
    gl.uniform1f(u('u_intensity'), 0.5);
    gl.uniform1f(u('u_contrast'), 1.1);
    gl.uniform1f(u('u_grain'), 0.12);
    gl.uniform1f(u('u_seed'), 1581.0);
    gl.uniform2f(u('u_offset'), -0.23, 0.0);
    const uRes = u('u_resolution');
    const uTime = u('u_time');

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const t0 = performance.now();
    let raf = 0;
    let visible = true;

    const resize = () => {
        const dpr = Math.min(window.devicePixelRatio || 1, 2);
        const r = canvas.getBoundingClientRect();
        let w = Math.max(1, Math.round(r.width * dpr));
        let h = Math.max(1, Math.round(r.height * dpr));
        const k = Math.min(1, Math.sqrt(2_000_000 / (w * h)));
        w = Math.max(1, Math.round(w * k));
        h = Math.max(1, Math.round(h * k));
        if (canvas.width !== w || canvas.height !== h) {
            canvas.width = w;
            canvas.height = h;
            gl.viewport(0, 0, w, h);
        }
    };

    const draw = (now) => {
        raf = 0;
        resize();
        gl.uniform2f(uRes, canvas.width, canvas.height);
        gl.uniform1f(uTime, ((now - t0) / 1000) * 0.309);
        gl.drawArrays(gl.TRIANGLES, 0, 3);
        if (!reduced && visible) raf = requestAnimationFrame(draw);
    };

    new IntersectionObserver(([e]) => {
        visible = e.isIntersecting;
        if (visible && !raf) raf = requestAnimationFrame(draw);
    }).observe(canvas);
    window.addEventListener('resize', () => { if (reduced) requestAnimationFrame(draw); });
    raf = requestAnimationFrame(draw);
}

document.querySelectorAll('canvas[data-shader-bg]').forEach(start);
