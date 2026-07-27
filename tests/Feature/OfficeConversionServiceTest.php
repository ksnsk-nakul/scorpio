<?php

use App\Models\Media;
use App\Models\User;
use App\Services\OfficeConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

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
