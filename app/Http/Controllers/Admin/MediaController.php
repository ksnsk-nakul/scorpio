<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function __construct(private MediaService $media) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file']);

        $record = $this->media->store($request->file('file'), $request->user());

        return response()->json([
            'id'        => $record->id,
            'filename'  => $record->filename,
            'mime_type' => $record->mime_type,
            'size'      => $record->size,
            'path'      => $record->path,
            'url'       => $record->url,
            'is_image'  => $record->isImage(),
            'is_video'  => $record->isVideo(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $media = Media::findOrFail($id);
        Storage::disk($media->disk)->delete($media->path);
        $media->delete();
        return response()->json(['ok' => true]);
    }
}
