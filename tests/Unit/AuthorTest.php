<?php

use App\Models\Author;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates a unique slug from the name on creation', function () {
    $a = Author::create(['name' => 'Ursula K. Le Guin']);
    expect($a->slug)->toBe('ursula-k-le-guin');
});

it('appends a numeric suffix when the slug collides', function () {
    Author::create(['name' => 'Frank Herbert']);
    $second = Author::create(['name' => 'Frank Herbert']);
    expect($second->slug)->toBe('frank-herbert-2');
});

it('finds an existing author by case-insensitive exact name match', function () {
    $original = Author::create(['name' => 'Isaac Asimov']);
    $found = Author::findOrCreateByName('isaac asimov');
    expect($found->id)->toBe($original->id);
    expect(Author::count())->toBe(1);
});

it('creates a new author when no case-insensitive match exists', function () {
    Author::findOrCreateByName('Octavia E. Butler');
    expect(Author::count())->toBe(1);
    expect(Author::first()->name)->toBe('Octavia E. Butler');
});
