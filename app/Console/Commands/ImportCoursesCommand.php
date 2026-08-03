<?php

namespace App\Console\Commands;

use App\Services\CourseImportService;
use Illuminate\Console\Command;

class ImportCoursesCommand extends Command
{
    protected $signature = 'edtech:import-courses {path : Path to the Courses directory containing COURSE-INDEX.md}';

    protected $description = 'Import every content-complete course from a CourseWork-style Courses directory into the database.';

    public function handle(CourseImportService $service): int
    {
        $path = rtrim($this->argument('path'), '/');
        $indexPath = "{$path}/COURSE-INDEX.md";

        if (! is_file($indexPath)) {
            $this->error("No COURSE-INDEX.md found at {$indexPath}");

            return self::FAILURE;
        }

        $rows = $this->parseIndexRows(file_get_contents($indexPath));
        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if (! str_contains($row['status'], 'content complete')) {
                $skipped++;

                continue;
            }

            $courseDir = collect(glob("{$path}/{$row['code']}-*", GLOB_ONLYDIR))->first();
            if (! $courseDir) {
                $this->warn("No folder found for {$row['code']}, skipping.");
                $skipped++;

                continue;
            }

            $this->info("Importing {$row['code']}: {$row['title']}");

            try {
                $service->importCourse($courseDir, $row['code']);
                $imported++;
            } catch (\Throwable $e) {
                $this->warn("Failed to import {$row['code']}: {$e->getMessage()}");
                $skipped++;

                continue;
            }
        }

        $this->info("Imported: {$imported}, Skipped: {$skipped}");

        return self::SUCCESS;
    }

    /** @return array<int, array{code: string, title: string, status: string}> */
    private function parseIndexRows(string $content): array
    {
        // Match the first "| Code | ... | Status |" table header (present under
        // ## Catalog / ### Generated in the real COURSE-INDEX.md, and as a bare
        // table in CourseFixtureBuilder-generated fixtures) and capture every
        // row until the table ends.
        if (! preg_match('/\|\s*Code\s*\|.*\|\s*Status\s*\|\s*\n\s*\|[-| ]+\|\s*\n((?:\|.+\|\s*\n?)+)/', $content, $m)) {
            return [];
        }

        $rows = [];
        foreach (explode("\n", trim($m[1])) as $line) {
            $cells = array_map('trim', explode('|', trim($line, '| ')));
            if (count($cells) < 6) {
                continue;
            }
            [$code, $title, , , , $status] = $cells;
            $rows[] = ['code' => $code, 'title' => $title, 'status' => $status];
        }

        return $rows;
    }
}
