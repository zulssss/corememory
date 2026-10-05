/**
 * COREMEMORY — MOTION
 *
 * Every scroll-linked effect on the site is registered here and nowhere else.
 * One module owning all of it is what makes the three non-negotiables
 * enforceable in one place:
 *
 *   1. `prefers-reduced-motion: reduce` kills Lenis and every ScrollTrigger.
 *   2. Under 768px, reveals stay but heavy parallax and the cursor are dropped.
 *   3. Every ScrollTrigger is killed on navigation, so nothing leaks.
 *
 * Rule for anything added here: animate `transform` and `opacity` only. Never
 * width, height, top, left or margin — those trigger layout and cost frames.
 */

import Lenis from "lenis";
import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";

gsap.registerPlugin(ScrollTrigger);

/* -------------------------------------------------------------------------
   ENVIRONMENT
   ---------------------------------------------------------------------- */

const REDUCED_MOTION = window.matchMedia("(prefers-reduced-motion: reduce)");
const DESKTOP = window.matchMedia("(min-width: 768px)");
const FINE_POINTER = window.matchMedia("(hover: hover) and (pointer: fine)");

/** Reads a design token so JS motion values can't drift from tokens.css. */
function token(name, fallback) {
  const value = getComputedStyle(document.documentElement)
    .getPropertyValue(name)
    .trim();
  return value || fallback;
}

function tokenNumber(name, fallback) {
  const parsed = parseFloat(token(name, ""));
  return Number.isFinite(parsed) ? parsed : fallback;
}

/* -------------------------------------------------------------------------
   STATE — everything we create is tracked so it can be torn down.
   ---------------------------------------------------------------------- */

let lenis = null;
let rafId = null;
const cleanups = [];

function onCleanup(fn) {
  cleanups.push(fn);
}

/* -------------------------------------------------------------------------
   SMOOTH SCROLL
   ---------------------------------------------------------------------- */

function initSmoothScroll() {
  lenis = new Lenis({
    duration: 1.05,
    easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
    smoothWheel: true,
    // Never hijack touch scrolling — native momentum is better on mobile and
    // fighting it is the fastest way to make a site feel broken on a phone.
    smoothTouch: false,
  });

  // Lenis drives scroll position, so ScrollTrigger must read from Lenis
  // instead of the native scroll event or the two desynchronise.
  lenis.on("scroll", ScrollTrigger.update);

  const raf = (time) => {
    lenis.raf(time);
    rafId = requestAnimationFrame(raf);
  };
  rafId = requestAnimationFrame(raf);

  onCleanup(() => {
    if (rafId) cancelAnimationFrame(rafId);
    rafId = null;
    lenis?.destroy();
    lenis = null;
  });
}

/* -------------------------------------------------------------------------
   REVEAL ON ENTER
   Text fades and rises; images clip-reveal by settling an overscale back to 1.
   Fires once only — re-animating on every scroll-by is the gimmicky version.
   ---------------------------------------------------------------------- */

function initReveals() {
  const distance = token("--reveal-distance", "24px");
  const stagger = tokenNumber("--reveal-stagger", 70) / 1000;

  // Siblings inside a [data-reveal-group] animate as a staggered batch.
  document.querySelectorAll("[data-reveal-group]").forEach((group) => {
    const items = group.querySelectorAll("[data-reveal]");
    if (!items.length) return;

    const tween = gsap.to(items, {
      opacity: 1,
      y: 0,
      duration: 0.9,
      ease: "power3.out",
      stagger,
      scrollTrigger: {
        trigger: group,
        start: "top 85%",
        once: true,
      },
    });

    onCleanup(() => tween.scrollTrigger?.kill());
  });

  // Standalone reveals not inside a group.
  document
    .querySelectorAll("[data-reveal]:not([data-reveal-group] [data-reveal])")
    .forEach((el) => {
      const kind = el.getAttribute("data-reveal");

      let target = el;
      let vars = { opacity: 1, y: 0 };

      if (kind === "image") {
        target = el.firstElementChild ?? el;
        vars = { opacity: 1, scale: 1 };
        gsap.set(el, { opacity: 1 });
      } else if (kind === "line") {
        vars = { opacity: 1, scaleX: 1 };
      } else {
        gsap.set(el, { y: distance });
      }

      const tween = gsap.to(target, {
        ...vars,
        duration: kind === "line" ? 1.1 : 0.9,
        ease: "power3.out",
        scrollTrigger: { trigger: el, start: "top 88%", once: true },
      });

      onCleanup(() => tween.scrollTrigger?.kill());
    });
}

