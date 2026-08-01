<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatThread;
use App\Services\ChatService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class LibraryChatController extends Controller
{
    public function index(Request $request): Response
    {
        $threads = ChatThread::where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'updated_at']);

        return Inertia::render('Admin/Library/Chat/Index', [
            'threads' => $threads,
            'activeThread' => null,
        ]);
    }

    public function show(Request $request, ChatThread $thread): Response
    {
        abort_unless($thread->user_id === $request->user()->id, 403);

        $threads = ChatThread::where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'updated_at']);

        return Inertia::render('Admin/Library/Chat/Index', [
            'threads' => $threads,
            'activeThread' => $thread->load('messages'),
        ]);
    }

    public function store(Request $request, ChatService $chatService): RedirectResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:2000', function ($attribute, $value, $fail) {
                if (trim($value) === '') {
                    $fail('Question cannot be empty.');
                }
            }],
            'thread_id' => ['nullable', 'integer'],
        ]);

        // SECURITY: $request->user()->id is the only acceptable source for the userId
        // passed into ChatService::ask(). ChatService's ownership check (ChatThread::where
        // ('user_id', $userId)->findOrFail($threadId)) only protects against IDOR if the
        // real authenticated user's ID is what's passed here — never derive this from
        // request input.
        try {
            $result = $chatService->ask(
                userId: $request->user()->id,
                threadId: $data['thread_id'] ?? null,
                question: $data['question'],
            );
        } catch (ModelNotFoundException $e) {
            // Re-throw as-is: this is the IDOR guard (thread_id belongs to someone else,
            // or doesn't exist) and must surface as Laravel's normal 404, not be
            // swallowed into a friendlier redirect that could mask the ownership check.
            throw $e;
        } catch (RuntimeException $e) {
            // GeminiClient failures (API down, rate-limited, malformed response) — the
            // question was already persisted by ChatService before the failing call, so
            // send the admin back to see it (with no answer yet) rather than a raw 500.
            return back()->withErrors(['question' => 'Something went wrong asking the library: '.$e->getMessage()]);
        }

        return redirect()->route('admin.library.chat.show', $result['thread']->id);
    }
}
