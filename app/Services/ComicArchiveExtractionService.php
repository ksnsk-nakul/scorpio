<?php
namespace App\Services;

use App\Models\Media;
use FilesystemIterator;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

class ComicArchiveExtractionService
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function extract(Media $media): array
    {
        $sourcePath = Storage::disk($media->disk)->path($media->path);
        $tempDir    = storage_path("app/tmp-comics/{$media->id}");

        if (is_dir($tempDir)) {
            $this->deleteDirectory($tempDir);
        }
        mkdir($tempDir, 0755, true);

        if ($media->extension() === 'cbz') {
            $this->extractZip($sourcePath, $tempDir);
        } else {
            $this->extractRar($sourcePath, $tempDir);
        }

        $manifest = [];
        foreach ($this->collectImages($tempDir) as $index => $path) {
            $pageNumber = str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            $ext        = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $filename   = "page-{$pageNumber}.{$ext}";

            Storage::disk('public')->put("comics/{$media->id}/{$filename}", file_get_contents($path));
            $manifest[] = $filename;
        }

        $this->deleteDirectory($tempDir);

        return $manifest;
    }

    private function extractZip(string $sourcePath, string $tempDir): void
    {
        $zip = new ZipArchive();
        if ($zip->open($sourcePath) !== true) {
            throw new RuntimeException('Unable to open CBZ archive.');
        }
        $zip->extractTo($tempDir);
        $zip->close();
    }

    private function extractRar(string $sourcePath, string $tempDir): void
    {
        $binary = config('media.unrar_binary', 'unrar');
        $result = Process::timeout(120)->run([$binary, 'x', '-y', $sourcePath, $tempDir . '/']);

        if (! $result->successful()) {
            throw new RuntimeException("unrar extraction failed: {$result->errorOutput()}");
        }
    }

    private function collectImages(string $dir): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        $paths = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array(strtolower($file->getExtension()), self::IMAGE_EXTENSIONS, true)) {
                $paths[] = $file->getPathname();
            }
        }

        sort($paths, SORT_NATURAL);

        return $paths;
    }

    private function deleteDirectory(string $dir): void
    {
        foreach (glob("{$dir}/*") ?: [] as $file) {
            is_dir($file) ? $this->deleteDirectory($file) : unlink($file);
        }
        rmdir($dir);
    }
}
