<?php
namespace App\Jobs;

use App\Models\Media;
use App\Services\OfficeConversionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ConvertOfficeDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Media $media) {}

    public function handle(OfficeConversionService $converter): void
    {
        $this->media->update(['status' => 'processing']);
        $converter->convert($this->media);
        $this->media->update(['status' => 'ready']);
    }

    public function failed(Throwable $e): void
    {
        $this->media->update(['status' => 'failed', 'status_reason' => $e->getMessage()]);
    }
}
