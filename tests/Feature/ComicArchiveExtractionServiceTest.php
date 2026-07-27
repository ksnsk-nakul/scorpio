<?php

use App\Jobs\ExtractComicArchiveJob;
use App\Models\Media;
use App\Models\User;
use App\Services\ComicArchiveExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    $this->user = User::factory()->create();
});

function makeFixtureCbz(string $path): void
{
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE);
    $zip->addFromString('page-1.jpg', 'fake jpg bytes 1');
    $zip->addFromString('nested/page-2.jpg', 'fake jpg bytes 2');
    $zip->close();
}

it('extracts a cbz archive into ordered page images', function () {
    $media = Media::factory()->create([
        'disk' => 'local', 'path' => 'users/1/uploads/issue-1.cbz',
        'filename' => 'issue-1.cbz', 'mime_type' => 'application/zip',
    ]);

    $localFixture = storage_path('app/test-fixture.cbz');
    makeFixtureCbz($localFixture);
    Storage::disk('local')->put($media->path, file_get_contents($localFixture));
    unlink($localFixture);

    $manifest = (new ComicArchiveExtractionService())->extract($media);

    expect($manifest)->toHaveCount(2);
    Storage::disk('public')->assertExists("comics/{$media->id}/{$manifest[0]}");
    Storage::disk('public')->assertExists("comics/{$media->id}/{$manifest[1]}");
});

it('extracts a cbr archive via the unrar binary', function () {
    $media = Media::factory()->create([
        'disk' => 'local', 'path' => 'users/1/uploads/issue-2.cbr',
        'filename' => 'issue-2.cbr', 'mime_type' => 'application/x-rar-compressed',
    ]);
    Storage::disk('local')->put($media->path, 'fake rar bytes');

    Process::fake(function () use ($media) {
        $dir = storage_path("app/tmp-comics/{$media->id}");
        @mkdir($dir, 0755, true);
        file_put_contents("{$dir}/page-1.jpg", 'fake jpg bytes');
        return Process::result(output: '', errorOutput: '', exitCode: 0);
    });

    $manifest = (new ComicArchiveExtractionService())->extract($media);

    expect($manifest)->toHaveCount(1);
    Storage::disk('public')->assertExists("comics/{$media->id}/{$manifest[0]}");
});

it('marks a job failed when unrar reports an error', function () {
    $media = Media::factory()->create([
        'disk' => 'local', 'path' => 'users/1/uploads/issue-3.cbr',
        'filename' => 'issue-3.cbr', 'mime_type' => 'application/x-rar-compressed', 'status' => 'pending',
    ]);
    Storage::disk('local')->put($media->path, 'fake rar bytes');

    Process::fake(fn () => Process::result(output: '', errorOutput: 'unrar: not found', exitCode: 1));

    $job = new ExtractComicArchiveJob($media);
    try {
        $job->handle(new ComicArchiveExtractionService());
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($media->fresh()->status)->toBe('failed');
});
