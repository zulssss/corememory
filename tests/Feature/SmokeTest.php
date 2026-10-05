<?php

declare(strict_types=1);

use App\Filament\Pages\BookingCalendar;
use App\Filament\Pages\FinanceDashboard;
use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\AddOns\AddOnResource;
use App\Filament\Resources\BlockedDates\BlockedDateResource;
use App\Filament\Resources\Bookings\BookingResource;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Packages\PackageResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Testimonials\TestimonialResource;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Post;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Smoke test — every page, on the full demo dataset
|--------------------------------------------------------------------------
|
| Opens every public page and every admin page, for every role, against the
| same data `php artisan migrate:fresh --seed` produces. Not a substitute for
| the focused tests — a net under them: a page that 500s because of a missing
| relation, a bad cast or a view typo fails here even if nobody wrote a test
| for that page.
|
| Seeds the whole demo site per check, so it takes minutes: it is grouped
| `smoke` and left out of the everyday run. Before publishing:
|
|     php artisan test --group=smoke
|
*/

pest()->group('smoke');

beforeEach(function () {
    // The seeder renders invoice PDFs whose numbers match real ones. Fake the
    // private disk so a test run can never overwrite the studio's documents.
    Storage::fake(config('filesystems.private_disk', 'local'));

    $this->seed(DatabaseSeeder::class);

    // The seeder leaves journal posts without a cover, so a cover-only code
    // path was never exercised — and every post WITH a cover 500'd on the live
    // site (Seo asked for a `full` size Post never defined). Real posts have
    // covers; so do these.
    Post::query()->each(fn (Post $post) => $post
        ->addMedia(UploadedFile::fake()->image('cover.jpg', 1600, 900))
        ->toMediaCollection('cover'));
});

it('serves every public page', function () {
    $urls = [
        route('home'),
        route('work'),
        route('packages'),
        route('availability'),
        route('book'),
        route('about'),
        route('journal'),
        route('contact'),
        route('terms'),
        route('sitemap'),
    ];

    foreach (Project::query()->published()->pluck('slug') as $slug) {
        $urls[] = route('work.show', $slug);
    }
    foreach (Post::query()->published()->pluck('slug') as $slug) {
        $urls[] = route('journal.show', $slug);
    }
    $urls[] = route('book.thanks', Booking::query()->value('reference'));

    expect(count($urls))->toBeGreaterThan(12);

    $failures = [];
    foreach ($urls as $url) {
        $status = $this->get($url)->getStatusCode();
        if ($status !== 200) {
            $failures[] = "{$status} {$url}";
        }
    }

    expect($failures)->toBe([]);
});

it('serves an invoice to the couple through its signed link', function () {
    $invoice = Invoice::query()->whereNotNull('pdf_path')->firstOrFail();

    $this->get($invoice->downloadUrl())->assertOk();

    // And refuses the same URL without its signature.
    $this->get(route('invoices.download', $invoice))->assertForbidden();
});

it('opens every admin page the role is allowed, and refuses the rest', function (string $email, array $refused) {
    $this->actingAs(User::where('email', $email)->firstOrFail());

    $resources = [
        BookingResource::class, EnquiryResource::class, BlockedDateResource::class,
        InvoiceResource::class, PackageResource::class, AddOnResource::class,
        ProjectResource::class, TestimonialResource::class, PostResource::class,
    ];

    // [url, expected status]
    $checks = [[url('/admin'), 200]];

    foreach ($resources as $resource) {
        $expected = in_array($resource, $refused, true) ? 403 : 200;
        $record = $resource::getModel()::query()->first();

        foreach (array_keys($resource::getPages()) as $page) {
            $needsRecord = in_array($page, ['edit', 'view'], true);
            if ($needsRecord && ! $record) {
                continue;
            }
            $checks[] = [$needsRecord ? $resource::getUrl($page, ['record' => $record]) : $resource::getUrl($page), $expected];
        }
    }

    foreach ([BookingCalendar::class, FinanceDashboard::class, ManageSettings::class] as $page) {
        $checks[] = [$page::getUrl(), in_array($page, $refused, true) ? 403 : 200];
    }

    $failures = [];
    foreach ($checks as [$url, $expected]) {
        $status = $this->get($url)->getStatusCode();
        if ($status !== $expected) {
            $failures[] = "expected {$expected}, got {$status}: {$url}";
        }
    }

    expect(count($checks))->toBeGreaterThan(20)
        ->and($failures)->toBe([]);
})->with([
    'super admin' => ['super@corememory.test', []],
    'owner' => ['owner@corememory.test', []],
    'staff' => ['staff@corememory.test', [InvoiceResource::class, FinanceDashboard::class, ManageSettings::class]],
]);
