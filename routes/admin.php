<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Admin Routes (outside Filament)
|------------------------------------------------------------------------------
| The Filament panel registers its own routes at /admin via
| app/Providers/Filament/AdminPanelProvider.php — nothing here duplicates that.
|
| This file is for admin-adjacent routes Filament does not own. Phase 4 adds:
|
|   - The signed, expiring invoice download link. It has no login (a client
|     must be able to open it from an email) but the URL is signed so it can't
|     be guessed or shared past its expiry.
|
| Deliberately empty until then.
*/