/* -------------------------------------------------------------------------
   PARALLAX — desktop only. Dropped entirely under 768px.

   Each element translates inside its own overflow-hidden container. Intensity
   varies per element via `data-parallax="0.18"` so the grid doesn't move as
   one flat plane.
   ---------------------------------------------------------------------- */

function initParallax() {
  if (!DESKTOP.matches) return;

  const gridDefault = tokenNumber("--parallax-grid", 0.12);

  document.querySelectorAll("[data-parallax]").forEach((container) => {
    const layer = container.firstElementChild;
    if (!layer) return;

    const intensity = parseFloat(container.getAttribute("data-parallax")) || gridDefault;
    const shift = container.offsetHeight * intensity;

    // The layer is overscaled in markup so the translate never exposes an edge.
    const tween = gsap.fromTo(
      layer,
      { yPercent: -intensity * 50 },
      {
        yPercent: intensity * 50,
        ease: "none",
        scrollTrigger: {
          trigger: container,
          start: "top bottom",
          end: "bottom top",
          scrub: true,
          invalidateOnRefresh: true,
        },
      }
    );

    onCleanup(() => tween.scrollTrigger?.kill());
    void shift;
  });
}

/** Hero: background drifts slowly, headline drifts and fades slightly faster. */
function initHero() {
  if (!DESKTOP.matches) return;

  const hero = document.querySelector("[data-hero]");
  if (!hero) return;

  const intensity = tokenNumber("--parallax-hero", 0.2);
  const media = hero.querySelector("[data-hero-media]");
  const content = hero.querySelector("[data-hero-content]");

  const timeline = gsap.timeline({
    scrollTrigger: {
      trigger: hero,
      start: "top top",
      end: "bottom top",
      scrub: true,
      invalidateOnRefresh: true,
    },
  });

  if (media) {
    timeline.to(media, { yPercent: intensity * 100, ease: "none" }, 0);
  }

  if (content) {
    timeline.to(
      content,
      { yPercent: intensity * 160, opacity: 0, ease: "none" },
      0
    );
  }

  onCleanup(() => timeline.scrollTrigger?.kill());
}

/* -------------------------------------------------------------------------
   COUNTERS — stats grid counts up once on enter.
   ---------------------------------------------------------------------- */

function initCounters() {
  document.querySelectorAll("[data-count-to]").forEach((el) => {
    const target = parseFloat(el.getAttribute("data-count-to"));
    if (!Number.isFinite(target)) return;

    const counter = { value: 0 };
    // Preserve a leading zero (05, 08) so the design's numeral style survives.
    const pad = el.textContent.trim().startsWith("0") ? 2 : 0;

    const tween = gsap.to(counter, {
      value: target,
      duration: 1.8,
      ease: "power2.out",
      onUpdate: () => {
        const n = Math.round(counter.value);
        el.textContent = pad ? String(n).padStart(pad, "0") : String(n);
      },
      scrollTrigger: { trigger: el, start: "top 90%", once: true },
    });

    onCleanup(() => tween.scrollTrigger?.kill());
  });
}

/* -------------------------------------------------------------------------
   MARQUEE — seamless horizontal loop for the behind-the-scenes strip.
   Speed reacts subtly to scroll velocity; pauses on hover.
   ---------------------------------------------------------------------- */

function initMarquee() {
  document.querySelectorAll("[data-marquee]").forEach((marquee) => {
    const track = marquee.querySelector("[data-marquee-track]");
    if (!track) return;

    // The track is duplicated in markup, so translating exactly -50% lands on
    // an identical frame — that's what makes the loop seamless.
    const speed = parseFloat(marquee.getAttribute("data-marquee")) || 40;

    const tween = gsap.to(track, {
      xPercent: -50,
      duration: speed,
      ease: "none",
      repeat: -1,
    });

    const onEnter = () => gsap.to(tween, { timeScale: 0, duration: 0.4 });
    const onLeave = () => gsap.to(tween, { timeScale: 1, duration: 0.4 });
    marquee.addEventListener("pointerenter", onEnter);
    marquee.addEventListener("pointerleave", onLeave);

    // Scroll velocity nudges the speed, then eases back to rest.
    let velocityTrigger = null;
    if (DESKTOP.matches) {
      velocityTrigger = ScrollTrigger.create({
        trigger: marquee,
        start: "top bottom",
        end: "bottom top",
        onUpdate: (self) => {
          const boost = 1 + Math.min(Math.abs(self.getVelocity()) / 2200, 2.2);
          gsap.to(tween, { timeScale: boost, duration: 0.3, overwrite: true });
          gsap.to(tween, { timeScale: 1, duration: 1.2, delay: 0.3, overwrite: "auto" });
        },
      });
    }

    onCleanup(() => {
      marquee.removeEventListener("pointerenter", onEnter);
      marquee.removeEventListener("pointerleave", onLeave);
      velocityTrigger?.kill();
      tween.kill();
    });
  });
}

