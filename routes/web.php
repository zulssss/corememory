<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Web Routes
|------------------------------------------------------------------------------
| This file is a manifest, not a route table. Real routes live in the files it
| loads, so neither grows into an unreadable wall.
|
|   public.php  — the site a couple sees
|   admin.php   — admin routes that sit OUTSIDE the Filament panel
|                 (Filament registers /admin itself in AdminPanelProvider)
*/

require __DIR__.'/public.php';
require __DIR__.'/admin.php';
