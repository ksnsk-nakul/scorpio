<?php

namespace App\Services;

use App\Models\Course;
use App\Models\PricingTier;
use Throwable;

class CourseImportService
{
    public function importCourse(string $courseDir, string $code): Course
    {
        $title = $this->extractTitle($courseDir) ?? $code;
        $existing = Course::where('code', $code)->first();

        $course = Course::updateOrCreate(
            ['code' => $code],
            [
                'title' => $title,
                'slug' => $existing?->slug ?? Course::uniqueSlug($title),
                'description' => $this->extractDescription($courseDir),
                'source_path' => $courseDir,
                'status' => 'importing',
                'status_reason' => null,
            ]
        );

        try {
            $this->importPricingTiers($course, $courseDir);
            // Task 5 adds a call to $this->importModules($course, $courseDir); here.

            $course->status = 'ready';
            $course->imported_at = now();
            $course->save();
        } catch (Throwable $e) {
            // One malformed course must not abort the whole catalog import — isolate
            // the failure onto this course's own row (mirrors Book's pending/processing/
            // ready/failed lifecycle) so ImportCoursesCommand's loop can continue to the
            // next course, and the admin UI shows exactly which course needs attention.
            $course->update(['status' => 'failed', 'status_reason' => $e->getMessage()]);
        }

        return $course->fresh('pricingTiers');
    }

    private function extractTitle(string $courseDir): ?string
    {
        $path = "{$courseDir}/00-course-info/intro-and-summary.md";
        if (! is_file($path)) {
            return null;
        }

        $line = collect(file($path))->first(fn ($l) => str_starts_with(trim($l), '# '));
        return $line ? trim(substr(trim($line), 2)) : null;
    }

    private function extractDescription(string $courseDir): ?string
    {
        $path = "{$courseDir}/00-course-info/intro-and-summary.md";
        if (! is_file($path)) {
            return null;
        }

        $lines = array_filter(
            array_map('trim', file($path)),
            fn ($l) => $l !== '' && ! str_starts_with($l, '#')
        );

        return $lines ? implode(' ', $lines) : null;
    }

    private function importPricingTiers(Course $course, string $courseDir): void
    {
        $path = "{$courseDir}/00-course-info/pricing-and-format.md";
        if (! is_file($path)) {
            return;
        }

        $content = file_get_contents($path);
        if (! preg_match('/## Tiers\s*\n\s*\n(\|.+\|(?:\n\|.+\|)*)/', $content, $m)) {
            return;
        }

        $rows = array_slice(explode("\n", trim($m[1])), 2); // drop header + separator rows

        $course->pricingTiers()->delete();

        foreach ($rows as $row) {
            $cells = array_map('trim', explode('|', trim($row, '| ')));
            if (count($cells) < 4) {
                continue;
            }

            [$name, $included, $inr, $usd] = $cells;

            PricingTier::create([
                'course_id' => $course->id,
                'name' => $name,
                'description' => $included,
                'price_inr_paise' => $this->parseMoneyToMinorUnits($inr),
                'price_usd_cents' => $this->parseMoneyToMinorUnits($usd),
            ]);
        }
    }

    private function parseMoneyToMinorUnits(string $raw): int
    {
        if (stripos($raw, 'free') !== false) {
            return 0;
        }

        $digits = preg_replace('/[^0-9.]/', '', $raw);
        return (int) round(((float) $digits) * 100);
    }
}
