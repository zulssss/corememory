/**
 * HALFTONE REVEAL
 *
 * Renders a photograph as a halftone print, with a sharp "loupe" that follows
 * the cursor and reveals the real image underneath.
 *
 * Ported from React Bits' <HalftoneReveal /> (https://reactbits.dev/animations/
 * halftone-reveal). This site has no React, so the component's React wrapper
 * is rewritten as plain JavaScript; the GLSL shaders below are the original,
 * unchanged — they are where the effect actually lives.
 *
 * What differs from the original, and why:
 *   - It decorates an EXISTING <img> rather than replacing it. The <img> stays
 *     in the page for its alt text, for search engines, and as the fallback
 *     whenever this doesn't run (touch, reduced motion, no WebGL 2).
 *   - Ink and paper colours come from tokens.css, not hex props, so the effect
 *     follows a restyle like everything else.
 *   - It only renders while on screen. The original draws every frame forever;
 *     a full-width canvas doing that while the couple reads further down the
 *     page would cost battery and frames for nothing.
 *   - The canvas fades in only once the photo is loaded into WebGL, so there
 *     is never a black frame between the photo and the print.
 *
 * Loaded lazily from motion.js, so pages without [data-halftone] never
 * download ogl at all.
 */

import { Renderer, Program, Triangle, Mesh, Texture } from "ogl";

const MODES = { mono: 0, duotone: 1, color: 2 };
const SHAPES = { circle: 0, square: 1, diamond: 2, line: 3 };
const TRIGGERS = { off: 0, hover: 1, always: 2 };

/* ---------------------------------------------------------------------------
   Shaders — verbatim from React Bits.
   ------------------------------------------------------------------------ */

const vertex = `#version 300 es
in vec2 position;
out vec2 vUv;
void main() {
  vUv = position * 0.5 + 0.5;
  gl_Position = vec4(position, 0.0, 1.0);
}
`;

