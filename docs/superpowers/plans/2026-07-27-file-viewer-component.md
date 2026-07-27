# Universal File Viewer Component Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a reusable `<FileViewer>` Vue component and its Laravel backend conversion pipeline that renders every format in the spec (images, audio, video, PDF, Office docs, CBZ/CBR comics, EPUB, TXT/MD/CSV) inline or fullscreen, on top of the existing `Media` model.

**Architecture:** Backend adds a `status` lifecycle to `Media` (`ready`/`pending`/`processing`/`failed`) plus two queued jobs that convert Office docs to PDF (LibreOffice headless) and extract comic archives to page images (PHP `ZipArchive` for CBZ, `unrar` CLI for CBR), caching output to the `public` disk. Frontend adds a `FileViewer.vue` dispatcher that picks a renderer by file extension and a `useReaderTheme` composable for sepia/dark/light reading themes, independent of the app's (nonexistent) dark mode.

**Tech Stack:** Laravel 13 (Process facade, queued jobs, Pest), Vue 3 `<script setup>`, Vite, pdf.js, epub.js, marked, PapaParse, Vitest + @vue/test-utils (new — this repo has no JS test runner yet).

**Reference spec:** `docs/superpowers/specs/2026-07-27-file-viewer-component-design.md`

---

## File Map

**Backend — new**
- `database/migrations/2026_07_27_100001_add_status_to_media_table.php`
- `app/Services/OfficeConversionService.php`
- `app/Services/ComicArchiveExtractionService.php`
- `app/Jobs/ConvertOfficeDocumentJob.php`
- `app/Jobs/ExtractComicArchiveJob.php`
- `tests/Feature/OfficeConversionServiceTest.php`
- `tests/Feature/ComicArchiveExtractionServiceTest.php`
- `tests/Feature/MediaStatusEndpointTest.php`
- `tests/Feature/support/fixtures/sample.cbz` (test fixture, built in Task 7)

**Backend — modified**
- `app/Models/Media.php` — status/extension helpers, converted-URL accessors
- `app/Services/MediaService.php` — expand allowed types, dispatch conversion jobs
- `app/Http/Controllers/Admin/MediaController.php` — status endpoint, expose `status` in store response
- `config/media.php` — `office_binary` / `unrar_binary` config
- `.env.example` — `SOFFICE_BINARY` / `UNRAR_BINARY`
- `routes/web.php` — status route
- `tests/Feature/MediaUploadTest.php` — fix now-invalid "rejects PDF" case, add new-type coverage

**Frontend — new**
- `resources/js/Components/FileViewer/rendererMap.js`
- `resources/js/Components/FileViewer/FileViewer.vue`
- `resources/js/Components/FileViewer/ProcessingState.vue`
- `resources/js/Components/FileViewer/UnsupportedRenderer.vue`
- `resources/js/Components/FileViewer/renderers/ImageRenderer.vue`
- `resources/js/Components/FileViewer/renderers/VideoRenderer.vue`
- `resources/js/Components/FileViewer/renderers/AudioRenderer.vue`
- `resources/js/Components/FileViewer/renderers/TextRenderer.vue`
- `resources/js/Components/FileViewer/renderers/PdfRenderer.vue`
- `resources/js/Components/FileViewer/renderers/OfficeRenderer.vue`
- `resources/js/Components/FileViewer/renderers/ComicRenderer.vue`
- `resources/js/Components/FileViewer/renderers/EpubRenderer.vue`
- `resources/js/Composables/useReaderTheme.js`
- `tests/js/rendererMap.test.js`
- `tests/js/useReaderTheme.test.js`
- `tests/js/FileViewer.test.js`
- `tests/js/TextRenderer.test.js`
- `tests/js/ComicRenderer.test.js`
- `vitest.config.js`

**Frontend — modified**
- `package.json` — new deps + `test:unit` script
- `docs/INSTALLATION.md`, `docs/CONFIGURATION.md` — `soffice`/`unrar` prerequisites

---

## Task 1: Migration — add status columns to `media`

**Files:**
- Create: `database/migrations/2026_07_27_100001_add_status_to_media_table.php`

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('status')->default('ready')->after('alt_text');
            $table->string('status_reason')->nullable()->after('status');
            $table->json('page_manifest')->nullable()->after('status_reason');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn(['status', 'status_reason', 'page_manifest']);
        });
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate`
Expected: `2026_07_27_100001_add_status_to_media_table ... DONE`

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_07_27_100001_add_status_to_media_table.php
git commit -m "feat: add status/page_manifest columns to media table"
```

---

## Task 2: `Media` model — status helpers and URL accessors

**Files:**
- Modify: `app/Models/Media.php`
- Test: `tests/Unit/MediaTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->user = User::factory()->create();
});

it('detects office documents that need conversion by mime type', function () {
    $media = Media::factory()->create(['mime_type' => 'application/msword', 'filename' => 'report.doc']);
    expect($media->needsOfficeConversion())->toBeTrue();

    $media = Media::factory()->create(['mime_type' => 'image/png', 'filename' => 'photo.png']);
    expect($media->needsOfficeConversion())->toBeFalse();
});

it('detects comic archives by filename extension', function () {
    $cbz = Media::factory()->create(['filename' => 'issue-1.cbz', 'mime_type' => 'application/zip']);
    $cbr = Media::factory()->create(['filename' => 'issue-2.cbr', 'mime_type' => 'application/x-rar-compressed']);
    $pdf = Media::factory()->create(['filename' => 'issue-3.pdf', 'mime_type' => 'application/pdf']);

    expect($cbz->needsComicExtraction())->toBeTrue()
        ->and($cbr->needsComicExtraction())->toBeTrue()
        ->and($pdf->needsComicExtraction())->toBeFalse();
});

it('builds a converted PDF url only once the office doc is ready', function () {
    $media = Media::factory()->create([
        'mime_type' => 'application/msword', 'filename' => 'report.doc', 'status' => 'processing',
    ]);
    expect($media->converted_pdf_url)->toBeNull();

    $media->update(['status' => 'ready']);
    expect($media->fresh()->converted_pdf_url)->toContain("conversions/{$media->id}.pdf");
});

it('builds comic page urls from the page manifest', function () {
    $media = Media::factory()->create([
        'filename' => 'issue-1.cbz', 'mime_type' => 'application/zip',
        'status' => 'ready', 'page_manifest' => ['page-001.jpg', 'page-002.jpg'],
    ]);

    expect($media->comic_page_urls)->toHaveCount(2)
        ->and($media->comic_page_urls[0])->toContain("comics/{$media->id}/page-001.jpg");
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/MediaTest.php`
Expected: FAIL — `needsOfficeConversion()`/`needsComicExtraction()`/`converted_pdf_url`/`comic_page_urls` undefined

- [ ] **Step 3: Check `Media` has a factory**

Run: `find database/factories -iname "MediaFactory.php"`

