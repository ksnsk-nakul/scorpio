<?php

namespace Tests\Support;

class CourseFixtureBuilder
{
    /**
     * @param array<int, array{title: string, topics: array<int, array{title: string, notes?: string, task?: string}>}> $modules
     * @param array<int, array{name: string, price_inr: string, price_usd: string, included: string}> $tiers
     */
    public static function build(
        string $tmpDir,
        string $code,
        string $courseTitle,
        string $description,
        array $modules,
        array $tiers = [],
    ): string {
        $courseDir = "{$tmpDir}/{$code}-" . \Illuminate\Support\Str::slug($courseTitle);
        @mkdir("{$courseDir}/00-course-info", 0777, true);
        @mkdir("{$courseDir}/selfpaced", 0777, true);

        file_put_contents("{$courseDir}/00-course-info/intro-and-summary.md", "# Intro\n\n{$description}\n");

        $tierRows = implode("\n", array_map(
            fn ($t) => "| {$t['name']} | {$t['included']} | {$t['price_inr']} | {$t['price_usd']} |",
            $tiers
        ));
        file_put_contents("{$courseDir}/00-course-info/pricing-and-format.md", <<<MD
        # Pricing & Format

        ## Tiers

        | Tier | Included | Price (India) | Price (International) |
        |---|---|---|---|
        {$tierRows}
        MD);

        foreach ($modules as $mi => $module) {
            $mn = str_pad((string) ($mi + 1), 2, '0', STR_PAD_LEFT);
            $moduleDir = "{$courseDir}/selfpaced/module-{$mn}-" . \Illuminate\Support\Str::slug($module['title']);
            @mkdir($moduleDir, 0777, true);
            file_put_contents("{$moduleDir}/README.md", "# {$module['title']}\n");

            foreach ($module['topics'] as $ti => $topic) {
                $tn = str_pad((string) ($ti + 1), 2, '0', STR_PAD_LEFT);
                $topicDir = "{$moduleDir}/topic-{$tn}-" . \Illuminate\Support\Str::slug($topic['title']);
                @mkdir("{$topicDir}/notes", 0777, true);
                @mkdir("{$topicDir}/tasks", 0777, true);
                @mkdir("{$topicDir}/demo/problem", 0777, true);
                @mkdir("{$topicDir}/demo/try-it", 0777, true);
                @mkdir("{$topicDir}/demo/solution", 0777, true);

                file_put_contents("{$topicDir}/README.md", "# {$topic['title']}\n");
                file_put_contents("{$topicDir}/notes/notes.md", $topic['notes'] ?? "# {$topic['title']}\n\nSome notes.");
                file_put_contents("{$topicDir}/tasks/task.md", $topic['task'] ?? "# Task\n\nDo the thing.");
                file_put_contents("{$topicDir}/demo/problem/index.html", '<html><body>problem</body></html>');
                file_put_contents("{$topicDir}/demo/try-it/index.html", '<html><body>try-it</body></html>');
                file_put_contents("{$topicDir}/demo/solution/index.html", '<html><body>solution</body></html>');
            }
        }

        return $courseDir;
    }

    public static function buildIndex(string $tmpDir, array $rows): void
    {
        $lines = implode("\n", array_map(
            fn ($r) => "| {$r['code']} | {$r['title']} | `{$r['slug']}` | Language | Standalone | Generated, content complete |",
            $rows
        ));
        file_put_contents("{$tmpDir}/COURSE-INDEX.md", <<<MD
        # Course Index

        | Code | Course | Slug | Layer | Coupling | Status |
        |---|---|---|---|---|---|
        {$lines}
        MD);
    }
}
