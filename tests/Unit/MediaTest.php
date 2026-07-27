<?php

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

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
