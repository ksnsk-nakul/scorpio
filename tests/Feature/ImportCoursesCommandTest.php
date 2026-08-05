<?php

use App\Models\Course;
use App\Services\CourseImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CourseFixtureBuilder;

uses(RefreshDatabase::class);
beforeEach(fn () => Queue::fake());

it('imports every course listed as content-complete in COURSE-INDEX.md', function () {
    $tmp = sys_get_temp_dir().'/edtech-cmd-test-'.uniqid();
    mkdir($tmp, 0777, true);

    CourseFixtureBuilder::build($tmp, 'C001-HTML-101', 'Introduction to HTML', 'HTML desc', [
        ['title' => 'Fundamentals', 'topics' => [['title' => 'Intro']]],
    ]);
    CourseFixtureBuilder::build($tmp, 'C002-CSS-101', 'Introduction to CSS', 'CSS desc', [
        ['title' => 'Fundamentals', 'topics' => [['title' => 'Selectors']]],
    ]);
    CourseFixtureBuilder::buildIndex($tmp, [
        ['code' => 'C001-HTML-101', 'title' => 'Introduction to HTML', 'slug' => 'C001-HTML-101-introduction-to-html'],
        ['code' => 'C002-CSS-101', 'title' => 'Introduction to CSS', 'slug' => 'C002-CSS-101-introduction-to-css'],
    ]);

    $this->artisan('edtech:import-courses', ['path' => $tmp])->assertSuccessful();

    expect(Course::count())->toBe(2)
        ->and(Course::where('code', 'C001-HTML-101')->first()->status)->toBe('ready');
});

it('skips courses whose COURSE-INDEX.md status is not content-complete', function () {
    $tmp = sys_get_temp_dir().'/edtech-cmd-test-'.uniqid();
    mkdir($tmp, 0777, true);

    CourseFixtureBuilder::buildIndex($tmp, [
        ['code' => 'C099-PLANNED-201', 'title' => 'Not Built Yet', 'slug' => 'not-built'],
    ]);
    // Overwrite the fixture's status column to something not "content complete":
    file_put_contents("{$tmp}/COURSE-INDEX.md", str_replace(
        'Generated, content complete', 'Planned', file_get_contents("{$tmp}/COURSE-INDEX.md")
    ));

    $this->artisan('edtech:import-courses', ['path' => $tmp])->assertSuccessful();

    expect(Course::count())->toBe(0);
});

it('isolates a total per-course import failure at the command loop level and keeps importing the rest', function () {
    $tmp = sys_get_temp_dir().'/edtech-cmd-test-'.uniqid();
    mkdir($tmp, 0777, true);

    CourseFixtureBuilder::build($tmp, 'C001-HTML-101', 'Introduction to HTML', 'HTML desc', [
        ['title' => 'Fundamentals', 'topics' => [['title' => 'Intro']]],
    ]);
    CourseFixtureBuilder::build($tmp, 'C002-CSS-101', 'Introduction to CSS', 'CSS desc', [
        ['title' => 'Fundamentals', 'topics' => [['title' => 'Selectors']]],
    ]);
    CourseFixtureBuilder::buildIndex($tmp, [
        ['code' => 'C001-HTML-101', 'title' => 'Introduction to HTML', 'slug' => 'C001-HTML-101-introduction-to-html'],
        ['code' => 'C002-CSS-101', 'title' => 'Introduction to CSS', 'slug' => 'C002-CSS-101-introduction-to-css'],
    ]);

    // Simulate CourseImportService::importCourse() throwing before any Course
    // row could be created for C001, while C002 imports normally through the
    // real (non-mocked) implementation.
    $this->partialMock(CourseImportService::class, function ($mock) {
        $mock->shouldReceive('importCourse')
            ->withArgs(fn ($courseDir, $code) => $code === 'C001-HTML-101')
            ->andThrow(new \RuntimeException('simulated total failure'));
    });

    $this->artisan('edtech:import-courses', ['path' => $tmp])->assertSuccessful();

    expect(Course::count())->toBe(1)
        ->and(Course::where('code', 'C002-CSS-101')->first()->status)->toBe('ready')
        ->and(Course::where('code', 'C001-HTML-101')->exists())->toBeFalse();
});
