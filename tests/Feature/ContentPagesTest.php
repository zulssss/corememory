<?php

declare(strict_types=1);

use App\Mail\EnquiryReceivedMail;
use App\Models\Enquiry;
use App\Models\Package;
use App\Models\Post;
use App\Models\Project;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Cache::flush();
    Settings::flush();
});

describe('the journal', function () {
    it('lists published posts and hides drafts', function () {
        Post::factory()->create(['title' => 'A Published Note']);
        Post::factory()->draft()->create(['title' => 'An Unpublished Note']);

        $this->get(route('journal'))
            ->assertOk()
            ->assertSee('A Published Note')
            ->assertDontSee('An Unpublished Note');
    });

    it('shows a post', function () {
        $post = Post::factory()->create(['title' => 'Why We Shoot Two Days', 'body' => 'Because it is two days.']);

        $this->get(route('journal.show', $post))
            ->assertOk()
            ->assertSee('Why We Shoot Two Days')
            ->assertSee('Because it is two days.');
    });

    it('returns 404 for a draft or a future post', function (bool $draft) {
        $post = $draft
            ? Post::factory()->draft()->create()
            : Post::factory()->create(['published_at' => now()->addWeek()]);

        $this->get(route('journal.show', $post))->assertNotFound();
    })->with([true, false]);
});

describe('about', function () {
    it('renders the process timeline', function () {
        $this->get(route('about'))
            ->assertOk()
            ->assertSee(__('about.headline'))
            ->assertSee(__('about.process')[0]['title']);
    });
});

describe('the terms page', function () {
    it('publishes every clause from the studio pricelist', function () {
        $response = $this->get(route('terms'))->assertOk();

        // Each group renders, and the clause count matches the lang file, so a
        // clause cannot be dropped silently when the terms are edited.
        $expected = collect(['booking', 'coverage', 'delivery', 'cancellation'])
            ->flatMap(fn (string $group) => __('terms.'.$group));

        expect($expected)->toHaveCount(17);

        foreach ($expected as $clause) {
            $response->assertSee($clause, escape: true);
        }
    });

    it('is reachable from the footer and listed in the sitemap', function () {
        $this->get('/')->assertOk()->assertSee(route('terms'), escape: false);
        $this->get(route('sitemap'))->assertOk()->assertSee(route('terms'), escape: false);
    });

    it('is linked from the booking consent checkbox', function () {
        // A couple must be able to read what they are accepting before they
        // accept it, not after.
        $this->get(route('book'))->assertOk()->assertSee(route('terms'), escape: false);
    });
});

describe('the contact form', function () {
    it('renders', function () {
        $this->get(route('contact'))->assertOk();
    });

    it('stores a message and notifies the studio', function () {
        Mail::fake();
        Settings::set('contact.email', 'studio@example.test');

        $this->post(route('contact.store'), [
            'name' => 'Aisyah',
            'email' => 'aisyah@example.test',
            'phone' => '012-345 6789',
            'message' => 'Do you photograph weddings in Penang?',
        ])->assertRedirect(route('contact'))->assertSessionHas('enquiry_sent');

        $enquiry = Enquiry::firstOrFail();

        expect($enquiry->name)->toBe('Aisyah')
            ->and($enquiry->status->value)->toBe('new');

        Mail::assertQueued(EnquiryReceivedMail::class,
            fn ($mail) => $mail->hasTo('studio@example.test'));
    });

    it('notifies the studio address when no contact email is set', function () {
        /*
         * The live configuration: contact.email is blank because the studio's
         * pricelist never gave one, so the fallback chain decides where an
         * enquiry lands. It used to fall back to mail.from.address — the
         * address the site sends FROM — so notifications went to the wrong
         * mailbox while booking notifications went to the studio. The test
         * above only ever exercised the first branch.
         */
        Mail::fake();
        Settings::set('contact.email', '');
        config()->set('mail.studio_address', 'studio@corememory.test');
        config()->set('mail.from.address', 'noreply@corememory.test');

        $this->post(route('contact.store'), [
            'name' => 'Syamim',
            'email' => 'syamim@example.test',
            'message' => 'Are you free in March?',
        ])->assertRedirect(route('contact'));

        Mail::assertQueued(
            EnquiryReceivedMail::class,
            fn ($mail) => $mail->hasTo('studio@corememory.test')
                && ! $mail->hasTo('noreply@corememory.test')
        );
    });

    it('still stores the message when there is nowhere to notify', function () {
        // A missing studio address must never cost the studio the enquiry.
        Mail::fake();
        Settings::set('contact.email', '');
        config()->set('mail.studio_address', null);
        config()->set('mail.from.address', null);

        $this->post(route('contact.store'), [
            'name' => 'Aminah',
            'email' => 'aminah@example.test',
            'message' => 'Do you have a date free in November for a nikah?',
        ])->assertRedirect(route('contact'))->assertSessionHas('enquiry_sent');

        expect(Enquiry::where('email', 'aminah@example.test')->exists())->toBeTrue();
        Mail::assertNothingQueued();
    });

    it('clears the form after a successful send', function () {
        // The visitor must be able to tell the message went. A banner over a
        // form still holding their text reads as "it did not send".
        $html = $this->followingRedirects()->post(route('contact.store'), [
            'name' => 'Nurul',
            'email' => 'nurul@example.test',
            'message' => 'Checking a date',
        ])->assertOk()->getContent();

        expect($html)->toContain(__('contact.sent.label'));

        preg_match('/name="name"[^>]*value="([^"]*)"/', $html, $name);
        expect($name[1] ?? '')->toBe('');
    });

    it('stores the phone in one format, whatever was typed', function () {
        $this->post(route('contact.store'), [
            'name' => 'Aisyah',
            'email' => 'aisyah@example.test',
            'phone' => "012\u{2011}452 2344 ",
            'message' => 'Do you have a date free in November?',
        ])->assertSessionHasNoErrors();

        expect(Enquiry::where('email', 'aisyah@example.test')->sole()->phone)->toBe('012-452 2344');
    });

    it('validates through the Form Request', function (array $payload, string $field) {
        $this->post(route('contact.store'), $payload)->assertSessionHasErrors([$field]);

        expect(Enquiry::count())->toBe(0);
    })->with([
        'missing name' => [['email' => 'a@b.test', 'message' => 'A long enough message here.'], 'name'],
        'bad email' => [['name' => 'Aisyah', 'email' => 'nope', 'message' => 'A long enough message here.'], 'email'],
        'short message' => [['name' => 'Aisyah', 'email' => 'a@b.test', 'message' => 'Hi'], 'message'],
        'bad phone' => [['name' => 'Aisyah', 'email' => 'a@b.test', 'phone' => 'call me', 'message' => 'A long enough message.'], 'phone'],
    ]);

    it('lets a phone number be omitted', function () {
        // Someone asking a general question should not have to hand one over.
        $this->post(route('contact.store'), [
            'name' => 'Aisyah',
            'email' => 'aisyah@example.test',
            'message' => 'A general question about your packages.',
        ])->assertSessionHasNoErrors();

        expect(Enquiry::count())->toBe(1);
    });

    it('silently drops a submission that fills the honeypot', function () {
        $this->post(route('contact.store'), [
            'name' => 'Spam Bot',
            'email' => 'bot@example.test',
            'message' => 'Buy cheap things at my website right now.',
            config('booking.honeypot_field') => 'http://spam.example',
        ])->assertRedirect(route('contact'));

        // Looks successful to the bot; nothing is stored.
        expect(Enquiry::count())->toBe(0);
    });

    it('works without JavaScript — it is a plain POST', function () {
        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('method="POST"', escape: false)
            ->assertSee(route('contact.store'), escape: false);
    });
});

