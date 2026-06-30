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
    ];

    public function store(UploadedFile $file, User $user, string $context = 'default'): Media
    {
        $maxMb = (int) Setting::get('media_max_size_mb', 50);

        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES)) {
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
        ];
        $ext  = $mimeMap[$file->getMimeType()] ?? 'bin';
        $name = Str::uuid() . '.' . $ext;
        $path = $file->storeAs($dir, $name, $disk);

        return Media::create([
            'user_id'   => $user->id,
            'disk'      => $disk,
            'path'      => $path,
            'filename'  => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size'      => $file->getSize(),
        ]);
    }

    public function attach(array $mediaIds, Model $mediable): void
    {
        Media::whereIn('id', $mediaIds)
            ->whereNull('mediable_type')
            ->update([
                'mediable_type' => get_class($mediable),
                'mediable_id'   => $mediable->id,
            ]);
    }
}
