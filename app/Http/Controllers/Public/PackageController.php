<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\PackageCategory;
use App\Http\Controllers\Controller;
use App\Models\AddOn;
use App\Models\Package;
use App\Support\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;

/**
 * /packages — the most important page after the homepage.
 *
 * Every price is visible without interaction. No "enquire for pricing", no
 * hover reveal, no gated PDF. The entire premise of this site is that a couple
 * can self-select before anyone from the studio spends time on them.
 */
class PackageController extends Controller
{
    public function __invoke(): View
    {
        // Cache the ids only — never Eloquent models. See CLAUDE.md § Caching.
        $ids = Cache::remember('packages.active', now()->addHour(), fn (): array => [
            'packages' => Package::active()->ordered()->pluck('id')->all(),
            'add_ons' => AddOn::active()->ordered()->pluck('id')->all(),
        ]);

        $packages = Package::whereIn('id', $ids['packages'])->ordered()->get();
        $addOns = AddOn::whereIn('id', $ids['add_ons'])->ordered()->get();

        /*
         * Grouped by coverage type. The pricelist carries eighteen packages;
         * as one flat list the comparison table would be eighteen columns
         * wide and unreadable, so the page renders one table per category.
         */
        $groups = collect(PackageCategory::ordered())
            ->map(fn (PackageCategory $category) => [
                'category' => $category,
                'packages' => $packages->where('category', $category)->values(),
            ])
            ->filter(fn (array $group) => $group['packages']->isNotEmpty())
            ->values();

        return view('pages.packages', [
            'packages' => $packages,
            'groups' => $groups,
            'addOns' => $addOns,
            'depositPerEvent' => Settings::depositPerEvent(),
        ]);
    }
}
