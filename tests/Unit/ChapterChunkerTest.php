<?php

use App\Services\ChapterChunker;

it('extracts plain-text paragraphs from chapter HTML', function () {
    $html = '<h1>Title</h1><p>First paragraph.</p><p>Second paragraph.</p>';
    $chunks = (new ChapterChunker())->chunk($html);

    expect($chunks)->toHaveCount(1);
    expect($chunks[0])->toContain('First paragraph.')
        ->toContain('Second paragraph.');
});

it('strips images and other non-text tags', function () {
    $html = '<p>Before image.</p><img src="pic.jpg" alt="a picture"><p>After image.</p>';
    $chunks = (new ChapterChunker())->chunk($html);

    expect($chunks[0])->toContain('Before image.')
        ->toContain('After image.')
        ->not->toContain('<img');
});

it('splits into multiple chunks at paragraph boundaries once the size cap is exceeded', function () {
    $paragraph = '<p>' . str_repeat('word ', 200) . '</p>'; // ~1000 chars per paragraph
    $html = str_repeat($paragraph, 5); // ~5000 chars total, cap is 3000

    $chunks = (new ChapterChunker())->chunk($html);

    expect(count($chunks))->toBeGreaterThan(1);
    foreach ($chunks as $chunk) {
        expect(mb_strlen($chunk))->toBeLessThanOrEqual(3000 + 1000); // cap + one paragraph's worth of slack
    }
});

it('never splits a single paragraph in the middle', function () {
    $longWord = str_repeat('a', 50);
    $html = '<p>' . implode(' ', array_fill(0, 10, $longWord)) . '</p>';
    $chunks = (new ChapterChunker())->chunk($html);

    // the whole paragraph must appear intact in exactly one chunk
    $matches = array_filter($chunks, fn ($c) => str_contains($c, $longWord . ' ' . $longWord));
    expect($matches)->not->toBeEmpty();
});

it('returns an empty array for empty content', function () {
    expect((new ChapterChunker())->chunk(''))->toBe([]);
    expect((new ChapterChunker())->chunk('<p></p>'))->toBe([]);
});

it('does not duplicate text when block tags are nested', function () {
    $blockquote = (new ChapterChunker())->chunk('<blockquote><p>Quoted text here.</p></blockquote>');
    expect(substr_count($blockquote[0], 'Quoted text here.'))->toBe(1);

    $listItem = (new ChapterChunker())->chunk('<li><p>List item text.</p></li>');
    expect(substr_count($listItem[0], 'List item text.'))->toBe(1);
});