/* -------------------------------------------------------------------------
   CUSTOM CURSOR — desktop, fine pointer only. Never on touch.
   ---------------------------------------------------------------------- */

/* -------------------------------------------------------------------------
   HALFTONE REVEAL — [data-halftone]
   The photograph prints as a halftone; a loupe under the cursor shows the real
   image. Desktop with a real mouse only: on touch there is no cursor to
   steer the loupe, so a phone would only ever see dots and never the photo.
   Under reduced motion initMotion() never gets here, so the plain photograph
   stays. ogl is fetched only when such an element exists.
   ---------------------------------------------------------------------- */

function initHalftone() {
  if (!DESKTOP.matches || !FINE_POINTER.matches) return;

  const targets = document.querySelectorAll("[data-halftone]");
  if (targets.length === 0) return;

  let cancelled = false;
  const unmounts = [];

  onCleanup(() => {
    cancelled = true;
    unmounts.splice(0).forEach((unmount) => unmount());
  });

  import("./halftone")
    .then(({ mountHalftone }) => {
      if (cancelled) return;
      targets.forEach((el) => unmounts.push(mountHalftone(el)));
    })
    .catch(() => {
      /* Chunk failed to load: the plain photograph is already showing. */
    });
}

function initCursor() {
  if (!FINE_POINTER.matches || !DESKTOP.matches) return;

  const root = document.querySelector("[data-cursor-root]");
  if (!root) return;

  const label = root.querySelector("[data-cursor-label]");
  gsap.set(root, { xPercent: -50, yPercent: -50 });

  const moveX = gsap.quickTo(root, "x", { duration: 0.35, ease: "power3" });
  const moveY = gsap.quickTo(root, "y", { duration: 0.35, ease: "power3" });

  const onMove = (event) => {
    moveX(event.clientX);
    moveY(event.clientY);
  };
  window.addEventListener("pointermove", onMove, { passive: true });

  // Over a [data-cursor="view"] target the dot scales up and reads its label.
  const targets = document.querySelectorAll("[data-cursor]");
  const listeners = [];

  targets.forEach((target) => {
    const text = target.getAttribute("data-cursor") || "VIEW";

    const enter = () => {
      gsap.to(root, { scale: 1, opacity: 1, duration: 0.3, ease: "power3.out" });
      if (label) label.textContent = text;
      root.setAttribute("data-cursor-active", "true");
    };
    const leave = () => {
      gsap.to(root, { scale: 0.28, duration: 0.3, ease: "power3.out" });
      root.removeAttribute("data-cursor-active");
    };

    target.addEventListener("pointerenter", enter);
    target.addEventListener("pointerleave", leave);
    listeners.push([target, enter, leave]);
  });

  gsap.set(root, { scale: 0.28, opacity: 1 });

  onCleanup(() => {
    window.removeEventListener("pointermove", onMove);
    listeners.forEach(([target, enter, leave]) => {
      target.removeEventListener("pointerenter", enter);
      target.removeEventListener("pointerleave", leave);
    });
  });
}

/* -------------------------------------------------------------------------
   STICKY + SCRUB — the featured wedding pins briefly while its caption
   content changes. Desktop only; pinning on a phone costs more than it gives.
   ---------------------------------------------------------------------- */

function initStickyFeature() {
  if (!DESKTOP.matches) return;

  document.querySelectorAll("[data-sticky-feature]").forEach((section) => {
    const panels = section.querySelectorAll("[data-sticky-panel]");
    if (panels.length < 2) return;

    const trigger = ScrollTrigger.create({
      trigger: section,
      start: "top top",
      end: () => `+=${section.offsetHeight * (panels.length - 1)}`,
      pin: true,
      scrub: true,
      anticipatePin: 1,
      invalidateOnRefresh: true,
      onUpdate: (self) => {
        const index = Math.min(
          panels.length - 1,
          Math.floor(self.progress * panels.length)
        );
        panels.forEach((panel, i) => {
          gsap.to(panel, {
            opacity: i === index ? 1 : 0,
            duration: 0.35,
            ease: "power2.out",
            overwrite: true,
          });
        });
      },
    });

    onCleanup(() => trigger.kill());
  });
}

