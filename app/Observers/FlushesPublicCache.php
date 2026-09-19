<?php

declare(strict_types=1);

namespace App\Observers;

use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Busts the cached public-page payloads whenever content changes in the admin.
 *
 * Registered in AppServiceProvider for every model whose content appears on a
 * cached page. Without this, an owner saves a project and then sees no change
 * on the site for up to an hour — and reasonably concludes the save failed.
 */
class FlushesPublicCache
{
    public function saved(Model $model): void
    {
        $this->flush();
    }

    public function deleted(Model $model): void
    {
        $this->flush();
    }

    private function flush(): void
    {
        Cache::forget('home.payload');
        Settings::flush();
    }
}
