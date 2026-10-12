<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Vercel entry point — the review copy only
|--------------------------------------------------------------------------
|
| Vercel runs PHP as a serverless function (vercel-php runtime) whose
| filesystem is read-only except /tmp. Laravel writes a few caches and the
| compiled Blade views at boot, so those are pointed at /tmp before the
| framework starts. Public pages only: vercel.json returns 404 for /admin.
| The real site (Hostinger / a VPS) never loads this file.
|
*/

$base = dirname(__DIR__);
$tmp = '/tmp/corememory';

$env = [
    'APP_PACKAGES_CACHE' => "{$tmp}/cache/packages.php",
    'APP_SERVICES_CACHE' => "{$tmp}/cache/services.php",
    'APP_CONFIG_CACHE' => "{$tmp}/cache/config.php",
    'APP_ROUTES_CACHE' => "{$tmp}/cache/routes.php",
    'APP_EVENTS_CACHE' => "{$tmp}/cache/events.php",
    'VIEW_COMPILED_PATH' => "{$tmp}/views",
    'LOG_CHANNEL' => 'stderr',
];

// The preview runs on a SQLite snapshot of the public content. The bundle is
// read-only, so each cold start works on its own copy in /tmp: sessions and
// test bookings write there and are gone when the instance is recycled.
if (is_file("{$base}/database/review.sqlite")) {
    is_dir($tmp) || mkdir($tmp, 0755, true);
    is_file("{$tmp}/review.sqlite") || copy("{$base}/database/review.sqlite", "{$tmp}/review.sqlite");
    $env['DB_DATABASE'] = "{$tmp}/review.sqlite";
}

// Photo and asset URLs are built from APP_URL. Taking it from the request
// means the preview works on whichever *.vercel.app address serves it.
if ($host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? null) {
    $env['APP_URL'] = 'https://'.$host;
}

foreach ($env as $key => $value) {
    $_ENV[$key] = $_SERVER[$key] = $value;
    putenv("{$key}={$value}");
}

foreach (["{$tmp}/cache", "{$tmp}/views"] as $dir) {
    is_dir($dir) || mkdir($dir, 0755, true);
}

require $base.'/public/index.php';
