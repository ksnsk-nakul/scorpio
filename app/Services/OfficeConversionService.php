<?php
namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OfficeConversionService
{
    public function convert(Media $media): void
    {
        $binary     = config('media.office_binary', 'soffice');
        $sourcePath = Storage::disk($media->disk)->path($media->path);
        $outputDir  = storage_path('app/tmp-conversions');

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $result = Process::timeout(120)->run([
            $binary, '--headless', '--convert-to', 'pdf', '--outdir', $outputDir, $sourcePath,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException("soffice conversion failed: {$result->errorOutput()}");
        }

        $convertedPath = $outputDir . '/' . pathinfo($sourcePath, PATHINFO_FILENAME) . '.pdf';

        if (! file_exists($convertedPath)) {
            throw new RuntimeException('soffice did not produce an output file.');
        }

        Storage::disk('public')->put("conversions/{$media->id}.pdf", file_get_contents($convertedPath));
        unlink($convertedPath);
    }
}
