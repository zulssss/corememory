<?php

declare(strict_types=1);

use App\Filament\Resources\Projects\Pages\EditProject;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Filament uploads default to config('filament.default_filesystem_disk'),
 * which is env('FILESYSTEM_DISK') — the PRIVATE 'local' disk in this app.
 *
 * Media that lands there is not web-servable. The public site then renders its
 * blur placeholder with a broken image over it, because the real file 403s.
 * Every image uploaded through the admin was arriving that way.
 *
 * These tests pin the disk each upload field must use. Receipts are the one
 * collection that must STAY private.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);
});

it('stores an admin-uploaded hero on a web-servable disk', function () {
    $project = Project::factory()->create();

    Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
        ->set('data.hero', [UploadedFile::fake()->image('hero.jpg', 2000, 1200)])
        ->call('save')
        ->assertHasNoFormErrors();

    $media = $project->refresh()->getFirstMedia('hero');

    expect($media)->not->toBeNull()
        ->and($media->disk)->not->toBe(
            config('filesystems.private_disk'),
            'Hero images landed on the private disk — the public site will 403 on them.'
        )
        // The media library's public disk — `public` locally, a public bucket
        // on Laravel Cloud, and an isolated folder under test.
        ->and($media->disk)->toBe(config('media-library.disk_name'));

    // Under test that disk must NOT be the folder holding the studio's real
    // photographs: uploading through the admin form used to write there.
    expect(config("filesystems.disks.{$media->disk}.root"))
        ->not->toBe(storage_path('app/public'));
});

it('stores an admin-uploaded gallery image on a web-servable disk', function () {
    $project = Project::factory()->create();

    Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
        ->set('data.gallery', [UploadedFile::fake()->image('g.jpg', 1600, 1200)])
        ->call('save')
        ->assertHasNoFormErrors();

    $media = $project->refresh()->getMedia('gallery');

    expect($media)->not->toBeEmpty()
        ->and($media->first()->disk)->not->toBe('local');
});

it('never leaves a public-facing collection on the private disk', function () {
    // A belt-and-braces sweep: whatever else is in the database, nothing a
    // visitor is meant to see may sit on a disk that cannot serve it.
    $stranded = Media::query()
        ->where('disk', 'local')
        ->whereIn('collection_name', ['hero', 'gallery', 'cover'])
        ->count();

    expect($stranded)->toBe(0);
});

it('keeps payment receipts on the private disk', function () {
    // The opposite guarantee: a receipt carries a client's banking details and
    // must never become web-servable by someone "fixing" the disk globally.
    $source = file_get_contents(base_path(
        'app/Filament/Resources/Invoices/RelationManagers/PaymentsRelationManager.php'
    ));

    expect($source)->toContain("->disk(config('filesystems.private_disk'))");
});
