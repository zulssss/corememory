<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * A studio team member. There are no client accounts — a couple never logs in,
 * which is why the booking wizard is public and the invoice download uses a
 * signed URL instead of authentication.
 *
 * Roles (seeded by RoleSeeder):
 *   super_admin — full access, including settings and user management
 *   admin       — the studio owner; everything except destructive settings
 *   staff       — view and update bookings; no pricing, no finance
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Gate for the Filament admin panel.
     *
     * Every team member has at least one role, so "has any role" is the check.
     * Finer-grained access (who may see finance, who may edit pricing) is
     * enforced per-resource with policies, not here.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(['super_admin', 'admin', 'staff']);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
