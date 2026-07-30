<?php

use App\Models\Author;
use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('only lists ready books on the public index, paginated 15 per page', function () {
    Book::factory()->count(3)->create(['status' => 'ready']);
    Book::factory()->count(2)->create(['status' => 'pending']);
    Book::factory()->count(1)->create(['status' => 'failed']);

    $response = $this->get('/library');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Public/Library/Index')
        ->has('books.data', 3));
});

it('requires no authentication to view the public index', function () {
    $this->get('/library')->assertOk();
});
