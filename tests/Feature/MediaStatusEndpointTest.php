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
        'user_id' => $this->viewer->id,
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
        'user_id' => $this->viewer->id,
        'filename' => 'report.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'status' => 'ready',
    ]);

    $this->actingAs($this->viewer)
        ->getJson("/admin/media/{$media->id}/status")
        ->assertOk()
        ->assertJsonPath('converted_pdf_url', fn ($url) => str_contains($url, "conversions/{$media->id}.pdf"));
});

it('forbids a viewer who does not own the media from polling its status', function () {
    $owner = User::factory()->create();
    $owner->assignRole('viewer');

    $media = Media::factory()->create([
        'user_id' => $owner->id,
        'filename' => 'private.docx',
        'status' => 'processing',
        'status_reason' => 'internal libreoffice stderr output',
    ]);

    $this->actingAs($this->viewer)
        ->getJson("/admin/media/{$media->id}/status")
        ->assertForbidden();
});

it('allows the owning viewer to poll their own media status', function () {
    $media = Media::factory()->create([
        'user_id' => $this->viewer->id,
        'filename' => 'mine.docx',
        'status' => 'ready',
    ]);

    $this->actingAs($this->viewer)
        ->getJson("/admin/media/{$media->id}/status")
        ->assertOk()
        ->assertJsonPath('id', $media->id);
});

it('allows an admin to poll status for media they do not own', function () {
    $owner = User::factory()->create();
    $owner->assignRole('viewer');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $media = Media::factory()->create([
        'user_id' => $owner->id,
        'filename' => 'someone-elses.docx',
        'status' => 'ready',
    ]);

    $this->actingAs($admin)
        ->getJson("/admin/media/{$media->id}/status")
        ->assertOk()
        ->assertJsonPath('id', $media->id);
});
