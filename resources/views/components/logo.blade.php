@props([
    /* Accessible name. Pass null when the logo is purely decorative and the
       brand is already announced nearby — the footer's giant cropped repeat. */
    'label' => null,
])

{{--
    The CoreMemory wordmark.

    Clones the <path> that <x-logo-symbol> puts in the layout, so the 33 KB of
    path data is paid for once per page however many times this renders. The
    path carries fill="currentColor", so the mark takes its colour from the
    text colour around it — ink in the header, on-inverse in the footer — and
    stays restyleable from tokens.css.

    SIZE IS THE CALLER'S JOB. There is deliberately no default width or height
    here: $attributes->merge() CONCATENATES classes rather than replacing them,
    so a default of `w-full` plus a caller's `w-auto` would ship both, and which
    one won would depend on their order in the compiled stylesheet rather than
    on anything visible in this file.

    viewBox gives the element its intrinsic aspect ratio, so setting one of
    width or height and leaving the other `auto` is enough.
--}}
<svg
    {{ $attributes }}
    viewBox="0 -706 6040 959"
    @if ($label)
        role="img" aria-label="{{ $label }}"
    @else
        aria-hidden="true" focusable="false"
    @endif
>
    <use href="#cm-wordmark" />
</svg>
