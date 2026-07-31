<?php

namespace App\Console\Commands;

use App\Jobs\ParseEpubBookJob;
use App\Models\Book;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SeedLibraryFromDirectory extends Command
{
    protected $signature = 'library:seed-directory {paths* : One or more directories to scan for .epub files} {--dry-run : List what would be imported without creating anything}';

    protected $description = 'Bulk-import every .epub file found in the given directories into the e-library, skipping titles already present.';

    public function handle(): int
    {
        $uploader = User::role('admin')->first();
        if (! $uploader) {
            $this->error('No admin user found to attribute uploads to.');
            return self::FAILURE;
        }

        $existingTitles = Book::pluck('title')->map(fn ($t) => Str::lower(trim($t)))->flip();

        $files = collect($this->argument('paths'))
            ->flatMap(fn ($path) => glob(rtrim($path, '/') . '/*.epub') ?: [])
            ->unique()
            ->values();

        $this->info("Found {$files->count()} .epub files across the given directories.");

        $imported = 0;
        $skipped = 0;

        foreach ($files as $filePath) {
            $title = pathinfo($filePath, PATHINFO_FILENAME);
            $key = Str::lower(trim($title));

            if ($existingTitles->has($key)) {
                $skipped++;
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("Would import: {$title}");
                $imported++;
                continue;
            }

            $destination = 'books/uploads/' . Str::uuid() . '.epub';
            $stream = fopen($filePath, 'r');
            Storage::disk('public')->put($destination, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            $book = Book::create([
                'title' => $title,
                'source_epub_path' => $destination,
                'uploaded_by' => $uploader->id,
                'status' => 'pending',
            ]);

            ParseEpubBookJob::dispatch($book);

            $existingTitles->put($key, true);
            $imported++;
        }

        $this->info(($this->option('dry-run') ? '[dry-run] ' : '') . "Imported: {$imported}, Skipped (already present): {$skipped}");

        return self::SUCCESS;
    }
}
