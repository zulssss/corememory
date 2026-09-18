{{--
    Footer: inverted near-black band with the giant wordmark cropped by the
    viewport edge (per the reference layout).

    The inversion uses the dedicated inverse-* tokens rather than hardcoded
    dark colours, so the footer restyles from tokens.css like everything else.

    Contact details are placeholders until Phase 2 wires them to admin settings.
--}}
<footer class="bg-inverse text-on-inverse">
    <div class="page-gutter pt-section">

        <div class="flex flex-col gap-10 border-b border-inverse-rule pb-12 md:flex-row md:items-start md:justify-between">

            {{-- Contact --}}
            <div class="flex flex-col gap-2">
                <p class="font-mono text-micro uppercase tracking-micro text-on-inverse-muted">
                    {{ __('site.footer.get_in_touch') }}
                </p>
                <a href="tel:{{ __('site.contact.phone_href') }}"
                   class="text-statement leading-tight text-on-inverse transition-colors hover:text-accent-soft">
                    {{ __('site.contact.phone') }}
                </a>
                <a href="mailto:{{ __('site.contact.email') }}"
                   class="text-statement uppercase leading-tight text-on-inverse transition-colors hover:text-accent-soft">
                    {{ __('site.contact.email') }}
                </a>
            </div>

            {{-- Socials + legal --}}
            <div class="flex flex-col gap-6 md:items-end">
                <nav class="flex gap-6" aria-label="{{ __('site.footer.social') }}">
                    @foreach (['instagram', 'tiktok', 'whatsapp'] as $social)
                        <a href="#"
                           class="font-mono text-micro uppercase tracking-micro text-on-inverse-muted transition-colors hover:text-on-inverse">
                            {{ __('site.social.'.$social) }}
                        </a>
                    @endforeach
                </nav>

                <nav class="flex gap-6" aria-label="{{ __('site.footer.legal') }}">
                    <a href="#" class="font-mono text-micro uppercase tracking-micro text-on-inverse-muted transition-colors hover:text-on-inverse">
                        {{ __('site.footer.privacy') }}
                    </a>
                    <a href="#" class="font-mono text-micro uppercase tracking-micro text-on-inverse-muted transition-colors hover:text-on-inverse">
                        {{ __('site.footer.terms') }}
                    </a>
                </nav>

                <p class="font-mono text-micro uppercase tracking-micro text-on-inverse-muted">
                    &copy; {{ now()->year }} {{ __('site.brand.legal_name') }}
                </p>
            </div>
        </div>
    </div>

    {{--
        The giant wordmark. overflow-hidden on the wrapper plus a whitespace-nowrap
        line lets it run past the viewport edge and be cropped, which is the
        intended effect — not an accident.
        aria-hidden because it's a decorative repeat of the brand name already
        announced in the header.
    --}}
    <div class="overflow-hidden" aria-hidden="true">
        <p class="select-none whitespace-nowrap px-[0.02em] text-wordmark font-semibold leading-none tracking-tighter text-on-inverse">
            {{ __('site.brand.name') }}<span class="text-on-inverse-muted">&copy;</span>
        </p>
    </div>
</footer>