If it doesn't exist, create `database/factories/MediaFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'   => User::factory(),
            'disk'      => 'public',
            'path'      => 'users/1/uploads/' . $this->faker->uuid() . '.bin',
            'filename'  => $this->faker->word() . '.bin',
            'mime_type' => 'application/octet-stream',
            'size'      => 1024,
        ];
    }
}
```

- [ ] **Step 4: Implement the model changes**

Replace `app/Models/Media.php` with:

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    public const OFFICE_MIMES = [
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.oasis.opendocument.text',
    ];

    public const COMIC_EXTENSIONS = ['cbz', 'cbr'];

    protected $fillable = ['user_id','disk','path','filename','mime_type','size','alt_text','status','status_reason','page_manifest'];

    protected $casts = [
        'page_manifest' => 'array',
    ];

    protected $attributes = [
        'status' => 'ready',
    ];

    public function mediable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getUrlAttribute(): string
    {
        if ($this->disk === 'public') {
            return asset('storage/' . $this->path);
        }
        if ($this->disk === 'static') {
            return asset($this->path);
        }
        return Storage::disk($this->disk)->url($this->path);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->filename, PATHINFO_EXTENSION));
    }

    public function needsOfficeConversion(): bool
    {
        return in_array($this->mime_type, self::OFFICE_MIMES, true);
    }

    public function needsComicExtraction(): bool
    {
        return in_array($this->extension(), self::COMIC_EXTENSIONS, true);
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isProcessing(): bool
    {
        return in_array($this->status, ['pending', 'processing'], true);
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function getConvertedPdfUrlAttribute(): ?string
    {
        if (! $this->needsOfficeConversion() || ! $this->isReady()) {
            return null;
        }

        return Storage::disk('public')->url("conversions/{$this->id}.pdf");
    }

    public function getComicPageUrlsAttribute(): array
    {
        if (! $this->needsComicExtraction() || empty($this->page_manifest)) {
            return [];
        }

        return collect($this->page_manifest)
            ->map(fn (string $filename) => Storage::disk('public')->url("comics/{$this->id}/{$filename}"))
            ->all();
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Unit/MediaTest.php`
Expected: PASS (4 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Models/Media.php tests/Unit/MediaTest.php database/factories/MediaFactory.php
git commit -m "feat: add status helpers and converted-URL accessors to Media"
```

---

## Task 3: Expand `MediaService` allowed types

**Files:**
- Modify: `app/Services/MediaService.php`
- Modify: `tests/Feature/MediaUploadTest.php`

The existing test `it('rejects disallowed mime types')` uploads a `.pdf` and asserts 422 — that becomes wrong once PDF is allowed, so it must change to a type that's still actually disallowed.

- [ ] **Step 1: Update the failing/changed tests**

In `tests/Feature/MediaUploadTest.php`, replace the third test:

```php
it('rejects disallowed mime types', function () {
    $file = UploadedFile::fake()->create('app.exe', 100, 'application/x-msdownload');

    $this->actingAs($this->admin)
        ->postJson('/admin/media', ['file' => $file])
        ->assertStatus(422);
});

it('accepts a pdf upload', function () {
    $file = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');

    $this->actingAs($this->admin)
        ->postJson('/admin/media', ['file' => $file])
        ->assertOk()
        ->assertJsonPath('mime_type', 'application/pdf');
});

it('accepts a cbz upload by extension even though the mime is generic zip', function () {
    $file = UploadedFile::fake()->create('issue-1.cbz', 100, 'application/zip');

    $this->actingAs($this->admin)
        ->postJson('/admin/media', ['file' => $file])
        ->assertOk();
});

it('rejects a zip upload that is not named cbz or cbr', function () {
    $file = UploadedFile::fake()->create('archive.zip', 100, 'application/zip');

    $this->actingAs($this->admin)
        ->postJson('/admin/media', ['file' => $file])
        ->assertStatus(422);
});
```

- [ ] **Step 2: Run tests to verify the new/changed ones fail**

Run: `php artisan test tests/Feature/MediaUploadTest.php`
Expected: FAIL — PDF/cbz uploads currently return 422 (not yet allowed)

- [ ] **Step 3: Implement the expanded allow-list**

In `app/Services/MediaService.php`, replace the `ALLOWED_MIMES` constant and the body of `store()` up to `$ext = ...`:

```php
private const ALLOWED_MIMES = [
    'image/jpeg','image/png','image/gif','image/webp','image/svg+xml',
    'video/mp4','video/quicktime','video/webm',
    'audio/mpeg','audio/wav','audio/x-wav',
    'text/plain','text/markdown','text/csv',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.oasis.opendocument.text',
    'application/epub+zip',
];

// Zip/RAR mime detection varies by OS and PHP build, so archive uploads are
// only trusted when the filename extension confirms cbz/cbr.
private const ARCHIVE_MIMES_BY_EXTENSION = [
    'cbz' => ['application/zip', 'application/vnd.comicbook+zip', 'application/octet-stream'],
    'cbr' => ['application/vnd.rar', 'application/x-rar-compressed', 'application/octet-stream'],
];

public function store(UploadedFile $file, User $user, string $context = 'default'): Media
{
    $maxMb = (int) Setting::get('media_max_size_mb', 50);
    $mime  = $file->getMimeType();
    $ext   = strtolower($file->getClientOriginalExtension());

    $isArchive = isset(self::ARCHIVE_MIMES_BY_EXTENSION[$ext])
        && in_array($mime, self::ARCHIVE_MIMES_BY_EXTENSION[$ext], true);

    if (! $isArchive && ! in_array($mime, self::ALLOWED_MIMES, true)) {
        throw ValidationException::withMessages(['file' => 'File type not allowed.']);
    }

    if ($file->getSize() > $maxMb * 1024 * 1024) {
        throw ValidationException::withMessages(['file' => "Max file size is {$maxMb}MB."]);
    }

    $disk         = config('media.disk', 'public');
    $pathTemplate = config("media.paths.{$context}", config('media.paths.default', 'users/{user}/uploads'));
    $base         = str_replace('{user}', $user->id, $pathTemplate);
    $dir          = $base . '/' . now()->format('Y/m');
    $mimeMap = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/gif'       => 'gif',
        'image/webp'      => 'webp',
        'image/svg+xml'   => 'svg',
        'video/mp4'       => 'mp4',
        'video/quicktime' => 'mov',
        'video/webm'      => 'webm',
        'audio/mpeg'      => 'mp3',
        'audio/wav'       => 'wav',
        'audio/x-wav'     => 'wav',
        'text/plain'      => 'txt',
        'text/markdown'   => 'md',
        'text/csv'        => 'csv',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.oasis.opendocument.text' => 'odt',
        'application/epub+zip' => 'epub',
    ];
    $storedExt = $isArchive ? $ext : ($mimeMap[$mime] ?? 'bin');
    $name = Str::uuid() . '.' . $storedExt;
    $path = $file->storeAs($dir, $name, $disk);

    $media = Media::create([
        'user_id'   => $user->id,
        'disk'      => $disk,
        'path'      => $path,
        'filename'  => $file->getClientOriginalName(),
        'mime_type' => $mime,
        'size'      => $file->getSize(),
    ]);

    $this->dispatchProcessingJobs($media);

    return $media;
}

private function dispatchProcessingJobs(Media $media): void
{
    if ($media->needsOfficeConversion()) {
        $media->update(['status' => 'pending']);
        \App\Jobs\ConvertOfficeDocumentJob::dispatch($media);
    } elseif ($media->needsComicExtraction()) {
        $media->update(['status' => 'pending']);
        \App\Jobs\ExtractComicArchiveJob::dispatch($media);
    }
}
```

Note: `dispatchProcessingJobs()` references jobs that don't exist yet (Tasks 4 and 5) — this will fail until those tasks land. That's expected for this task; the office/cbz/cbr acceptance tests above don't trigger these mime types except the cbz test, so run only the PDF/generic-zip tests now and defer the cbz test to Task 5.

- [ ] **Step 4: Temporarily skip the cbz test until Task 5's job exists**

In `tests/Feature/MediaUploadTest.php`, add `->skip('needs ExtractComicArchiveJob from Task 5')` to the cbz test:

```php
it('accepts a cbz upload by extension even though the mime is generic zip', function () {
    $file = UploadedFile::fake()->create('issue-1.cbz', 100, 'application/zip');

    $this->actingAs($this->admin)
        ->postJson('/admin/media', ['file' => $file])
        ->assertOk();
})->skip('needs ExtractComicArchiveJob from Task 5');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/MediaUploadTest.php`
Expected: PASS (cbz test shown as skipped)

- [ ] **Step 6: Commit**

```bash
git add app/Services/MediaService.php tests/Feature/MediaUploadTest.php
git commit -m "feat: allow pdf/office/audio/text/epub/comic uploads in MediaService"
```

---

## Task 4: Office conversion — service, job, tests

**Files:**
- Create: `app/Services/OfficeConversionService.php`
- Create: `app/Jobs/ConvertOfficeDocumentJob.php`
- Create: `tests/Feature/OfficeConversionServiceTest.php`
- Modify: `config/media.php`, `.env.example`

- [ ] **Step 1: Add the binary path config**

Append to `config/media.php`, inside the returned array:

```php
    'office_binary' => env('SOFFICE_BINARY', 'soffice'),
    'unrar_binary'  => env('UNRAR_BINARY', 'unrar'),
