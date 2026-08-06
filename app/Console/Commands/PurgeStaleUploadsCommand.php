<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Services\BookDeletionService;
use Illuminate\Console\Command;

class PurgeStaleUploadsCommand extends Command
{
    protected $signature = 'library:purge-stale-uploads {--dry-run : Report what would be purged without deleting anything} {--hours=1 : How long a book may sit in pending/processing before it is purged}';

    protected $description = 'Delete uploads stuck in pending/processing beyond the given window, freeing the title/slug so the user can re-upload.';

    public function handle(BookDeletionService $bookDeletionService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $hours = (float) $this->option('hours');

        $stale = Book::whereIn('status', ['pending', 'processing'])
            ->where('updated_at', '<', now()->subHours($hours))
            ->get();

        foreach ($stale as $book) {
            $this->line("{$book->id}: {$book->title} (status={$book->status}, updated_at={$book->updated_at})");

            if (! $dryRun) {
                $bookDeletionService->delete($book);
            }
        }

        $verb = $dryRun ? 'Would purge' : 'Purged';
        $this->info("{$verb} {$stale->count()} stale upload(s).");

        return self::SUCCESS;
    }
}
