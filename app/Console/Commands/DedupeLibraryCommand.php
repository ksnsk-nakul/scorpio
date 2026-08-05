<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Models\BookAlternateTitle;
use App\Models\Series;
use App\Services\BookDeletionService;
use App\Services\SeriesNameExtractor;
use Illuminate\Console\Command;

class DedupeLibraryCommand extends Command
{
    protected $signature = 'library:dedupe {--dry-run : Report what would happen without deleting/persisting anything}';

    protected $description = 'Backfill series/volume metadata and merge duplicate books in the e-library.';

    public function handle(SeriesNameExtractor $extractor, BookDeletionService $bookDeletionService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Step 1: backfill series_id/volume_number for books that don't have it yet.
        $backfillTargets = Book::whereNull('series_id')
            ->whereNull('volume_number')
            ->where('status', '!=', 'failed')
            ->get();

        // In-memory extraction results, keyed by book id — used for grouping so the
        // dry-run path never needs anything persisted to disk.
        $extracted = [];
        foreach ($backfillTargets as $book) {
            $result = $extractor->extract($book->title);
            $extracted[$book->id] = $result;

            if ($result['series_name'] !== null && ! $dryRun) {
                $book->series_id = Series::findOrCreateByName($result['series_name'])->id;
                $book->volume_number = $result['volume_number'];
                $book->save();
            }
        }

        // Step 2: group.
        $allBooks = Book::where('status', '!=', 'failed')->get()->keyBy('id');

        $seriesKey = function (Book $book) use ($extracted): ?string {
            if ($book->series_id !== null && $book->volume_number !== null) {
                return 'sid:' . $book->series_id . ':' . $book->volume_number;
            }

            $result = $extracted[$book->id] ?? null;
            if ($result && $result['series_name'] !== null) {
                return 'name:' . mb_strtolower(trim($result['series_name'])) . ':' . $result['volume_number'];
            }

            return null;
        };

        $groupA = $allBooks->filter(fn (Book $b) => $seriesKey($b) !== null)
            ->groupBy(fn (Book $b) => $seriesKey($b));

        $ungrouped = $allBooks->reject(fn (Book $b) => $seriesKey($b) !== null);
        $groupB = $ungrouped->groupBy(fn (Book $b) => $extractor->normalize($b->title));

        // groupBy() on an Eloquent Collection still produces an Eloquent Collection whose
        // *values* are themselves Collections (not Models) — Eloquent Collection::merge()
        // assumes Model values and calls getKey() on them, so drop to the base Support
        // Collection first to merge these two group-maps together safely.
        $groups = $groupA->toBase()->merge($groupB)->filter(fn ($group) => $group->count() > 1)->values();

        $rows = [];
        $totalDeleted = 0;
        $groupNum = 0;

        foreach ($groups as $group) {
            $groupNum++;

            $survivor = $group->sort(function (Book $a, Book $b) {
                $aReady = $a->status === 'ready' ? 1 : 0;
                $bReady = $b->status === 'ready' ? 1 : 0;
                if ($aReady !== $bReady) {
                    return $bReady <=> $aReady;
                }

                $aChapters = $a->chapters()->count();
                $bChapters = $b->chapters()->count();
                if ($aChapters !== $bChapters) {
                    return $bChapters <=> $aChapters;
                }

                return $a->created_at <=> $b->created_at;
            })->first();

            $nonSurvivors = $group->reject(fn (Book $b) => $b->id === $survivor->id);

            $rows[] = [
                $groupNum,
                "{$survivor->id}: {$survivor->title}",
                $nonSurvivors->map(fn (Book $b) => "{$b->id}: {$b->title}")->implode(', '),
            ];

            foreach ($nonSurvivors as $nonSurvivor) {
                if (! $dryRun) {
                    BookAlternateTitle::create([
                        'book_id' => $survivor->id,
                        'title' => $nonSurvivor->title,
                        'source' => 'cleanup',
                        'created_at' => now(),
                    ]);
                    $bookDeletionService->delete($nonSurvivor);
                }

                $totalDeleted++;
            }
        }

        $this->table(['Group #', 'Survivor', 'Merged away'], $rows);

        if ($dryRun) {
            $this->info("Would delete {$totalDeleted} books across {$groupNum} groups.");
        } else {
            $this->info("Deleted {$totalDeleted} books across {$groupNum} groups.");
        }

        return self::SUCCESS;
    }
}
