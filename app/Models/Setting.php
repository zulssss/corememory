<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One owner-editable setting.
 *
 * Read settings through App\Support\Settings, not this model directly — that
 * facade caches the whole table in a single query and busts the cache on save.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['value' => 'array'];
    }
}
