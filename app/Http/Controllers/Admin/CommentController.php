<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Task;
use App\Services\MediaService;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function __construct(private MediaService $media) {}

    public function store(Request $request, Task $task)
    {
        $data = $request->validate([
            'body'      => 'required|string',
            'media_ids' => 'nullable|array',
        ]);

        $mediaIds = $data['media_ids'] ?? [];

        $comment = $task->comments()->create([
            'user_id' => $request->user()->id,
            'body'    => $data['body'],
        ]);

        if ($mediaIds) {
            $this->media->attach($mediaIds, $comment);
        }

        return back()->with('success', 'Comment added.');
    }

    public function destroy(Request $request, Comment $comment)
    {
        $this->authorize('delete', $comment);
        $comment->delete();
        return back()->with('success', 'Comment deleted.');
    }
}
