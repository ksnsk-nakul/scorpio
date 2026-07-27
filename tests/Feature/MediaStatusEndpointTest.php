<?php

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);
beforeEach(fn () => $this->seed());

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
