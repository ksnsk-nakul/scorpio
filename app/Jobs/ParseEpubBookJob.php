<?php
namespace App\Jobs;

use App\Models\Book;
use App\Services\EpubParsingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
    }

    public function failed(Throwable $e): void
    {
        $this->book->update(['status' => 'failed', 'status_reason' => $e->getMessage()]);
    }
}
