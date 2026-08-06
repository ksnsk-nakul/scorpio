<?php
namespace App\Jobs;

use App\Models\Book;
use App\Services\EpubParsingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ParseEpubBookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Book $book) {}

    public function handle(EpubParsingService $parser): void
    {
        $this->book->update(['status' => 'processing']);
        $parser->parse($this->book);
        $this->book->status = 'ready';
        $this->book->save();

        // Once parsed, chapters/covers live in the DB and public disk under
        // books/{id}/ — the original upload is dead weight from here on
        // (retry() only needs it for failed/stuck books, never ready ones).
        Storage::disk('public')->delete($this->book->source_epub_path);

        IndexBookChunksJob::dispatch($this->book);
    }

    public function failed(Throwable $e): void
    {
        $this->book->update(['status' => 'failed', 'status_reason' => $e->getMessage()]);
    }
}
