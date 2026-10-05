<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a review or staging copy of the site out of search engines.
 *
 * Switched on with SITE_NOINDEX=true. A header rather than robots.txt:
 * robots.txt is a static file the web server returns without running Laravel,
 * so it cannot differ per environment — and it only asks crawlers not to
 * VISIT, while a page linked from elsewhere can still be indexed. The
 * X-Robots-Tag header is an instruction not to index at all.
 *
 * The review copy carries placeholder invoice details (SSM number, bank
 * account); they must never turn up in a search result.
 */
class NoIndexWhenRequested
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (config('app.noindex')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
