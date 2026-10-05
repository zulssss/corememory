import Alpine from "alpinejs";
import { initMotion, revealInWizard, scrollToWizardTop } from "./motion";

/**
 * Alpine handles small UI state only — the mobile nav, accordions, the booking
 * wizard's local interactions. Anything scroll-linked belongs in motion.js.
 *
 * EXACTLY ONE Alpine may start per page.
 *
 * Livewire auto-injects livewire.js on any page that renders a component, and
 * that bundle carries and starts its own Alpine. Starting this one as well put
 * two Alpine instances on /book and /availability, which Livewire warns makes
 * components behave erratically.
 *
 * So: on a page with a Livewire component, Livewire's Alpine runs everything
 * (including the header menu). On every other page — most of the site — this
 * lightweight Alpine runs instead, so the homepage never pays for the Livewire
 * runtime just to open a menu. This module is deferred, so the DOM is fully
 * parsed and the check below is reliable.
 */
const hasLivewire = document.querySelector("[wire\\:id]") !== null;

if (!hasLivewire) {
  window.Alpine = Alpine;
  Alpine.start();
}

// The inline head script sets data-motion="on" before first paint so reveal
// targets never flash visible. If that script ran, it also armed a failsafe
// that strips the attribute when this module fails to load — disarm it now.
window.__coreMemoryMotionReady?.();

initMotion();

// BookingWizard::rendering() dispatches this on every step change. Livewire
// fires it as a bubbling DOM event from the component, so it reaches window
// without importing Livewire here. Wait a frame so the morph has painted the
// new step before measuring.
window.addEventListener("wizard-step-changed", () => {
  requestAnimationFrame(scrollToWizardTop);
});

// Within a step: after picking a day, a session or a package, and after a
// failed Continue — see the dispatches in BookingWizard.
window.addEventListener("wizard-reveal", (event) => {
  const target = event.detail?.target;
  if (target) requestAnimationFrame(() => revealInWizard(target));
});

/* -------------------------------------------------------------------------
   PHONE — formats as the couple types: 0124522344 → 012-452 2344
   Plain event delegation rather than Alpine: it then behaves identically on
   /book (Livewire's Alpine) and /contact (ours), and survives Livewire morphs.
   The server (App\Support\Phone) normalises again, so this is a convenience,
   never the guarantee.
   ---------------------------------------------------------------------- */

/** 3-3-4, or 3-4-4 once there are eleven digits (011 and newer ranges). */
function formatMyMobile(digits) {
  const d = digits.slice(0, 11);
  if (d.length <= 3) return d;

  const split = d.length === 11 ? 7 : 6;
  if (d.length <= split) return `${d.slice(0, 3)}-${d.slice(3)}`;

  return `${d.slice(0, 3)}-${d.slice(3, split)} ${d.slice(split)}`;
}

document.addEventListener("input", (event) => {
  const input = event.target;
  if (!(input instanceof HTMLInputElement) || !input.matches("[data-phone-format]")) return;

  // Only on typing and paste. On backspace leave the text alone, otherwise a
  // deleted dash is re-added instantly and the couple gets stuck behind it.
  if (!event.inputType?.startsWith("insert")) return;

  // International input is left as typed; the server converts it.
  if (input.value.trim().startsWith("+")) return;

  const caret = input.selectionStart ?? input.value.length;
  const digitsBeforeCaret = input.value.slice(0, caret).replace(/\D/g, "").length;

  const formatted = formatMyMobile(input.value.replace(/\D/g, ""));
  if (formatted === input.value) return;

  input.value = formatted;

  // Put the caret back after the same number of digits it was after.
  let position = 0;
  for (let seen = 0; position < formatted.length && seen < digitsBeforeCaret; position++) {
    if (/\d/.test(formatted[position])) seen++;
  }
  input.setSelectionRange(position, position);
});
