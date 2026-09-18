{{--
    Custom cursor — a small dot that scales up and reads VIEW over project
    thumbnails.

    Desktop + fine pointer only. CSS hides it on touch, and motion.js refuses
    to initialise it without both (hover: hover) and (pointer: fine), so a
    touchscreen laptop can't end up with a cursor chasing nothing.

    Mark a target with:  <a data-cursor="VIEW"> ... </a>
--}}
<div data-cursor-root aria-hidden="true"
     class="flex h-20 w-20 items-center justify-center rounded-full bg-paper">
    <span data-cursor-label
          class="font-mono text-micro uppercase tracking-micro text-ink opacity-0 transition-opacity duration-200 [[data-cursor-active]_&]:opacity-100"></span>
</div>
