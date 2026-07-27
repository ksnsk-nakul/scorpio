<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);
beforeEach(fn () => $this->seed());

beforeEach(function () {
    Storage::fake('public');
    Queue::fake();
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('uploads an image and returns a media record', function () {
    $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

    $response = $this->actingAs($this->admin)
        ->postJson('/admin/media', ['file' => $file]);

    $response->assertOk()->assertJsonStructure(['id','filename','mime_type','url']);
    Storage::disk('public')->assertExists($response->json('path'));
});

it('rejects files over max size', function () {
    // 60 MB exceeds the 50 MB limit
    $file = UploadedFile::fake()->create('big.mp4', 60 * 1024, 'video/mp4');

    $this->actingAs($this->admin)
        ->postJson('/admin/media', ['file' => $file])
        ->assertStatus(422);
});

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
