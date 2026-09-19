<?php

declare(strict_types=1);

use App\Enums\ProjectCategory;
use App\Models\Post;
use App\Models\Project;
use App\Models\Testimonial;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    Settings::flush();
});

it('shows published projects on the work index', function () {
    $published = Project::factory()->create(['title' => 'Aisyah and Danial']);
    $draft = Project::factory()->draft()->create(['title' => 'Unpublished Story']);

    $this->get(route('work'))
        ->assertOk()
        ->assertSee('Aisyah and Danial')
        ->assertDontSee('Unpublished Story');
});

it('filters the work index by category', function () {
    Project::factory()->category(ProjectCategory::Wedding)->create(['title' => 'A Wedding Story']);
    Project::factory()->category(ProjectCategory::Video)->create(['title' => 'A Video Story']);

    $this->get(route('work', ['category' => 'wedding']))
        ->assertOk()
        ->assertSee('A Wedding Story')
        ->assertDontSee('A Video Story');
});

it('ignores an unknown category instead of failing', function () {
    // A stale or hand-edited ?category= must show everything, not throw a 500.
    Project::factory()->create(['title' => 'Still Visible']);

    $this->get(route('work', ['category' => 'not-a-real-category']))
        ->assertOk()
        ->assertSee('Still Visible');
});

it('shows a published wedding story', function () {
    $project = Project::factory()->create([
        'title' => 'Farah and Hafiz',
        'venue' => 'Masjid Wilayah',
        'couple_names' => 'Farah & Hafiz',
    ]);

    $this->get(route('work.show', $project))
        ->assertOk()
        ->assertSee('Farah and Hafiz')
        ->assertSee('Masjid Wilayah');
});

it('returns 404 for a draft story', function () {
    $draft = Project::factory()->draft()->create();

    $this->get(route('work.show', $draft))->assertNotFound();
});

it('returns 404 for a story scheduled in the future', function () {
    $scheduled = Project::factory()->create(['published_at' => now()->addWeek()]);

    $this->get(route('work.show', $scheduled))->assertNotFound();
});

it('keeps a slug stable when the title is edited', function () {
    // A shared or indexed /work/{slug} URL must keep working after an edit.
    $project = Project::factory()->create(['title' => 'Original Title']);
    $original = $project->slug;

    $project->update(['title' => 'A Completely Different Title']);

    expect($project->fresh()->slug)->toBe($original);
});

it('renders the homepage from database content', function () {
    Settings::set('hero.headline', 'A headline from settings');
    Project::factory()->featured()->create(['title' => 'Featured Couple']);
    Testimonial::factory()->create(['quote' => 'A lovely quote from a couple']);
    Post::factory()->create(['title' => 'A Journal Entry']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('A headline from settings')
        ->assertSee('Featured Couple')
        ->assertSee('A lovely quote from a couple')
        ->assertSee('A Journal Entry');
});

it('hides unpublished testimonials from the homepage', function () {
    Project::factory()->featured()->create();
    Testimonial::factory()->unpublished()->create(['quote' => 'This should stay hidden']);

    $this->get(route('home'))->assertOk()->assertDontSee('This should stay hidden');
});

it('does not cache Eloquent models, which breaks on unserialize', function () {
    // Regression test. Caching Eloquent collections in the database cache store
    // throws "tried to call a method on an incomplete object" at render time —
    // a 500 in production. Only primitives may cross the cache boundary.
    Project::factory()->featured()->create();

    $this->get(route('home'))->assertOk();

    $cached = Cache::get('home.payload');

    expect($cached)->toBeArray();

    foreach ($cached as $key => $value) {
        expect(is_scalar($value) || is_array($value) || is_null($value))
            ->toBeTrue("home.payload[{$key}] must be a primitive, not an object");
    }
});

it('busts the homepage cache when content is saved', function () {
    // sort_order is unsigned, so ordering is controlled with 0 as "first".
    Project::factory()->featured()->create(['title' => 'Before Edit', 'sort_order' => 5]);
    $this->get(route('home'))->assertOk()->assertSee('Before Edit');

    // An owner saving in the admin must see the change immediately, not after
    // the cache TTL expires.
    Project::factory()->featured()->create(['title' => 'After Edit', 'sort_order' => 0]);

    $this->get(route('home'))->assertOk()->assertSee('After Edit');
});

describe('Settings', function () {
    it('falls back to a documented default when unset', function () {
        expect(Settings::get('stats.years'))->toBe(Settings::DEFAULTS['stats.years']);
    });

    it('returns a saved value over the default', function () {
        Settings::set('stats.years', 9);

        expect(Settings::get('stats.years'))->toBe(9);
    });

    it('reads the whole table in one query', function () {
        Settings::setMany(['a.one' => 1, 'a.two' => 2, 'a.three' => 3], group: 'a');
        Settings::flush();

        DB::enableQueryLog();
        Settings::get('a.one');
        Settings::get('a.two');
        Settings::get('a.three');

        expect(DB::getQueryLog())->toHaveCount(1);
    });
});
