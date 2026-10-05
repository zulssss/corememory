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
| framework starts. The real site (Hostinger / a VPS) never loads this file.
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

// The hosted MySQL only accepts TLS; its CA certificate ships with the bundle.
if (is_file("{$base}/database/ca.pem")) {
    $env['MYSQL_ATTR_SSL_CA'] = "{$base}/database/ca.pem";
}

foreach ($env as $key => $value) {
    $_ENV[$key] = $_SERVER[$key] = $value;
    putenv("{$key}={$value}");
}

foreach (["{$tmp}/cache", "{$tmp}/views"] as $dir) {
    is_dir($dir) || mkdir($dir, 0755, true);
}

// Invoice PDFs and receipts live on the tmp_private disk. Seed it from the
// bundled copy once per cold start so existing invoices still download.
$private = "{$tmp}/private";
$bundled = "{$base}/storage/app/private";
if (! is_dir($private) && is_dir($bundled)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($bundled, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );
    foreach ($files as $file) {
        $target = $private.substr($file->getPathname(), strlen($bundled));
        $file->isDir() ? (is_dir($target) || mkdir($target, 0755, true)) : copy($file->getPathname(), $target);
    }
    is_dir($private) || mkdir($private, 0755, true);
}

require $base.'/public/index.php';
