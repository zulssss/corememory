<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\BlockedDate;
use App\Models\Post;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Observers\BlockedDateObserver;
use App\Observers\FlushesPublicCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Saving content in the admin must be visible on the site immediately,
        // not after the cache TTL expires.
        foreach ([Project::class, Testimonial::class, Post::class, Setting::class] as $model) {
            $model::observe(FlushesPublicCache::class);
        }

        // Blocking a date in the admin must immediately change what the
        // public calendar offers, so holds are kept in sync by an observer.
        BlockedDate::observe(BlockedDateObserver::class);

        // Fail loudly in development when a relationship wasn't eager-loaded.
        // The brief requires zero N+1 queries on any index page, and this is
        // what turns "we should check" into "the page throws".
        Model::preventLazyLoading($this->app->isLocal());

        // Mass-assignment protection stays on; models declare $fillable.
        Model::preventSilentlyDiscardingAttributes($this->app->isLocal());

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
