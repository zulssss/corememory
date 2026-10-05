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

            {{-- Contact.

                 Read from Settings, not from lang/: these are values the owner
                 edits in the admin, and the footer previously showed a
                 hardcoded lang string that no admin edit could ever change.

                 Each is guarded, because an unset detail must render as
                 nothing rather than as an empty mailto: or a dead tel: link. --}}
            <div class="flex flex-col gap-2">
                <p class="font-mono text-micro uppercase tracking-micro text-on-inverse-muted">
                    {{ __('site.footer.get_in_touch') }}
                </p>

                @if ($phone = \App\Support\Settings::get('contact.phone'))
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', (string) $phone) }}"
                       class="text-statement leading-tight text-on-inverse transition-colors hover:text-accent-soft">
                        {{ $phone }}
                    </a>
                @endif

                @if ($email = \App\Support\Settings::get('contact.email'))
                    <a href="mailto:{{ $email }}"
                       class="text-statement uppercase leading-tight text-on-inverse transition-colors hover:text-accent-soft">
                        {{ $email }}
                    </a>
                @endif

                @if ($instagram = \App\Support\Settings::get('social.instagram'))
                    <a href="{{ $instagram }}" target="_blank" rel="noopener me"
                       class="text-statement leading-tight text-on-inverse transition-colors hover:text-accent-soft">
                        &commat;{{ \Illuminate\Support\Str::of($instagram)->rtrim('/')->afterLast('/') }}
                    </a>
                @endif
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

                {{-- Privacy is deliberately absent: the studio has no published
                     privacy policy yet, and a link to an invented one is worse
                     than no link. Add it back when there is a real policy. --}}
                <nav class="flex gap-6" aria-label="{{ __('site.footer.legal') }}">
                    <a href="{{ route('terms') }}" class="font-mono text-micro uppercase tracking-micro text-on-inverse-muted transition-colors hover:text-on-inverse">
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
        The giant wordmark, closing the footer band.
        aria-hidden because it's a decorative repeat of the brand name already
        announced in the header.
    --}}
    <div class="page-gutter pt-gutter pb-gutter text-on-inverse" aria-hidden="true">
        {{-- Shown WHOLE, not cropped. The old oversized text line deliberately
             ran past the viewport edge, but a real wordmark clipped mid-letter
             reads as a broken asset rather than a design choice.

             The padding is not optional. The wordmark's viewBox leaves only 6
             user units above the cap line and 14 below the descender, so at
             this size the letterforms sit flush against their own edges — with
             no padding the C collides with the hairline rule above it. --}}
        <x-logo class="block h-auto w-full" />
    </div>
</footer>
