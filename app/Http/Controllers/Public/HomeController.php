<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Post;
use App\Models\Project;
use App\Models\Testimonial;
use App\Support\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    /**
     * The homepage.
     *
     * CACHING NOTE — read before changing this.
     *
     * We cache the *selection* (which record ids to show, in what order) and
     * the stat numbers, then hydrate the models fresh on each request.
     *
     * We do NOT cache the Eloquent collections themselves. Serialising models
     * into a cache store is fragile: PHP's unserialize() throws
     * "tried to call a method on an incomplete object" when a class isn't
     * loaded at the moment the cache is read, and media-library models make
     * that likely. It fails at render time, in production, as a 500 — which is
     * exactly what happened here before this was rewritten.
     *
     * Caching ids keeps the ordering and filtering logic cached while every
     * value crossing the cache boundary stays a plain int or string.
     *
     * Hydration is two indexed primary-key lookups; deeper page caching is a
     * Phase 5 concern, measured rather than guessed at.
     */
    public function __invoke(): View
    {
        $selection = Cache::remember('home.payload', now()->addHour(), function (): array {
            return [
                'featured_id' => Project::query()
                    ->published()->featured()->ordered()->value('id'),

                'project_ids' => Project::query()
                    ->published()->ordered()->limit(6)->pluck('id')->all(),

                // Every published testimonial, in the order the owner drags them
                // to in the admin. (This used to stop at three, silently.)
                'testimonial_ids' => Testimonial::query()
                    ->published()->ordered()->pluck('id')->all(),

                'post_ids' => Post::query()
                    ->published()->latest('published_at')->limit(3)->pluck('id')->all(),

                // One representative package per coverage type: the cheapest
                // way into Photo, Video, Photo & Video and Sessions. The
                // homepage teases the range; /packages carries all eighteen.
                'package_ids' => Package::query()
                    ->active()
                    ->ordered()
                    ->get(['id', 'category'])
                    ->groupBy(fn (Package $package) => $package->category?->value)
                    ->map(fn ($group) => $group->first()->id)
                    ->values()
                    ->all(),

                'stats' => [
                    ['value' => (int) Settings::get('stats.years'), 'label' => (string) Settings::get('stats.years_label'), 'pad' => true],
                    ['value' => (int) Settings::get('stats.weddings'), 'label' => (string) Settings::get('stats.weddings_label'), 'pad' => false],
                    ['value' => (int) Settings::get('stats.awards'), 'label' => (string) Settings::get('stats.awards_label'), 'pad' => true],
                    ['value' => (int) Settings::get('stats.couples'), 'label' => (string) Settings::get('stats.couples_label'), 'pad' => false],
                ],
            ];
        });

        // Hydrate. `with('media')` is essential — without it every card fires
        // its own query for a hero image, which is a textbook N+1 on the
        // busiest page on the site. preventLazyLoading() in AppServiceProvider
        // turns that mistake into a thrown exception locally rather than a
        // silent slowdown in production.
        $projects = Project::query()
            ->whereIn('id', $selection['project_ids'])
            ->ordered()
            ->with('media')
            ->get();

        return view('pages.home', [
            // Reuse the already-loaded model rather than querying it twice.
            'feature' => $projects->firstWhere('id', $selection['featured_id'])
                ?? Project::with('media')->find($selection['featured_id']),

            'projects' => $projects,

            'testimonials' => Testimonial::query()
                ->whereIn('id', $selection['testimonial_ids'])
                ->ordered()
                ->get(),

            'posts' => Post::query()
                ->whereIn('id', $selection['post_ids'])
                ->latest('published_at')
                ->with('media')
                ->get(),

            // Hydrated fresh from the cached ids — never cached as models.
            'packages' => Package::whereIn('id', $selection['package_ids'])
                ->ordered()
                ->get(),

            'stats' => $selection['stats'],
        ]);
    }
}