/* -------------------------------------------------------------------------
   LIFECYCLE
   ---------------------------------------------------------------------- */

/* -------------------------------------------------------------------------
   BOOKING WIZARD — keep the next thing to do on screen
   Livewire morphs the wizard in place and keeps the scroll position. Without
   help the couple ends up looking at the wrong part of the page: the foot of
   the next step, or above a session list they now need to choose from.
   ---------------------------------------------------------------------- */

/** Height covered by the sticky header, plus breathing room. Measured, not
 *  read from --header-height: the token is in rem, the header is real. */
function headerOffset() {
  const header = document.querySelector("body > header, header.sticky");
  return (header?.offsetHeight ?? 0) + 16;
}

/**
 * Bring `el` into view — but only if it isn't already comfortably visible, so
 * a couple who can already see it is never jolted. `align: "start"` puts its
 * top under the header; "nearest" scrolls the least needed to show it whole.
 */
function reveal(el, { align = "start" } = {}) {
  if (!el) return;

  const offset = headerOffset();
  const rect = el.getBoundingClientRect();
  const viewport = window.innerHeight;
  const visible = rect.top >= offset && rect.bottom <= viewport - 16;

  if (visible) return;

  // Taller than the space available, or above the fold: align its top.
  let delta = rect.top - offset;
  if (align === "nearest" && rect.top >= offset && rect.height < viewport - offset) {
    delta = rect.bottom - (viewport - 24);
  }

  if (lenis) {
    lenis.scrollTo(window.scrollY + delta);
  } else {
    // Reduced motion: Lenis was never created, so jump without animating.
    window.scrollTo({ top: window.scrollY + delta, behavior: "auto" });
  }
}

/** A step changed: show the progress bar and land focus on the new heading. */
export function scrollToWizardTop() {
  const wizard = document.getElementById("booking-wizard");
  if (!wizard) return;

  reveal(wizard);

  // Keyboard and screen-reader users land on the new step too. preventScroll
  // so focusing never fights the scroll above.
  wizard.querySelector("[data-step-heading]")?.focus({ preventScroll: true });
}

/**
 * Something inside the current step needs attention:
 *   sessions / venue / continue — the next thing to fill in after a choice
 *   error                       — the first warning after a failed Continue
 */
export function revealInWizard(target) {
  const wizard = document.getElementById("booking-wizard");
  if (!wizard) return;

  if (target === "error") {
    const warning = wizard.querySelector('[role="alert"]');
    reveal(warning?.closest("label") ?? warning, { align: "start" });

    // Put the cursor in the field the warning belongs to, if it is one.
    warning?.closest("label")?.querySelector("input, select, textarea")
      ?.focus({ preventScroll: true });
    return;
  }

  reveal(wizard.querySelector(`[data-wizard-section="${target}"]`), { align: "nearest" });
}

/** Tears down everything. Called on navigation so nothing leaks between pages. */
export function destroyMotion() {
  cleanups.splice(0).forEach((fn) => {
    try {
      fn();
    } catch {
      /* a failed teardown must never block the rest */
    }
  });
  ScrollTrigger.getAll().forEach((trigger) => trigger.kill());
  ScrollTrigger.clearScrollMemory();
  gsap.globalTimeline.clear();
}

export function initMotion() {
  // The single kill switch. Under reduced motion nothing is created at all —
  // CSS has already rendered every element in its final state.
  if (REDUCED_MOTION.matches) {
    document.documentElement.removeAttribute("data-motion");
    return;
  }

  document.documentElement.setAttribute("data-motion", "on");

  initSmoothScroll();
  initHero();
  initReveals();
  initParallax();
  initCounters();
  initMarquee();
  initStickyFeature();
  initCursor();
  initHalftone();

  // Images finishing late change element offsets, so triggers need recalculating.
  window.addEventListener("load", () => ScrollTrigger.refresh());
}

// Re-evaluate if the user flips reduced motion on mid-session.
REDUCED_MOTION.addEventListener("change", () => {
  destroyMotion();
  initMotion();
});

// Kill everything on navigation — including bfcache restores and Livewire
// page swaps, both of which would otherwise leave dead triggers behind.
window.addEventListener("pagehide", destroyMotion);
document.addEventListener("livewire:navigating", destroyMotion);
document.addEventListener("livewire:navigated", () => {
  destroyMotion();
  initMotion();
});