const fragment = `#version 300 es
precision highp float;

uniform sampler2D tMap;
uniform vec2 iResolution;
uniform vec2 uImageSize;
uniform vec2 uMouse;
uniform float uActivity;

uniform float uDotSize;
uniform float uDensity;
uniform float uAngle;
uniform int uShape;
uniform vec3 uInk;
uniform vec3 uPaper;
uniform int uMode;
uniform float uContrast;
uniform float uInvert;

uniform float uRevealRadius;
uniform float uEdge;
uniform float uIdleReveal;
uniform int uTrigger;

in vec2 vUv;
out vec4 fragColor;

vec2 uAspect() {
  return vec2(iResolution.x / max(iResolution.y, 1.0), 1.0);
}

vec2 coverUv(vec2 uv) {
  float ia = uImageSize.x / max(uImageSize.y, 1.0);
  float pa = iResolution.x / max(iResolution.y, 1.0);
  vec2 s = pa > ia ? vec2(1.0, ia / pa) : vec2(pa / ia, 1.0);
  return (uv - 0.5) * s + 0.5;
}

vec3 gradeRGB(vec3 c) {
  c = clamp((c - 0.5) * uContrast + 0.5, 0.0, 1.0);
  return mix(c, 1.0 - c, uInvert);
}

float shapeDist(vec2 f) {
  if (uShape == 1) return max(abs(f.x), abs(f.y));
  if (uShape == 2) return abs(f.x) + abs(f.y);
  if (uShape == 3) return abs(f.y);
  return length(f);
}

mat2 rot(float a) {
  float c = cos(a);
  float s = sin(a);
  return mat2(c, -s, s, c);
}

vec4 sampleCell(vec2 st, float dens, float ang) {
  vec2 rp = rot(ang) * st * dens;
  vec2 center = floor(rp) + 0.5;
  vec2 stC = rot(-ang) * (center / dens);
  vec2 uvC = stC / uAspect();
  return texture(tMap, clamp(coverUv(uvC), 0.0, 1.0));
}

float coverage(vec2 st, float dens, float ang, float ink, float rscale) {
  vec2 rp = rot(ang) * st * dens;
  vec2 f = fract(rp) - 0.5;
  float d = shapeDist(f);
  float r = sqrt(clamp(ink, 0.0, 1.0)) * 0.72 * rscale * uDotSize;
  float w = length(fwidth(rp)) * 0.6 + 1e-4;
  return smoothstep(r + w, r - w, d);
}

void main() {
  vec2 aspect = uAspect();
  vec2 st = vUv * aspect;
  float ang = radians(uAngle);

  vec2 duv = (vUv - uMouse) * aspect;
  float dist = length(duv);

  float act = uTrigger == 2 ? 1.0 : (uTrigger == 0 ? 0.0 : uActivity);
  float radius = max(uRevealRadius, 1e-4) * mix(0.4, 1.0, act);

  float px = 1.4 / max(iResolution.y, 1.0);
  float band = max(px, radius * (1.0 - clamp(uEdge, 0.0, 1.0)) * 0.45);
  float loupe = 1.0 - smoothstep(radius - band, radius + band, dist);
  float focus = clamp(max(loupe * act, uIdleReveal), 0.0, 1.0);

  float dens = uDensity;

  vec3 print;
  if (uMode == 2) {
    vec3 gc = gradeRGB(sampleCell(st, dens, ang + radians(15.0)).rgb);
    vec3 gm = gradeRGB(sampleCell(st, dens, ang + radians(75.0)).rgb);
    vec3 gy = gradeRGB(sampleCell(st, dens, ang).rgb);
    vec3 gk = gradeRGB(sampleCell(st, dens, ang + radians(45.0)).rgb);
    float c = 1.0 - gc.r;
    float m = 1.0 - gm.g;
    float y = 1.0 - gy.b;
    float k = 1.0 - dot(gk, vec3(0.299, 0.587, 0.114));
    float gcr = min(min(c, m), y) * 0.5;
    c = clamp(c - gcr, 0.0, 1.0);
    m = clamp(m - gcr, 0.0, 1.0);
    y = clamp(y - gcr, 0.0, 1.0);
    k = clamp(max(gcr, k * k * 0.9), 0.0, 1.0);
    float covC = coverage(st, dens, ang + radians(15.0), c, 0.82);
    float covM = coverage(st, dens, ang + radians(75.0), m, 0.82);
    float covY = coverage(st, dens, ang, y, 0.82);
    float covK = coverage(st, dens, ang + radians(45.0), k, 0.78);
    print = uPaper;
    print = mix(print, print * vec3(0.10, 0.72, 0.90), covC);
    print = mix(print, print * vec3(0.92, 0.10, 0.52), covM);
    print = mix(print, print * vec3(0.98, 0.86, 0.10), covY);
    print = mix(print, print * vec3(0.08), covK);
  } else if (uMode == 1) {
    vec3 ink2 = mix(uInk.gbr, vec3(0.90, 0.24, 0.30), 0.7);
    float lumA = dot(gradeRGB(sampleCell(st, dens, ang).rgb), vec3(0.299, 0.587, 0.114));
    float lumB = dot(gradeRGB(sampleCell(st, dens, ang + radians(38.0)).rgb), vec3(0.299, 0.587, 0.114));
    float covA = coverage(st, dens, ang, 1.0 - lumA, 1.0);
    float covB = coverage(st, dens, ang + radians(38.0), pow(1.0 - lumB, 1.4), 0.92);
    print = uPaper;
    print = mix(print, ink2, covB * 0.85);
    print = mix(print, uInk, covA);
  } else {
    float lum = dot(gradeRGB(sampleCell(st, dens, ang).rgb), vec3(0.299, 0.587, 0.114));
    float cov = coverage(st, dens, ang, 1.0 - lum, 1.0);
    print = mix(uPaper, uInk, cov);
  }

  float t = clamp(dist / radius, 0.0, 1.0);
  float bend = t * t * t * t;
  vec2 dir = dist > 1e-5 ? duv / dist : vec2(0.0);
  vec2 off = dir * bend * radius * 0.22 / aspect;
  vec2 ca = dir * bend * 0.0045 / aspect;
  vec3 sharp = gradeRGB(vec3(
    texture(tMap, clamp(coverUv(vUv - off - ca), 0.0, 1.0)).r,
    texture(tMap, clamp(coverUv(vUv - off), 0.0, 1.0)).g,
    texture(tMap, clamp(coverUv(vUv - off + ca), 0.0, 1.0)).b
  ));

  vec3 col = mix(print, sharp, focus);
  fragColor = vec4(col, 1.0);
}
`;

