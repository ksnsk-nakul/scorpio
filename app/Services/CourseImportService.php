<?php

namespace App\Services;

use App\Jobs\GenerateTopicSlidesJob;
use App\Models\Course;
use App\Models\Material;
use App\Models\Module;
use App\Models\PricingTier;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CourseImportService
{
    public function importCourse(string $courseDir, string $code): Course
    {
        try {
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
        } catch (Throwable $e) {
            // No Course row exists to mark as failed — log for operator
            // visibility and rethrow so the caller (ImportCoursesCommand)
            // can isolate the failure at the loop level.
            Log::warning('Course import failed before a Course row could be created', [
                'code' => $code,
                'course_dir' => $courseDir,
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }

        try {
            $this->importPricingTiers($course, $courseDir);
            DB::transaction(fn () => $this->importModules($course, $courseDir));

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
        return $this->extractH1("{$courseDir}/00-course-info/intro-and-summary.md");
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
        if (! preg_match('/(?m)^## Tiers\s*\n\s*\n(\|.+\|(?:\n\|.+\|)*)/', $content, $m)) {
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

    // Accepted v1 limitation: re-imports only add/update Module/Topic/Material
    // rows — they never remove rows whose source directory was renamed or
    // deleted between imports (unlike importPricingTiers, which deletes-then-
    // recreates all tiers every time). Full stale-row cleanup was judged out
    // of scope for now since course directory structure is expected to be
    // stable/append-only in practice.
    protected function importModules(Course $course, string $courseDir): void
    {
        $selfpacedDir = "{$courseDir}/selfpaced";
        if (! is_dir($selfpacedDir)) {
            return;
        }

        $moduleDirs = collect(glob("{$selfpacedDir}/module-*", GLOB_ONLYDIR))->sort()->values();

        foreach ($moduleDirs as $index => $moduleDir) {
            $slug = $this->slugFromDirName(basename($moduleDir));
            $title = $this->extractH1($moduleDir . '/README.md') ?? Str::headline($slug);

            $module = Module::updateOrCreate(
                ['course_id' => $course->id, 'slug' => $slug],
                ['title' => $title, 'sort_order' => $index]
            );

            $this->importTopics($module, $moduleDir);
        }
    }

    private function importTopics(Module $module, string $moduleDir): void
    {
        $topicDirs = collect(glob("{$moduleDir}/topic-*", GLOB_ONLYDIR))->sort()->values();

        foreach ($topicDirs as $index => $topicDir) {
            $slug = $this->slugFromDirName(basename($topicDir));
            $title = $this->extractH1($topicDir . '/README.md') ?? Str::headline($slug);

            $topic = Topic::updateOrCreate(
                ['module_id' => $module->id, 'slug' => $slug],
                ['title' => $title, 'sort_order' => $index]
            );

            $this->importMaterials($topic, $topicDir);
        }
    }

    private function importMaterials(Topic $topic, string $topicDir): void
    {
        $files = [
            'notes' => ["{$topicDir}/notes/notes.md", 'downloadable'],
            'task' => ["{$topicDir}/tasks/task.md", 'view_only'],
            'demo_problem' => [$this->firstDemoFile("{$topicDir}/demo/problem"), 'downloadable'],
            'demo_try_it' => [$this->firstDemoFile("{$topicDir}/demo/try-it"), 'view_only'],
            'demo_solution' => [$this->firstDemoFile("{$topicDir}/demo/solution"), 'view_only'],
        ];

        $previousNotes = Material::where('topic_id', $topic->id)->where('type', 'notes')->value('content');
        $newNotesPath = $files['notes'][0];
        $newNotes = is_file($newNotesPath) ? file_get_contents($newNotesPath) : null;

        foreach ($files as $type => [$path, $policy]) {
            if (! $path || ! is_file($path)) {
                continue;
            }

            Material::updateOrCreate(
                ['topic_id' => $topic->id, 'type' => $type],
                ['content' => file_get_contents($path), 'download_policy' => $policy, 'status' => 'ready']
            );
        }

        $existingSlides = Material::where('topic_id', $topic->id)->where('type', 'slides')->first();
        $notesUnchanged = $newNotes !== null && $newNotes === $previousNotes;
        $slidesAlreadyReady = $existingSlides && $existingSlides->status === 'ready';

        if (! ($notesUnchanged && $slidesAlreadyReady)) {
            Material::updateOrCreate(
                ['topic_id' => $topic->id, 'type' => 'slides'],
                ['download_policy' => 'view_only', 'status' => 'generating']
            );
            GenerateTopicSlidesJob::dispatch($topic);
        }

        Material::updateOrCreate(
            ['topic_id' => $topic->id, 'type' => 'video'],
            ['download_policy' => 'view_only', 'status' => 'not_generated']
        );
    }

    private function firstDemoFile(string $dir): ?string
    {
        $files = glob("{$dir}/*.html") ?: glob("{$dir}/*");
        $files = array_filter($files ?: [], fn ($f) => basename($f) !== 'README.md');
        return $files ? array_values($files)[0] : null;
    }

    private function extractH1(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $lines = file($path);
        if ($lines === false) {
            return null;
        }

        $line = collect($lines)->first(fn ($l) => str_starts_with(trim($l), '# '));
        return $line ? trim(substr(trim($line), 2)) : null;
    }

    private function slugFromDirName(string $dirName): string
    {
        // "module-01-html-fundamentals" -> "html-fundamentals"; "topic-03-tables" -> "tables"
        $slug = preg_replace('/^(module|topic)-\d+-/', '', $dirName);

        // A dir named e.g. "module-01-" (digits + trailing hyphen, nothing
        // after) would otherwise strip down to an empty string.
        return $slug !== '' ? $slug : $dirName;
    }
}
