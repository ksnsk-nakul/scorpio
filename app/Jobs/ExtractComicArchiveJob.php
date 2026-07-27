<?php
namespace App\Jobs;

use App\Models\Media;
use App\Services\ComicArchiveExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ExtractComicArchiveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Media $media) {}

    public function handle(ComicArchiveExtractionService $extractor): void
    {
        $this->media->update(['status' => 'processing']);
        $manifest = $extractor->extract($this->media);
        $this->media->update(['status' => 'ready', 'page_manifest' => $manifest]);
    }

    public function failed(Throwable $e): void
    {
        $this->media->update(['status' => 'failed', 'status_reason' => $e->getMessage()]);
    }
}
