<?php

use App\Services\SeriesNameExtractor;

beforeEach(function () {
    $this->extractor = new SeriesNameExtractor();
});

it('extracts series and volume from a "Volume NN" title with trailing bracket tags', function () {
    $result = $this->extractor->extract('Overlord - Volume 01 [Yen Press][Kobo]');

    expect($result)->toBe(['series_name' => 'Overlord', 'volume_number' => 1]);
});

it('extracts series and volume from "Vol. N"', function () {
    $result = $this->extractor->extract('Overlord - Vol. 1');

    expect($result)->toBe(['series_name' => 'Overlord', 'volume_number' => 1]);
});

it('extracts series and volume from "Vol N"', function () {
    $result = $this->extractor->extract('Overlord - Vol 1');

    expect($result)->toBe(['series_name' => 'Overlord', 'volume_number' => 1]);
});

it('extracts series and volume from "Vol.N" with no space', function () {
    $result = $this->extractor->extract('Overlord - Vol.1');

    expect($result)->toBe(['series_name' => 'Overlord', 'volume_number' => 1]);
});

it('extracts series and volume from "Book N"', function () {
    $result = $this->extractor->extract('Overlord - Book 1');

    expect($result)->toBe(['series_name' => 'Overlord', 'volume_number' => 1]);
});

it('extracts series and volume from "vN"', function () {
    $result = $this->extractor->extract('Overlord - v1');

    expect($result)->toBe(['series_name' => 'Overlord', 'volume_number' => 1]);
});

it('extracts series and volume from "v0N" (leading zero)', function () {
    $result = $this->extractor->extract('Overlord - v01');

    expect($result)->toBe(['series_name' => 'Overlord', 'volume_number' => 1]);
});

it('extracts series and volume from "#N"', function () {
    $result = $this->extractor->extract('Overlord #1');

    expect($result)->toBe(['series_name' => 'Overlord', 'volume_number' => 1]);
});

it('extracts series and volume from a bare "- NN" suffix (up to 3 digits)', function () {
    $result = $this->extractor->extract('Overlord - 01');

    expect($result)->toBe(['series_name' => 'Overlord', 'volume_number' => 1]);
});

it('does not treat a 4-digit trailing number (e.g. a year) as a volume', function () {
    $result = $this->extractor->extract('Overlord - 2024');

    expect($result)->toBe(['series_name' => null, 'volume_number' => null]);
});

it('returns nulls for a plain standalone title with no series/volume marker', function () {
    $result = $this->extractor->extract('The Lonely Standalone Novel');

    expect($result)->toBe(['series_name' => null, 'volume_number' => null]);
});

it('normalizes titles for duplicate comparison', function () {
    $a = $this->extractor->normalize('  The Lonely Standalone Novel  [Yen Press]');
    $b = $this->extractor->normalize('the lonely   standalone novel');

    expect($a)->toBe($b);
});