/* ---------------------------------------------------------------------------
   Helpers
   ------------------------------------------------------------------------ */

/** A CSS colour token (#rrggbb) as a 0–1 RGB triple. */
function tokenRgb(name, fallback) {
  const hex = getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
  const m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
  return m ? [1, 2, 3].map((i) => parseInt(m[i], 16) / 255) : [0, 0, 0];
}

/** WebGL refuses pixels from another origin without CORS, so never try. */
function isSameOrigin(url) {
  try {
    return new URL(url, window.location.href).origin === window.location.origin;
  } catch {
    return false;
  }
}

/** Resolves once the <img> has pixels, whether it was lazy or already loaded. */
function whenLoaded(img) {
  if (img.complete && img.naturalWidth > 0) return Promise.resolve(img);
  return new Promise((resolve, reject) => {
    img.addEventListener("load", () => resolve(img), { once: true });
    img.addEventListener("error", reject, { once: true });
  });
}

/* ---------------------------------------------------------------------------
   Mount
   ------------------------------------------------------------------------ */

/**
 * Turn the <img> inside `container` into a halftone with a cursor loupe.
 * Returns a cleanup function. If anything is unsupported it returns a no-op
 * and the plain photograph simply stays as it is.
 */
export function mountHalftone(container, options = {}) {
  const img = container.querySelector("img");
  if (!img) return () => {};

  const opts = {
    mode: "mono",
    dotSize: 1,
    dotDensity: 90,
    angle: 28,
    shape: "circle",
    contrast: 1.15,
    invert: false,
    revealRadius: 0.28,
    edge: 0.8,
    follow: 0.37,
    idleReveal: 0,
    trigger: "hover",
    ...options,
  };

  let renderer;
  try {
    renderer = new Renderer({
      dpr: Math.min(window.devicePixelRatio || 1, 2),
      alpha: false,
      antialias: true,
    });
  } catch {
    return () => {};
  }

  const gl = renderer.gl;

  // The shaders are GLSL ES 3.0. Without WebGL 2 they cannot compile, so
  // keep the photograph rather than show a blank box.
  if (!renderer.isWebgl2) {
    gl.getExtension("WEBGL_lose_context")?.loseContext();
    return () => {};
  }

  const canvas = gl.canvas;
  canvas.setAttribute("aria-hidden", "true");
  canvas.className = "halftone-reveal__canvas";
  container.appendChild(canvas);

  const texture = new Texture(gl, { generateMipmaps: false });

  const uniforms = {
    tMap: { value: texture },
    iResolution: { value: [1, 1] },
    uImageSize: { value: [1, 1] },
    uMouse: { value: [0.5, 0.5] },
    uActivity: { value: 0 },
    uDotSize: { value: opts.dotSize },
    uDensity: { value: opts.dotDensity },
    uAngle: { value: opts.angle },
    uShape: { value: SHAPES[opts.shape] ?? 0 },
    uInk: { value: tokenRgb("--color-ink", "#141312") },
    uPaper: { value: tokenRgb("--color-paper", "#f7f5f1") },
    uMode: { value: MODES[opts.mode] ?? 0 },
    uContrast: { value: opts.contrast },
    uInvert: { value: opts.invert ? 1 : 0 },
    uRevealRadius: { value: opts.revealRadius },
    uEdge: { value: opts.edge },
    uIdleReveal: { value: opts.idleReveal },
    uTrigger: { value: TRIGGERS[opts.trigger] ?? 1 },
  };

  const program = new Program(gl, { vertex, fragment, uniforms });
  const mesh = new Mesh(gl, { geometry: new Triangle(gl), program });

  // State --------------------------------------------------------------------
  let disposed = false;
  let ready = false;
  let visible = false;
  let rafId = null;
  let prev = performance.now();
  const mouse = { x: 0.5, y: 0.5, sx: 0.5, sy: 0.5, active: 0, target: 0 };

  // Size ---------------------------------------------------------------------
  const resize = () => {
    renderer.setSize(container.clientWidth || 1, container.clientHeight || 1);
    uniforms.iResolution.value = [canvas.width, canvas.height];
    if (ready && !rafId) renderer.render({ scene: mesh });
  };
  resize();
  const ro = new ResizeObserver(resize);
  ro.observe(container);

  // Loop — only while on screen ---------------------------------------------
  const loop = (now) => {
    rafId = requestAnimationFrame(loop);
    const dt = Math.min(0.05, Math.max(0.001, (now - prev) / 1000));
    prev = now;

    const a = 1 - Math.exp(-dt / Math.max(0.001, opts.follow));
    mouse.sx += (mouse.x - mouse.sx) * a;
    mouse.sy += (mouse.y - mouse.sy) * a;
    mouse.active += (mouse.target - mouse.active) * (1 - Math.exp(-dt / 0.18));

    uniforms.uMouse.value[0] = mouse.sx;
    uniforms.uMouse.value[1] = mouse.sy;
    uniforms.uActivity.value = mouse.active;

    renderer.render({ scene: mesh });
  };

  const start = () => {
    if (disposed || !ready || !visible || rafId) return;
    prev = performance.now();
    rafId = requestAnimationFrame(loop);
  };
  const stop = () => {
    if (rafId) cancelAnimationFrame(rafId);
    rafId = null;
  };

  const io = new IntersectionObserver(([entry]) => {
    visible = entry.isIntersecting;
    visible ? start() : stop();
  });
  io.observe(container);

  // Pointer ------------------------------------------------------------------
  // getBoundingClientRect includes the parallax transform, so the loupe stays
  // under the cursor while the image drifts on scroll.
  const onMove = (event) => {
    const rect = container.getBoundingClientRect();
    mouse.x = (event.clientX - rect.left) / rect.width;
    mouse.y = 1 - (event.clientY - rect.top) / rect.height;
    mouse.target = 1;
  };
  const onLeave = () => {
    mouse.target = 0;
  };
  container.addEventListener("pointermove", onMove, { passive: true });
  container.addEventListener("pointerenter", onMove, { passive: true });
  container.addEventListener("pointerleave", onLeave, { passive: true });

  // Image --------------------------------------------------------------------
  whenLoaded(img)
    .then(() => {
      const src = img.currentSrc || img.src;
      if (disposed || !isSameOrigin(src)) return;

      // A fresh element from the same (cached) URL, so a srcset swap on the
      // page <img> can't change the texture underneath us.
      const source = new Image();
      source.decoding = "async";
      source.onload = () => {
        if (disposed) return;
        texture.image = source;
        uniforms.uImageSize.value = [source.naturalWidth, source.naturalHeight];
        ready = true;

        // First frame BEFORE fading in, so the fade reveals the print and
        // never a black canvas.
        renderer.render({ scene: mesh });
        container.classList.add("is-halftone-ready");
        start();
      };
      source.src = src;
    })
    .catch(() => {
      /* The photograph failed to load; the <img> handles that on its own. */
    });

  // Cleanup ------------------------------------------------------------------
  return () => {
    disposed = true;
    stop();
    io.disconnect();
    ro.disconnect();
    container.removeEventListener("pointermove", onMove);
    container.removeEventListener("pointerenter", onMove);
    container.removeEventListener("pointerleave", onLeave);
    container.classList.remove("is-halftone-ready");
    gl.getExtension("WEBGL_lose_context")?.loseContext();
    canvas.remove();
  };
}
