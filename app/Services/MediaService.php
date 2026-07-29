<?php
namespace App\Services;

use App\Models\Media;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MediaService
{
    private const ALLOWED_MIMES = [
        'image/jpeg','image/png','image/gif','image/webp','image/svg+xml',
        'video/mp4','video/quicktime','video/webm',
        'audio/mpeg','audio/wav','audio/x-wav',
        'text/plain','text/markdown','text/csv',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.oasis.opendocument.text',
        'application/epub+zip',
    ];

    // Zip/RAR mime detection varies by OS and PHP build, so archive uploads are
    // only trusted when the filename extension confirms cbz/cbr/epub. Real-world
    // epub files are often sniffed as generic application/zip, so that (and
    // application/octet-stream) must be trusted alongside the canonical
    // application/epub+zip when the extension is .epub.
    private const ARCHIVE_MIMES_BY_EXTENSION = [
        'cbz'  => ['application/zip', 'application/vnd.comicbook+zip', 'application/octet-stream'],
        'cbr'  => ['application/vnd.rar', 'application/x-rar-compressed', 'application/octet-stream'],
        'epub' => ['application/epub+zip', 'application/zip', 'application/octet-stream'],
    ];

    public function store(UploadedFile $file, User $user, string $context = 'default'): Media
    {
        $maxMb = (int) Setting::get('media_max_size_mb', 50);
        $mime  = $file->getMimeType();
        $ext   = strtolower($file->getClientOriginalExtension());

        $isArchive = isset(self::ARCHIVE_MIMES_BY_EXTENSION[$ext])
            && in_array($mime, self::ARCHIVE_MIMES_BY_EXTENSION[$ext], true);

        if (! $isArchive && ! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages(['file' => 'File type not allowed.']);
        }

        if ($file->getSize() > $maxMb * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => "Max file size is {$maxMb}MB."]);
        }

        $disk         = config('media.disk', 'public');
        $pathTemplate = config("media.paths.{$context}", config('media.paths.default', 'users/{user}/uploads'));
        $base         = str_replace('{user}', $user->id, $pathTemplate);
        $dir          = $base . '/' . now()->format('Y/m');
        $mimeMap = [
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/gif'       => 'gif',
            'image/webp'      => 'webp',
            'image/svg+xml'   => 'svg',
            'video/mp4'       => 'mp4',
            'video/quicktime' => 'mov',
            'video/webm'      => 'webm',
            'audio/mpeg'      => 'mp3',
            'audio/wav'       => 'wav',
            'audio/x-wav'     => 'wav',
            'text/plain'      => 'txt',
            'text/markdown'   => 'md',
            'text/csv'        => 'csv',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.oasis.opendocument.text' => 'odt',
            'application/epub+zip' => 'epub',
        ];
        $storedExt = $isArchive ? $ext : ($mimeMap[$mime] ?? 'bin');
        $name = Str::uuid() . '.' . $storedExt;
        $path = $file->storeAs($dir, $name, $disk);

        $media = Media::create([
            'user_id'   => $user->id,
            'disk'      => $disk,
            'path'      => $path,
            'filename'  => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'size'      => $file->getSize(),
        ]);

        $this->dispatchProcessingJobs($media);

        return $media;
    }

    private function dispatchProcessingJobs(Media $media): void
    {
        if ($media->needsOfficeConversion()) {
            $media->update(['status' => 'pending']);
            \App\Jobs\ConvertOfficeDocumentJob::dispatch($media);
        } elseif ($media->needsComicExtraction()) {
            $media->update(['status' => 'pending']);
            \App\Jobs\ExtractComicArchiveJob::dispatch($media);
        }
    }

    public function attach(array $mediaIds, Model $mediable, ?int $userId = null): void
    {
        $query = Media::whereIn('id', $mediaIds)->whereNull('mediable_type');

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $query->update([
            'mediable_type' => get_class($mediable),
            'mediable_id'   => $mediable->id,
        ]);
    }
}
