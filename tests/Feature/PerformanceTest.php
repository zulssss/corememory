<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Cache::flush();
});

/** Data queries only — cache and session traffic is driver noise, not N+1. */
function dataQueryCount(string $url): int
{
    Cache::flush();
    test()->get($url);              // warm caches first

    DB::flushQueryLog();
    DB::enableQueryLog();
    test()->get($url);

    return collect(DB::getQueryLog())
        ->pluck('query')
        ->reject(fn (string $q) => str_contains($q, '`cache`')
            || str_contains($q, '`sessions`')
            || str_contains($q, '`jobs`'))
        ->count();
}

/** Gives a project a real hero image, so <img> tags actually render. */
function withHero(Project $project): Project
{
    $project->addMedia(public_path('images/placeholder/3x2-01.png'))
        ->preservingOriginal()
        ->toMediaCollection('hero');

    return $project;
}

describe('no N+1 queries', function () {
    /*
     * The real test is not "is it under N queries" — that is an arbitrary
     * ceiling that drifts. It is "does the count STAY THE SAME when the number
     * of records grows tenfold". A page that lazy-loads would climb; a page
     * with proper eager loading is flat.
     */
    it('keeps query count flat as records grow', function (string $route) {
        Project::factory()->count(3)->create();
        Post::factory()->count(3)->create();
        $small = dataQueryCount(route($route));

        Project::factory()->count(30)->create();
        Post::factory()->count(30)->create();
        $large = dataQueryCount(route($route));

        expect($large)->toBe($small);
    })->with(['home', 'work', 'journal', 'packages', 'about']);

    it('enables lazy-loading protection outside production', function () {
        // preventLazyLoading turns "we should check for N+1" into "the page
        // fails loudly". It is bound to isLocal(), so it is off in the testing
        // environment — assert the wiring rather than the runtime flag.
        $provider = file_get_contents(app_path('Providers/AppServiceProvider.php'));

        expect($provider)->toContain('Model::preventLazyLoading($this->app->isLocal())');
    });
});

describe('image delivery', function () {
    it('lazy-loads below the fold and prioritises the hero', function () {
        withHero(Project::factory()->featured()->create());
        Project::factory()->count(4)->create()->each(fn (Project $p) => withHero($p));

        $html = $this->get(route('home'))->assertOk()->getContent();

        expect($html)->toContain('loading="lazy"')
            // Exactly one eager image: the hero. More than one and the
            // browser competes with itself for the largest paint.
            ->and(substr_count($html, 'loading="eager"'))->toBe(1)
            ->and($html)->toContain('fetchpriority');
    });

    it('gives every image explicit dimensions so nothing shifts', function () {
        Project::factory()->count(3)->create()->each(fn (Project $p) => withHero($p));

        preg_match_all('/<img[^>]*>/', $this->get(route('home'))->getContent(), $matches);

        expect($matches[0])->not->toBeEmpty();

        // Cumulative Layout Shift is the easiest Lighthouse metric to fail,
        // and the cause is almost always an image with no reserved box.
        foreach ($matches[0] as $img) {
            expect($img)->toContain('width=')->and($img)->toContain('height=');
        }
    });

    it('serves a srcset so phones do not download desktop images', function () {
        Project::factory()->count(2)->create()->each(fn (Project $p) => withHero($p));

        $html = $this->get(route('home'))->getContent();

        expect($html)->toContain('sizes=')->and($html)->toContain('srcset=');
    });

    it('inlines a blurred placeholder rather than showing an empty box', function () {
        withHero(Project::factory()->featured()->create());

        expect($this->get(route('home'))->getContent())
            ->toContain('data:image/webp;base64');
    });
});

describe('motion', function () {
    it('renders content in its final state when JavaScript never arrives', function () {
        // data-motion is set by an inline head script and hides reveal targets.
        // The failsafe timer strips it if app.js fails — without that, a broken
        // bundle means a blank page.
        $html = $this->get(route('home'))->getContent();

        expect($html)->toContain('__coreMemoryMotionReady')
            ->and($html)->toContain('prefers-reduced-motion');
    });

    it('never animates a layout property', function () {
        // Animating width/height/top forces layout and drops frames. The whole
        // motion system is transform and opacity only.
        //
        // Scans GSAP calls specifically rather than the whole file — a naive
        // search for "width:" also matches the (min-width: 768px) media query,
        // which is a breakpoint, not an animation.
        $motion = file_get_contents(resource_path('js/motion.js'));

        preg_match_all('/gsap\.(to|from|fromTo|set)\((.*?)\);/s', $motion, $matches);

        expect($matches[0])->not->toBeEmpty();

        foreach ($matches[0] as $call) {
            foreach (['width:', 'height:', 'marginTop:', 'left:', 'top:', 'padding'] as $layoutProp) {
                expect($call)->not->toContain($layoutProp);
            }
        }
    });

    it('kills every ScrollTrigger on navigation', function () {
        $motion = file_get_contents(resource_path('js/motion.js'));

        expect($motion)->toContain('destroyMotion')
            ->and($motion)->toContain('pagehide')
            ->and($motion)->toContain('livewire:navigating');
    });
});
