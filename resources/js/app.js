import Alpine from "alpinejs";
import { initMotion } from "./motion";

/**
 * Alpine handles small UI state only — the mobile nav, accordions, the booking
 * wizard's local interactions. Anything scroll-linked belongs in motion.js.
 */
window.Alpine = Alpine;
Alpine.start();

// The inline head script sets data-motion="on" before first paint so reveal
// targets never flash visible. If that script ran, it also armed a failsafe
// that strips the attribute when this module fails to load — disarm it now.
window.__coreMemoryMotionReady?.();

initMotion();
