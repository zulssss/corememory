<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Project;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * sitemap.xml, generated on request and cached.
 *
 * Generated rather than written to disk by a scheduled command: this site has
 * tens of URLs, not tens of thousands, so building it live costs nothing and
 * removes a cron job that could silently stop running and leave a stale
 * sitemap behind.
 *
 * /book and /availability are deliberately absent — they are application
 * steps, not content, and indexing them just competes with /packages.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHours(6), function (): string {
            $sitemap = Sitemap::create();

            // Static pages, with priorities reflecting what actually matters:
            // packages is the page the whole site funnels toward.
            foreach ([
                ['home', 1.0, Url::CHANGE_FREQUENCY_WEEKLY],
                ['packages', 0.9, Url::CHANGE_FREQUENCY_MONTHLY],
                ['work', 0.8, Url::CHANGE_FREQUENCY_WEEKLY],
                ['about', 0.6, Url::CHANGE_FREQUENCY_YEARLY],
                ['journal', 0.6, Url::CHANGE_FREQUENCY_WEEKLY],
                ['contact', 0.5, Url::CHANGE_FREQUENCY_YEARLY],
            ] as [$route, $priority, $frequency]) {
                $sitemap->add(
                    Url::create(route($route))
                        ->setPriority($priority)
                        ->setChangeFrequency($frequency)
                );
            }

            Project::published()->ordered()->get(['slug', 'updated_at'])
                ->each(fn (Project $project) => $sitemap->add(
                    Url::create(route('work.show', $project->slug))
                        ->setLastModificationDate($project->updated_at)
                        ->setPriority(0.7)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                ));

            Post::published()->get(['slug', 'updated_at'])
                ->each(fn (Post $post) => $sitemap->add(
                    Url::create(route('journal.show', $post->slug))
                        ->setLastModificationDate($post->updated_at)
                        ->setPriority(0.5)
                        ->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY)
                ));

            return $sitemap->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