```

Append to `.env.example` (after the `RAZORPAY_ME_HANDLE=` line):

```
# File viewer conversion binaries — must be installed on the host/container
SOFFICE_BINARY=soffice
UNRAR_BINARY=unrar
```

- [ ] **Step 2: Write the failing test**

```php
<?php

use App\Models\Media;
use App\Models\User;
use App\Services\OfficeConversionService;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    $this->user = User::factory()->create();
});

it('converts an office document and stores the result on the public disk', function () {
    $media = Media::factory()->create([
        'disk' => 'local', 'path' => 'users/1/uploads/report.docx',
        'filename' => 'report.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);
    Storage::disk('local')->put($media->path, 'fake docx bytes');

    $outputDir = storage_path('app/tmp-conversions');
    @mkdir($outputDir, 0755, true);
    $outputPath = $outputDir . '/report.pdf';

    Process::fake(function ($process) use ($outputPath) {
        file_put_contents($outputPath, '%PDF-1.4 fake pdf bytes');
        return Process::result(output: '', errorOutput: '', exitCode: 0);
    });

    (new OfficeConversionService())->convert($media);

    Storage::disk('public')->assertExists("conversions/{$media->id}.pdf");
    expect(file_exists($outputPath))->toBeFalse();
});

it('throws when the office binary reports failure', function () {
    $media = Media::factory()->create([
        'disk' => 'local', 'path' => 'users/1/uploads/report.docx',
        'filename' => 'report.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);
    Storage::disk('local')->put($media->path, 'fake docx bytes');

    Process::fake(fn () => Process::result(output: '', errorOutput: 'soffice: command not found', exitCode: 1));

    (new OfficeConversionService())->convert($media);
})->throws(RuntimeException::class);
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test tests/Feature/OfficeConversionServiceTest.php`
Expected: FAIL — class `App\Services\OfficeConversionService` not found

- [ ] **Step 4: Implement `OfficeConversionService`**

```php
<?php
namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OfficeConversionService
{
    public function convert(Media $media): void
    {
        $binary     = config('media.office_binary', 'soffice');
        $sourcePath = Storage::disk($media->disk)->path($media->path);
        $outputDir  = storage_path('app/tmp-conversions');

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $result = Process::timeout(120)->run([
            $binary, '--headless', '--convert-to', 'pdf', '--outdir', $outputDir, $sourcePath,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException("soffice conversion failed: {$result->errorOutput()}");
        }

        $convertedPath = $outputDir . '/' . pathinfo($sourcePath, PATHINFO_FILENAME) . '.pdf';

        if (! file_exists($convertedPath)) {
            throw new RuntimeException('soffice did not produce an output file.');
        }

        Storage::disk('public')->put("conversions/{$media->id}.pdf", file_get_contents($convertedPath));
        unlink($convertedPath);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/OfficeConversionServiceTest.php`
Expected: PASS (2 tests)

- [ ] **Step 6: Write `ConvertOfficeDocumentJob`**

```php
<?php
namespace App\Jobs;

use App\Models\Media;
use App\Services\OfficeConversionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ConvertOfficeDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Media $media) {}

    public function handle(OfficeConversionService $converter): void
    {
        $this->media->update(['status' => 'processing']);
        $converter->convert($this->media);
        $this->media->update(['status' => 'ready']);
    }

    public function failed(Throwable $e): void
    {
        $this->media->update(['status' => 'failed', 'status_reason' => $e->getMessage()]);
    }
}
```

- [ ] **Step 7: Add a job-dispatch test**

Append to `tests/Feature/OfficeConversionServiceTest.php`:

```php
use App\Jobs\ConvertOfficeDocumentJob;
use Illuminate\Support\Facades\Queue;

it('marks the media ready after the job runs successfully', function () {
    $media = Media::factory()->create([
        'disk' => 'local', 'path' => 'users/1/uploads/report.docx',
        'filename' => 'report.docx', 'status' => 'pending',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);
    Storage::disk('local')->put($media->path, 'fake docx bytes');

    $outputDir = storage_path('app/tmp-conversions');
    @mkdir($outputDir, 0755, true);

    Process::fake(function () use ($outputDir) {
        file_put_contents("{$outputDir}/report.pdf", '%PDF-1.4 fake pdf bytes');
        return Process::result(output: '', errorOutput: '', exitCode: 0);
    });

    (new ConvertOfficeDocumentJob($media))->handle(new \App\Services\OfficeConversionService());

    expect($media->fresh()->status)->toBe('ready');
});

it('marks the media failed when conversion throws', function () {
    $media = Media::factory()->create([
        'disk' => 'local', 'path' => 'users/1/uploads/report.docx',
        'filename' => 'report.docx', 'status' => 'pending',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);
    Storage::disk('local')->put($media->path, 'fake docx bytes');

    Process::fake(fn () => Process::result(output: '', errorOutput: 'not found', exitCode: 1));

    $job = new ConvertOfficeDocumentJob($media);
    try {
        $job->handle(new \App\Services\OfficeConversionService());
    } catch (\RuntimeException $e) {
        $job->failed($e);
    }

    expect($media->fresh()->status)->toBe('failed')
        ->and($media->fresh()->status_reason)->toContain('not found');
});
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `php artisan test tests/Feature/OfficeConversionServiceTest.php`
Expected: PASS (4 tests)

- [ ] **Step 9: Commit**

```bash
git add app/Services/OfficeConversionService.php app/Jobs/ConvertOfficeDocumentJob.php \
        tests/Feature/OfficeConversionServiceTest.php config/media.php .env.example
git commit -m "feat: office-to-PDF conversion service and queued job"
```

---

## Task 5: Comic archive extraction — service, job, tests

**Files:**
- Create: `app/Services/ComicArchiveExtractionService.php`
- Create: `app/Jobs/ExtractComicArchiveJob.php`
- Create: `tests/Feature/ComicArchiveExtractionServiceTest.php`

- [ ] **Step 1: Write the failing test**

This test builds a real CBZ fixture on the fly (a zip with two JPEGs) so extraction is exercised for real, and fakes `Process` only for the CBR/unrar path.

```php
<?php

use App\Jobs\ExtractComicArchiveJob;
use App\Models\Media;
use App\Models\User;
use App\Services\ComicArchiveExtractionService;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/ComicArchiveExtractionServiceTest.php`
Expected: FAIL — `ComicArchiveExtractionService`/`ExtractComicArchiveJob` not found

- [ ] **Step 3: Implement `ComicArchiveExtractionService`**

```php
<?php
namespace App\Services;

use App\Models\Media;
use FilesystemIterator;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

class ComicArchiveExtractionService
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function extract(Media $media): array
    {
        $sourcePath = Storage::disk($media->disk)->path($media->path);
        $tempDir    = storage_path("app/tmp-comics/{$media->id}");

        if (is_dir($tempDir)) {
            $this->deleteDirectory($tempDir);
        }
        mkdir($tempDir, 0755, true);

        if ($media->extension() === 'cbz') {
            $this->extractZip($sourcePath, $tempDir);
        } else {
            $this->extractRar($sourcePath, $tempDir);
        }

        $manifest = [];
        foreach ($this->collectImages($tempDir) as $index => $path) {
            $pageNumber = str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            $ext        = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $filename   = "page-{$pageNumber}.{$ext}";

            Storage::disk('public')->put("comics/{$media->id}/{$filename}", file_get_contents($path));
            $manifest[] = $filename;
        }

        $this->deleteDirectory($tempDir);

        return $manifest;
    }

    private function extractZip(string $sourcePath, string $tempDir): void
    {
        $zip = new ZipArchive();
        if ($zip->open($sourcePath) !== true) {
            throw new RuntimeException('Unable to open CBZ archive.');
        }
        $zip->extractTo($tempDir);
        $zip->close();
    }

    private function extractRar(string $sourcePath, string $tempDir): void
    {
        $binary = config('media.unrar_binary', 'unrar');
        $result = Process::timeout(120)->run([$binary, 'x', '-y', $sourcePath, $tempDir . '/']);

        if (! $result->successful()) {
            throw new RuntimeException("unrar extraction failed: {$result->errorOutput()}");
        }
    }

    private function collectImages(string $dir): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        $paths = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array(strtolower($file->getExtension()), self::IMAGE_EXTENSIONS, true)) {
                $paths[] = $file->getPathname();
            }
        }

        sort($paths, SORT_NATURAL);

        return $paths;
    }

    private function deleteDirectory(string $dir): void
    {
        foreach (glob("{$dir}/*") ?: [] as $file) {
            is_dir($file) ? $this->deleteDirectory($file) : unlink($file);
        }
        rmdir($dir);
    }
}
```

- [ ] **Step 4: Write `ExtractComicArchiveJob`**

```php
<?php
namespace App\Jobs;

use App\Models\Media;
use App\Services\ComicArchiveExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ExtractComicArchiveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Media $media) {}

    public function handle(ComicArchiveExtractionService $extractor): void
    {
        $this->media->update(['status' => 'processing']);
        $manifest = $extractor->extract($this->media);
        $this->media->update(['status' => 'ready', 'page_manifest' => $manifest]);
    }

    public function failed(Throwable $e): void
    {
        $this->media->update(['status' => 'failed', 'status_reason' => $e->getMessage()]);
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/ComicArchiveExtractionServiceTest.php`
Expected: PASS (3 tests)

- [ ] **Step 6: Un-skip the cbz upload test from Task 3**

In `tests/Feature/MediaUploadTest.php`, remove `->skip('needs ExtractComicArchiveJob from Task 5')` from the cbz test, and add `use Illuminate\Support\Facades\Queue;` + `Queue::fake();` in the `beforeEach` block so the real job doesn't run synchronously against a fake zip:

```php
beforeEach(function () {
    Storage::fake('public');
    Queue::fake();
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});
```

- [ ] **Step 7: Run the full media upload suite**

Run: `php artisan test tests/Feature/MediaUploadTest.php tests/Feature/ComicArchiveExtractionServiceTest.php tests/Feature/OfficeConversionServiceTest.php`
Expected: PASS (all tests)

- [ ] **Step 8: Commit**

```bash
git add app/Services/ComicArchiveExtractionService.php app/Jobs/ExtractComicArchiveJob.php \
        tests/Feature/ComicArchiveExtractionServiceTest.php tests/Feature/MediaUploadTest.php
git commit -m "feat: comic archive extraction service and queued job"
```

---

## Task 6: Status polling endpoint

**Files:**
- Modify: `app/Http/Controllers/Admin/MediaController.php`, `routes/web.php`
- Create: `tests/Feature/MediaStatusEndpointTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->viewer = User::factory()->create();
    $this->viewer->assignRole('viewer');
});

it('returns processing status while a conversion is pending', function () {
    $media = Media::factory()->create([
        'filename' => 'report.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'status' => 'processing',
    ]);

    $this->actingAs($this->viewer)
        ->getJson("/admin/media/{$media->id}/status")
        ->assertOk()
        ->assertJsonPath('status', 'processing')
        ->assertJsonPath('converted_pdf_url', null);
});

it('returns the converted pdf url once ready', function () {
    $media = Media::factory()->create([
        'filename' => 'report.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'status' => 'ready',
    ]);

    $this->actingAs($this->viewer)
        ->getJson("/admin/media/{$media->id}/status")
        ->assertOk()
        ->assertJsonPath('converted_pdf_url', fn ($url) => str_contains($url, "conversions/{$media->id}.pdf"));
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/MediaStatusEndpointTest.php`
Expected: FAIL — 404 (route doesn't exist)

- [ ] **Step 3: Add the controller method**

In `app/Http/Controllers/Admin/MediaController.php`, add:

```php
    public function status(int $id): JsonResponse
    {
        $media = Media::findOrFail($id);

        return response()->json([
            'id'                => $media->id,
            'status'            => $media->status,
            'status_reason'     => $media->status_reason,
            'converted_pdf_url' => $media->converted_pdf_url,
            'comic_page_urls'   => $media->comic_page_urls,
        ]);
    }
```

Also add `'status' => $record->status,` to the `store()` method's JSON response array.

- [ ] **Step 4: Add the route**

In `routes/web.php`, add a read-accessible group right after the existing media `admin,editor` group (around line 69):

```php
Route::middleware(['auth', 'role:admin,editor,viewer'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('media/{id}/status', [MediaController::class, 'status'])->name('media.status');
    });
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/MediaStatusEndpointTest.php`
Expected: PASS (2 tests)

- [ ] **Step 6: Run the full backend suite**

Run: `php artisan test`
Expected: PASS — no regressions in unrelated suites

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/MediaController.php routes/web.php tests/Feature/MediaStatusEndpointTest.php
git commit -m "feat: media status polling endpoint"
```

---

## Task 7: Frontend dependencies and test runner

**Files:**
- Modify: `package.json`
- Create: `vitest.config.js`

This repo has no JS test runner yet — Vitest + @vue/test-utils are added here since the design spec calls for component tests.

- [ ] **Step 1: Install dependencies**

Run:
```bash
npm install pdfjs-dist epubjs marked papaparse
npm install --save-dev vitest @vue/test-utils jsdom
```

- [ ] **Step 2: Add the test script**

In `package.json`, add under `"scripts"`:

```json
        "test:unit": "vitest run"
```

- [ ] **Step 3: Write `vitest.config.js`**

```js
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  resolve: { alias: { '@': '/resources/js' } },
  test: {
    environment: 'jsdom',
    include: ['tests/js/**/*.test.js'],
  },
})
```

- [ ] **Step 4: Verify the runner works with a throwaway test**

Create `tests/js/sanity.test.js`:

```js
import { describe, it, expect } from 'vitest'

describe('sanity', () => {
  it('runs', () => {
    expect(1 + 1).toBe(2)
  })
})
```

Run: `npm run test:unit`
Expected: PASS (1 test)

Delete `tests/js/sanity.test.js` once confirmed.

- [ ] **Step 5: Commit**

```bash
git add package.json package-lock.json vitest.config.js
git commit -m "chore: add pdf/epub/markdown/csv deps and Vitest test runner"
```

---

## Task 8: `rendererMap` — extension-to-renderer dispatch logic

**Files:**
- Create: `resources/js/Components/FileViewer/rendererMap.js`
- Test: `tests/js/rendererMap.test.js`

Dispatch is extension-based (not mime-based): `.md` files are frequently reported as `text/plain` by PHP's `finfo`, which would collapse markdown and plain text into the same renderer if dispatch relied on `mime_type`.

- [ ] **Step 1: Write the failing test**

```js
import { describe, it, expect } from 'vitest'
import { resolveRenderer } from '@/Components/FileViewer/rendererMap'

describe('resolveRenderer', () => {
  it('resolves images by extension', () => {
    expect(resolveRenderer({ filename: 'photo.png' })).toBe('image')
    expect(resolveRenderer({ filename: 'photo.svg' })).toBe('image')
  })

  it('resolves audio and video', () => {
    expect(resolveRenderer({ filename: 'song.mp3' })).toBe('audio')
    expect(resolveRenderer({ filename: 'clip.mov' })).toBe('video')
  })

  it('resolves markdown separately from plain text and csv', () => {
    expect(resolveRenderer({ filename: 'notes.md' })).toBe('markdown')
    expect(resolveRenderer({ filename: 'notes.txt' })).toBe('text')
    expect(resolveRenderer({ filename: 'data.csv' })).toBe('csv')
  })

  it('resolves pdf and office documents', () => {
    expect(resolveRenderer({ filename: 'report.pdf' })).toBe('pdf')
    expect(resolveRenderer({ filename: 'report.docx' })).toBe('office')
    expect(resolveRenderer({ filename: 'report.odt' })).toBe('office')
  })

  it('resolves comic archives and epub', () => {
    expect(resolveRenderer({ filename: 'issue-1.cbz' })).toBe('comic')
    expect(resolveRenderer({ filename: 'issue-1.cbr' })).toBe('comic')
    expect(resolveRenderer({ filename: 'book.epub' })).toBe('epub')
  })

  it('falls back to unsupported for unknown extensions', () => {
    expect(resolveRenderer({ filename: 'archive.7z' })).toBe('unsupported')
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test:unit -- rendererMap`
Expected: FAIL — module not found

- [ ] **Step 3: Implement `rendererMap.js`**

```js
const EXTENSION_MAP = {
  png: 'image', jpg: 'image', jpeg: 'image', gif: 'image', svg: 'image',
  mp3: 'audio', wav: 'audio',
  mp4: 'video', mov: 'video',
  pdf: 'pdf',
  txt: 'text',
  md: 'markdown',
  csv: 'csv',
  doc: 'office', docx: 'office', odt: 'office',
  cbz: 'comic', cbr: 'comic',
  epub: 'epub',
}

export function resolveRenderer(media) {
  const parts = (media.filename ?? '').split('.')
  const ext = parts.length > 1 ? parts.pop().toLowerCase() : ''

  return EXTENSION_MAP[ext] ?? 'unsupported'
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test:unit -- rendererMap`
Expected: PASS (6 tests)

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/FileViewer/rendererMap.js tests/js/rendererMap.test.js
git commit -m "feat: extension-based renderer dispatch map"
```

---

## Task 9: `useReaderTheme` composable

**Files:**
- Create: `resources/js/Composables/useReaderTheme.js`
- Test: `tests/js/useReaderTheme.test.js`

- [ ] **Step 1: Write the failing test**

```js
import { describe, it, expect, beforeEach, vi } from 'vitest'

beforeEach(() => {
  localStorage.clear()
  vi.resetModules()
})

describe('useReaderTheme', () => {
  it('defaults to the light theme at 16px', async () => {
    const { useReaderTheme } = await import('@/Composables/useReaderTheme')
    const { preferences } = useReaderTheme()
    expect(preferences.value.theme).toBe('light')
    expect(preferences.value.fontSize).toBe(16)
  })

  it('persists theme changes to localStorage', async () => {
    const { useReaderTheme } = await import('@/Composables/useReaderTheme')
    const { setTheme } = useReaderTheme()
    setTheme('sepia')
    const stored = JSON.parse(localStorage.getItem('file-viewer-reader-theme'))
    expect(stored.theme).toBe('sepia')
  })

  it('clamps font size between 12 and 28', async () => {
    const { useReaderTheme } = await import('@/Composables/useReaderTheme')
    const { preferences, decreaseFontSize, increaseFontSize } = useReaderTheme()
    for (let i = 0; i < 10; i++) decreaseFontSize()
    expect(preferences.value.fontSize).toBe(12)
    for (let i = 0; i < 20; i++) increaseFontSize()
    expect(preferences.value.fontSize).toBe(28)
  })

  it('shares state across multiple calls in the same session', async () => {
    const { useReaderTheme } = await import('@/Composables/useReaderTheme')
    const a = useReaderTheme()
    const b = useReaderTheme()
    a.setTheme('dark')
    expect(b.preferences.value.theme).toBe('dark')
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test:unit -- useReaderTheme`
Expected: FAIL — module not found

- [ ] **Step 3: Implement `useReaderTheme.js`**

```js
import { computed, ref, watch } from 'vue'

const THEME_CLASSES = {
  light: 'bg-white text-slate-900',
  sepia: 'bg-[#f4ecd8] text-[#3b2f1c]',
  dark: 'bg-[#121212] text-[#d8d8d8]',
}

const STORAGE_KEY = 'file-viewer-reader-theme'
const MIN_FONT_SIZE = 12
const MAX_FONT_SIZE = 28

function loadPreferences() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    return raw ? JSON.parse(raw) : { theme: 'light', fontSize: 16, lineHeight: 1.6 }
  } catch {
    return { theme: 'light', fontSize: 16, lineHeight: 1.6 }
  }
}

const preferences = ref(loadPreferences())

watch(preferences, (value) => {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(value))
}, { deep: true })

export function useReaderTheme() {
  const themeClass = computed(() => THEME_CLASSES[preferences.value.theme] ?? THEME_CLASSES.light)
  const fontStyle = computed(() => ({
    fontSize: `${preferences.value.fontSize}px`,
    lineHeight: preferences.value.lineHeight,
  }))

  const setTheme = (theme) => { preferences.value = { ...preferences.value, theme } }
  const increaseFontSize = () => {
    preferences.value = { ...preferences.value, fontSize: Math.min(preferences.value.fontSize + 2, MAX_FONT_SIZE) }
  }
  const decreaseFontSize = () => {
    preferences.value = { ...preferences.value, fontSize: Math.max(preferences.value.fontSize - 2, MIN_FONT_SIZE) }
  }

  return { preferences, themeClass, fontStyle, setTheme, increaseFontSize, decreaseFontSize }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test:unit -- useReaderTheme`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add resources/js/Composables/useReaderTheme.js tests/js/useReaderTheme.test.js
git commit -m "feat: reader theme composable (sepia/dark/light, font size, persisted)"
```

---

## Task 10: Native renderers — Image, Video, Audio

**Files:**
- Create: `resources/js/Components/FileViewer/renderers/ImageRenderer.vue`
- Create: `resources/js/Components/FileViewer/renderers/VideoRenderer.vue`
- Create: `resources/js/Components/FileViewer/renderers/AudioRenderer.vue`

These are thin wrappers around native elements with no branching logic, so they're covered by the `FileViewer` dispatch test in Task 13 rather than individually — there's nothing renderer-specific to unit test beyond "the right tag renders with the right `src`," which that test already checks per-renderer via `findComponent`.

- [ ] **Step 1: Write `ImageRenderer.vue`**

```vue
<template>
  <img :src="media.url" :alt="media.filename" class="w-full h-full object-contain bg-slate-900" />
</template>

<script setup>
defineProps({ media: { type: Object, required: true } })
</script>
```

- [ ] **Step 2: Write `VideoRenderer.vue`**

```vue
<template>
  <video :src="media.url" controls class="w-full h-full bg-black" />
</template>

<script setup>
defineProps({ media: { type: Object, required: true } })
</script>
```

- [ ] **Step 3: Write `AudioRenderer.vue`**

```vue
<template>
  <div class="w-full h-full min-h-[100px] flex items-center justify-center bg-slate-100 p-4">
    <audio :src="media.url" controls class="w-full" />
  </div>
</template>

<script setup>
defineProps({ media: { type: Object, required: true } })
</script>
```

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/FileViewer/renderers/ImageRenderer.vue \
        resources/js/Components/FileViewer/renderers/VideoRenderer.vue \
        resources/js/Components/FileViewer/renderers/AudioRenderer.vue
git commit -m "feat: native image/video/audio renderers"
```

---

## Task 11: `TextRenderer` — txt/md/csv with reader theming

**Files:**
- Create: `resources/js/Components/FileViewer/renderers/TextRenderer.vue`
- Test: `tests/js/TextRenderer.test.js`

- [ ] **Step 1: Write the failing test**

```js
import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import TextRenderer from '@/Components/FileViewer/renderers/TextRenderer.vue'

global.fetch = vi.fn()

function mockFetchText(text) {
  fetch.mockResolvedValueOnce({ text: () => Promise.resolve(text) })
}

describe('TextRenderer', () => {
  it('renders plain text in a <pre> block', async () => {
    mockFetchText('hello world')
    const wrapper = mount(TextRenderer, {
      props: { media: { filename: 'notes.txt', url: '/notes.txt' } },
    })
    await flushPromises()
    expect(wrapper.find('pre').text()).toBe('hello world')
  })

  it('renders markdown as HTML', async () => {
    mockFetchText('# Title')
    const wrapper = mount(TextRenderer, {
      props: { media: { filename: 'notes.md', url: '/notes.md' } },
    })
    await flushPromises()
    expect(wrapper.find('h1').text()).toBe('Title')
  })

  it('renders csv as a table', async () => {
    mockFetchText('name,age\nAda,30\nGrace,45')
    const wrapper = mount(TextRenderer, {
      props: { media: { filename: 'data.csv', url: '/data.csv' } },
    })
    await flushPromises()
    expect(wrapper.findAll('th').map((th) => th.text())).toEqual(['name', 'age'])
    expect(wrapper.findAll('tbody tr')).toHaveLength(2)
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test:unit -- TextRenderer`
Expected: FAIL — component not found

- [ ] **Step 3: Implement `TextRenderer.vue`**

```vue
<template>
  <div class="w-full h-full min-h-[220px] overflow-auto p-4 text-sm" :class="themeClass" :style="fontStyle">
    <pre v-if="variant === 'text'" class="whitespace-pre-wrap font-mono">{{ content }}</pre>
    <div v-else-if="variant === 'markdown'" v-html="renderedMarkdown"></div>
    <table v-else-if="variant === 'csv'" class="min-w-full border-collapse">
      <thead>
        <tr>
          <th v-for="(col, i) in headerRow" :key="i" class="border border-slate-200 px-2 py-1 text-left font-medium">
            {{ col }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(row, r) in bodyRows" :key="r">
          <td v-for="(cell, c) in row" :key="c" class="border border-slate-200 px-2 py-1">{{ cell }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { marked } from 'marked'
import Papa from 'papaparse'
import { resolveRenderer } from '../rendererMap'
import { useReaderTheme } from '@/Composables/useReaderTheme'

const props = defineProps({ media: { type: Object, required: true } })

const variant = computed(() => resolveRenderer(props.media))
const content = ref('')
const csvRows = ref([])
const { themeClass, fontStyle } = useReaderTheme()

onMounted(async () => {
  const response = await fetch(props.media.url)
  const text = await response.text()

  if (variant.value === 'csv') {
    csvRows.value = Papa.parse(text.trim()).data
  } else {
    content.value = text
  }
})

const renderedMarkdown = computed(() => marked.parse(content.value))
const headerRow = computed(() => csvRows.value[0] ?? [])
const bodyRows = computed(() => csvRows.value.slice(1))
</script>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test:unit -- TextRenderer`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/FileViewer/renderers/TextRenderer.vue tests/js/TextRenderer.test.js
git commit -m "feat: text renderer for txt/markdown/csv with reader theming"
```

---

## Task 12: `PdfRenderer` and `OfficeRenderer`

**Files:**
- Create: `resources/js/Components/FileViewer/renderers/PdfRenderer.vue`
- Create: `resources/js/Components/FileViewer/renderers/OfficeRenderer.vue`

pdf.js renders to a `<canvas>` via a worker and isn't meaningfully unit-testable without a full browser canvas/worker environment — this is verified manually in the browser during Task 16's end-to-end check instead of a jsdom test.

- [ ] **Step 1: Write `PdfRenderer.vue`**

```vue
<template>
  <div class="relative w-full h-full min-h-[220px] bg-slate-900 flex flex-col items-center overflow-auto">
    <canvas ref="canvas"></canvas>
    <div v-if="loading" class="absolute inset-0 flex items-center justify-center text-slate-300 text-sm">
      Loading…
    </div>
    <div v-if="numPages > 1" class="sticky bottom-0 flex items-center justify-center gap-4 py-1 text-white text-sm bg-black/40 w-full">
      <button :disabled="page <= 1" @click="goTo(page - 1)">‹</button>
      <span>{{ page }} / {{ numPages }}</span>
      <button :disabled="page >= numPages" @click="goTo(page + 1)">›</button>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import * as pdfjsLib from 'pdfjs-dist'
import pdfWorker from 'pdfjs-dist/build/pdf.worker.min.mjs?url'

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorker

const props = defineProps({ media: { type: Object, required: true } })

const canvas = ref(null)
const loading = ref(true)
const page = ref(1)
const numPages = ref(0)
let pdfDoc = null

const renderPage = async (num) => {
  const pdfPage = await pdfDoc.getPage(num)
  const viewport = pdfPage.getViewport({ scale: 1.2 })
  canvas.value.width = viewport.width
  canvas.value.height = viewport.height
  await pdfPage.render({ canvasContext: canvas.value.getContext('2d'), viewport }).promise
}

const goTo = async (num) => {
  page.value = num
  await renderPage(num)
}

onMounted(async () => {
  pdfDoc = await pdfjsLib.getDocument(props.media.url).promise
  numPages.value = pdfDoc.numPages
  await renderPage(page.value)
  loading.value = false
})
</script>
```

- [ ] **Step 2: Write `OfficeRenderer.vue`**

```vue
<template>
  <PdfRenderer :media="pdfMedia" />
</template>

<script setup>
import { computed } from 'vue'
import PdfRenderer from './PdfRenderer.vue'

const props = defineProps({ media: { type: Object, required: true } })

const pdfMedia = computed(() => ({ ...props.media, url: props.media.converted_pdf_url }))
</script>
```

- [ ] **Step 3: Verify the build picks up the pdf.js worker asset**

Run: `npm run build`
Expected: build succeeds, output includes a `pdf.worker` chunk

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/FileViewer/renderers/PdfRenderer.vue \
        resources/js/Components/FileViewer/renderers/OfficeRenderer.vue
git commit -m "feat: pdf.js renderer and office-document wrapper"
```

---

## Task 13: `ComicRenderer` and `EpubRenderer`

**Files:**
- Create: `resources/js/Components/FileViewer/renderers/ComicRenderer.vue`
- Create: `resources/js/Components/FileViewer/renderers/EpubRenderer.vue`
- Test: `tests/js/ComicRenderer.test.js`

- [ ] **Step 1: Write the failing test for `ComicRenderer`**

```js
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import ComicRenderer from '@/Components/FileViewer/renderers/ComicRenderer.vue'

describe('ComicRenderer', () => {
  it('shows the first page and advances on next', async () => {
    const media = { comic_page_urls: ['/p1.jpg', '/p2.jpg', '/p3.jpg'] }
    const wrapper = mount(ComicRenderer, { props: { media } })

    expect(wrapper.find('img').attributes('src')).toBe('/p1.jpg')

    await wrapper.find('[data-testid="next-page"]').trigger('click')
    expect(wrapper.find('img').attributes('src')).toBe('/p2.jpg')
  })

  it('disables next on the last page and prev on the first', async () => {
    const media = { comic_page_urls: ['/p1.jpg', '/p2.jpg'] }
    const wrapper = mount(ComicRenderer, { props: { media } })

    expect(wrapper.find('[data-testid="prev-page"]').exists()).toBe(false)

    await wrapper.find('[data-testid="next-page"]').trigger('click')
    expect(wrapper.find('[data-testid="next-page"]').exists()).toBe(false)
  })

  it('shows a message when there are no pages', () => {
    const wrapper = mount(ComicRenderer, { props: { media: { comic_page_urls: [] } } })
    expect(wrapper.text()).toContain('No pages')
  })

  it('shows an always-visible page position indicator', () => {
    const media = { comic_page_urls: ['/p1.jpg', '/p2.jpg', '/p3.jpg'] }
    const wrapper = mount(ComicRenderer, { props: { media } })
    expect(wrapper.find('[data-testid="page-indicator"]').text()).toBe('1 / 3')
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test:unit -- ComicRenderer`
Expected: FAIL — component not found

- [ ] **Step 3: Implement `ComicRenderer.vue`**

```vue
<template>
  <div class="relative w-full h-full min-h-[220px] bg-slate-900 flex items-center justify-center overflow-hidden">
    <img v-if="pages.length" :src="pages[page - 1]" class="max-h-full max-w-full object-contain" />
    <p v-else class="text-slate-400 text-sm">No pages found for this archive.</p>

    <button
      v-if="page > 1"
      data-testid="prev-page"
      class="absolute left-2 top-1/2 -translate-y-1/2 text-white text-2xl"
      @click="page--"
    >‹</button>
    <button
      v-if="page < pages.length"
      data-testid="next-page"
      class="absolute right-2 top-1/2 -translate-y-1/2 text-white text-2xl"
      @click="page++"
    >›</button>

    <div
      v-if="pages.length"
      data-testid="page-indicator"
      class="absolute bottom-2 left-1/2 -translate-x-1/2 text-white text-xs bg-black/40 rounded px-2 py-0.5"
    >{{ page }} / {{ pages.length }}</div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({ media: { type: Object, required: true } })
const pages = computed(() => props.media.comic_page_urls ?? [])
const page = ref(1)
</script>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test:unit -- ComicRenderer`
Expected: PASS (4 tests)

- [ ] **Step 5: Write `EpubRenderer.vue`**

epub.js renders into an iframe it manages internally, so like `PdfRenderer` this is verified manually rather than in jsdom. Unlike `PdfRenderer`/`ComicRenderer`, this doesn't add a page-position indicator: epub.js locations (needed for an accurate "page X of Y") are generated asynchronously over the whole book and are non-trivial to wire up — same category of known gap as the MOV codec caveat in the spec. Out of scope for this plan; flag it if the e-library spec later needs reading-progress tracking.

```vue
<template>
  <div ref="container" class="w-full h-full min-h-[320px] bg-white"></div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import ePub from 'epubjs'

const props = defineProps({ media: { type: Object, required: true } })
const container = ref(null)

onMounted(() => {
  const book = ePub(props.media.url)
  const rendition = book.renderTo(container.value, { width: '100%', height: '100%' })
  rendition.display()
})
</script>
```

- [ ] **Step 6: Commit**

```bash
git add resources/js/Components/FileViewer/renderers/ComicRenderer.vue \
        resources/js/Components/FileViewer/renderers/EpubRenderer.vue \
        tests/js/ComicRenderer.test.js
git commit -m "feat: comic pager and epub.js renderers"
```

---

## Task 14: `ProcessingState` and `UnsupportedRenderer`

**Files:**
- Create: `resources/js/Components/FileViewer/ProcessingState.vue`
- Create: `resources/js/Components/FileViewer/UnsupportedRenderer.vue`

- [ ] **Step 1: Write `ProcessingState.vue`**

```vue
<template>
  <div class="w-full h-full min-h-[220px] flex items-center justify-center bg-slate-100 text-slate-500 text-sm">
    Processing…
  </div>
</template>
```

- [ ] **Step 2: Write `UnsupportedRenderer.vue`**

```vue
<template>
  <div class="w-full h-full min-h-[220px] flex flex-col items-center justify-center gap-2 bg-slate-100 text-slate-500 text-sm p-4 text-center">
    <p>Preview not available{{ reason ? ` — ${reason}` : '.' }}</p>
    <a v-if="downloadUrl" :href="downloadUrl" class="text-blue-600 hover:underline" download>Download to view</a>
  </div>
</template>

<script setup>
defineProps({ reason: { type: String, default: null }, downloadUrl: { type: String, default: null } })
</script>
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/Components/FileViewer/ProcessingState.vue resources/js/Components/FileViewer/UnsupportedRenderer.vue
git commit -m "feat: processing and unsupported-format fallback states"
```

---

## Task 15: `FileViewer` dispatcher — embedded/fullscreen, hover controls

**Files:**
- Create: `resources/js/Components/FileViewer/FileViewer.vue`
- Test: `tests/js/FileViewer.test.js`

- [ ] **Step 1: Write the failing test**

```js
import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import FileViewer from '@/Components/FileViewer/FileViewer.vue'
import ImageRenderer from '@/Components/FileViewer/renderers/ImageRenderer.vue'
import ComicRenderer from '@/Components/FileViewer/renderers/ComicRenderer.vue'
import ProcessingState from '@/Components/FileViewer/ProcessingState.vue'
import UnsupportedRenderer from '@/Components/FileViewer/UnsupportedRenderer.vue'

const readyImage = { id: 1, filename: 'photo.png', url: '/photo.png', status: 'ready' }

describe('FileViewer', () => {
  it('dispatches to ImageRenderer for image files', () => {
    const wrapper = mount(FileViewer, { props: { media: readyImage } })
    expect(wrapper.findComponent(ImageRenderer).exists()).toBe(true)
  })

  it('dispatches to ComicRenderer for cbz files', () => {
    const media = { id: 2, filename: 'issue-1.cbz', status: 'ready', comic_page_urls: ['/p1.jpg'] }
    const wrapper = mount(FileViewer, { props: { media } })
    expect(wrapper.findComponent(ComicRenderer).exists()).toBe(true)
  })

  it('shows ProcessingState while status is pending or processing', () => {
    const wrapper = mount(FileViewer, { props: { media: { ...readyImage, status: 'pending' } } })
    expect(wrapper.findComponent(ProcessingState).exists()).toBe(true)
  })

  it('shows UnsupportedRenderer for unknown extensions', () => {
    const wrapper = mount(FileViewer, { props: { media: { id: 3, filename: 'archive.7z', status: 'ready' } } })
    expect(wrapper.findComponent(UnsupportedRenderer).exists()).toBe(true)
  })

  it('shows UnsupportedRenderer with the failure reason when status is failed', () => {
    const media = { ...readyImage, status: 'failed', status_reason: 'unrar not found' }
    const wrapper = mount(FileViewer, { props: { media } })
    expect(wrapper.findComponent(UnsupportedRenderer).props('reason')).toBe('unrar not found')
  })

  it('toggles fullscreen state on the expand button', async () => {
    const wrapper = mount(FileViewer, { props: { media: readyImage } })
    expect(wrapper.classes()).not.toContain('fixed')

    await wrapper.find('[data-testid="toggle-fullscreen"]').trigger('click')
    expect(wrapper.classes()).toContain('fixed')
  })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `npm run test:unit -- FileViewer`
Expected: FAIL — component not found

- [ ] **Step 3: Implement `FileViewer.vue`**

```vue
<template>
  <div
    class="relative rounded-lg border border-slate-200 overflow-hidden"
    :class="fullscreen ? 'fixed inset-0 z-50 rounded-none border-0' : 'w-full h-full min-h-[220px]'"
    @mouseenter="showControls = true"
    @mouseleave="showControls = false"
  >
    <ProcessingState v-if="media.status === 'pending' || media.status === 'processing'" />
    <UnsupportedRenderer
      v-else-if="media.status === 'failed'"
      :reason="media.status_reason"
      :download-url="media.url"
    />
    <UnsupportedRenderer v-else-if="renderer === 'unsupported'" :download-url="media.url" />
    <component :is="rendererComponent" v-else :media="media" />

    <div
      class="absolute inset-x-0 top-0 flex items-center justify-between px-3 py-1.5 text-xs text-white transition-opacity duration-150 pointer-events-none"
      :class="showControls ? 'opacity-100' : 'opacity-0'"
      style="background: linear-gradient(to bottom, rgba(0,0,0,.55), transparent)"
    >
      <span class="truncate">{{ media.filename }}</span>
      <button
        data-testid="toggle-fullscreen"
        class="pointer-events-auto"
        @click="fullscreen = !fullscreen"
      >{{ fullscreen ? '✕' : '⛶' }}</button>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { resolveRenderer } from './rendererMap'
import ImageRenderer from './renderers/ImageRenderer.vue'
import VideoRenderer from './renderers/VideoRenderer.vue'
import AudioRenderer from './renderers/AudioRenderer.vue'
import PdfRenderer from './renderers/PdfRenderer.vue'
import TextRenderer from './renderers/TextRenderer.vue'
import OfficeRenderer from './renderers/OfficeRenderer.vue'
import ComicRenderer from './renderers/ComicRenderer.vue'
import EpubRenderer from './renderers/EpubRenderer.vue'
import ProcessingState from './ProcessingState.vue'
import UnsupportedRenderer from './UnsupportedRenderer.vue'

const props = defineProps({ media: { type: Object, required: true } })

const fullscreen = ref(false)
const showControls = ref(false)

const renderer = computed(() => resolveRenderer(props.media))

const COMPONENT_MAP = {
  image: ImageRenderer,
  video: VideoRenderer,
  audio: AudioRenderer,
  pdf: PdfRenderer,
  text: TextRenderer,
  markdown: TextRenderer,
  csv: TextRenderer,
  office: OfficeRenderer,
  comic: ComicRenderer,
  epub: EpubRenderer,
}

const rendererComponent = computed(() => COMPONENT_MAP[renderer.value])
</script>
```

- [ ] **Step 4: Run test to verify it passes**

Run: `npm run test:unit -- FileViewer`
Expected: PASS (6 tests)

- [ ] **Step 5: Run the full JS suite**

Run: `npm run test:unit`
Expected: PASS — all test files green

- [ ] **Step 6: Commit**

```bash
git add resources/js/Components/FileViewer/FileViewer.vue tests/js/FileViewer.test.js
git commit -m "feat: FileViewer dispatcher with hover controls and fullscreen toggle"
```

---

## Task 16: Manual verification in the browser

**Files:** none (verification only)

- [ ] **Step 1: Build assets and start the app**

Run: `npm run build && php artisan serve`

- [ ] **Step 2: Add a temporary preview route**

Since `FileViewer` isn't wired into task/comment attachment UI yet (out of scope — see spec), add a throwaway Inertia page to exercise it directly. Create `resources/js/Pages/Dev/FileViewerPreview.vue`:

```vue
<template>
  <div class="p-8 max-w-2xl mx-auto">
    <h1 class="text-lg font-semibold mb-4">FileViewer preview</h1>
    <FileViewer v-if="media" :media="media" />
    <input type="file" class="mt-4" @change="onUpload" />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'
import FileViewer from '@/Components/FileViewer/FileViewer.vue'

const media = ref(null)

const onUpload = async (e) => {
  const fd = new FormData()
  fd.append('file', e.target.files[0])
  const { data } = await axios.post('/admin/media', fd)
  media.value = data
}
</script>
```

Add a temporary route in `routes/web.php` (inside the `admin,editor,viewer` group used for the status route):

```php
        Route::get('dev/file-viewer-preview', fn () => Inertia::render('Dev/FileViewerPreview'))->name('dev.file-viewer-preview');
```

- [ ] **Step 3: Exercise each format**

Visit `/admin/dev/file-viewer-preview` and upload one file per format (png, mp3, mp4, pdf, txt, md, csv, docx, cbz, cbr, epub). For docx/cbz/cbr, confirm the `ProcessingState` shows first, then the real renderer appears once the queue worker finishes (run `php artisan queue:work` in another terminal since `QUEUE_CONNECTION=database`, not `sync`).

Confirm: hover reveals the toolbar, the fullscreen toggle works, reader themes apply on the md/txt/csv files (temporarily add theme buttons to `FileViewerPreview.vue` calling `useReaderTheme().setTheme(...)` if needed to check visually), and a `.7z` upload shows "preview not available" (note: `.7z` isn't in `MediaService::ALLOWED_MIMES`, so this will actually be rejected at upload — instead verify the unsupported state by manually setting `media.value.filename = 'test.7z'` in the browser console against an already-uploaded record).

- [ ] **Step 4: Remove the temporary preview page and route**

```bash
git rm resources/js/Pages/Dev/FileViewerPreview.vue
```

Revert the temporary route line in `routes/web.php`.

- [ ] **Step 5: Commit the revert**

```bash
git add routes/web.php
git commit -m "chore: remove temporary FileViewer preview route"
```

---

## Task 17: Documentation and final verification

**Files:**
- Modify: `docs/INSTALLATION.md`, `docs/CONFIGURATION.md`

- [ ] **Step 1: Document the new server prerequisites**

In `docs/INSTALLATION.md`, add to the prerequisites list (git and Docker sections):

```
- LibreOffice (`soffice` binary) — required for Doc/Docx/ODT preview conversion
- `unrar` or `unar` — required for CBR comic archive preview extraction
```

In `docs/CONFIGURATION.md`, document the two new env vars under a "File Viewer" heading:

```markdown
## File Viewer

| Variable | Default | Purpose |
|---|---|---|
| `SOFFICE_BINARY` | `soffice` | Path to the LibreOffice headless binary, used to convert Doc/Docx/ODT attachments to PDF for preview. |
| `UNRAR_BINARY` | `unrar` | Path to the unrar CLI, used to extract CBR comic archives into page images for preview. |
```

- [ ] **Step 2: Run the full backend and frontend suites**

Run: `php artisan test && npm run test:unit && npm run build`
Expected: all green, build succeeds

- [ ] **Step 3: Commit**

```bash
git add docs/INSTALLATION.md docs/CONFIGURATION.md
git commit -m "docs: document soffice/unrar prerequisites for the file viewer"
```

---

## Out of scope (tracked in the spec, not this plan)

- Wiring `<FileViewer>` into task/comment attachment upload and list UI
- E-library domain (book model, reading progress, library browsing)
- RAG over the e-library via a Laravel MCP server
- EdTech course generator from a compressed structured folder
