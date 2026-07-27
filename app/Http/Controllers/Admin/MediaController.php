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

        $context = $request->input('context', 'default');
        $record = $this->media->store($request->file('file'), $request->user(), $context);

        return response()->json([
            'id'        => $record->id,
            'filename'  => $record->filename,
            'mime_type' => $record->mime_type,
            'size'      => $record->size,
            'path'      => $record->path,
            'url'       => $record->url,
            'is_image'  => $record->isImage(),
            'is_video'  => $record->isVideo(),
            'status'    => $record->status,
        ]);
    }

    public function status(int $id): JsonResponse
    {
        $media = Media::findOrFail($id);
        $this->authorize('view', $media);

        return response()->json([
            'id'                => $media->id,
            'status'            => $media->status,
            'status_reason'     => $media->status_reason,
            'converted_pdf_url' => $media->converted_pdf_url,
            'comic_page_urls'   => $media->comic_page_urls,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $media = Media::findOrFail($id);
        $this->authorize('delete', $media);
        Storage::disk($media->disk)->delete($media->path);
        $media->delete();
        return response()->json(['ok' => true]);
    }
}