describe('SEO', function () {
    it('emits LocalBusiness structured data site-wide', function () {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('application/ld+json', escape: false)
            ->assertSee('"@type":"Photograph"', escape: false);
    });

    it('emits Service structured data with prices on the packages page', function () {
        Package::factory()->priced(680000)->create(['name' => 'Wedding Classic']);

        $this->get(route('packages'))
            ->assertOk()
            ->assertSee('"@type":"Service"', escape: false)
            ->assertSee('"priceCurrency":"MYR"', escape: false)
            ->assertSee('"@type":"BreadcrumbList"', escape: false);
    });

    it('emits Article structured data on a journal post', function () {
        $post = Post::factory()->create(['title' => 'A Note']);

        $this->get(route('journal.show', $post))
            ->assertOk()
            ->assertSee('"@type":"Article"', escape: false)
            ->assertSee('og:type" content="article"', escape: false);
    });

    it('gives every page a canonical URL and Open Graph tags', function (string $route) {
        $this->get(route($route))
            ->assertOk()
            ->assertSee('rel="canonical"', escape: false)
            ->assertSee('property="og:title"', escape: false);
    })->with(['home', 'work', 'packages', 'about', 'journal', 'contact']);

    it('builds a sitemap of published content only', function () {
        Project::factory()->create(['title' => 'Published Wedding']);
        Project::factory()->draft()->create(['title' => 'Draft Wedding']);
        Post::factory()->create(['title' => 'Published Note']);

        $response = $this->get('/sitemap.xml');

        $response->assertOk()->assertHeader('content-type', 'application/xml');

        $xml = $response->getContent();

        expect($xml)->toContain('published-wedding')
            ->and($xml)->not->toContain('draft-wedding')
            ->and($xml)->toContain('published-note')
            // Application steps are not content and must not be indexed.
            ->and($xml)->not->toContain('/book')
            ->and($xml)->not->toContain('/availability');
    });

    it('keeps crawlers out of admin, booking and signed downloads', function () {
        $robots = file_get_contents(public_path('robots.txt'));

        expect($robots)->toContain('Disallow: /admin')
            ->and($robots)->toContain('Disallow: /book')
            ->and($robots)->toContain('Disallow: /invoices/')
            ->and($robots)->toContain('Sitemap:');
    });
});

describe('accessibility basics', function () {
    it('gives every page exactly one h1 and a skip link', function (string $route) {
        $html = $this->get(route($route))->assertOk()->getContent();

        expect(substr_count($html, '<h1'))->toBe(1)
            ->and($html)->toContain('Skip to content')
            ->and($html)->toContain('<html lang=');
    })->with(['home', 'work', 'packages', 'about', 'journal', 'contact', 'book']);

    it('gives every image an alt attribute', function (string $route) {
        $html = $this->get(route($route))->assertOk()->getContent();

        preg_match_all('/<img[^>]*>/', $html, $matches);

        foreach ($matches[0] as $img) {
            expect($img)->toContain('alt=');
        }
    })->with(['home', 'work', 'about']);

    it('associates form errors with their field', function () {
        $this->post(route('contact.store'), ['name' => '', 'email' => 'bad', 'message' => 'x']);

        $html = $this->get(route('contact'))->getContent();

        // aria-describedby is what makes an error announced with the input
        // rather than read as loose text elsewhere on the page.
        expect($html)->toContain('aria-describedby="email-error"')
            ->and($html)->toContain('aria-invalid="true"');
    });
});
