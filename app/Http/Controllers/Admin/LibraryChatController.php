<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatThread;
use App\Services\ChatService;
use App\Support\RagConnectionGuard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class LibraryChatController extends Controller
{
    public function store(Request $request, ChatService $chatService): JsonResponse
    {
        if (! RagConnectionGuard::available()) {
            return response()->json(['message' => "Advanced search isn't available right now — the search backend is unreachable. Please try again later."], 503);
        }

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
            // report the failure back to the drawer rather than a raw 500.
            return response()->json(['message' => 'Something went wrong asking the library: '.$e->getMessage()], 422);
        }

        $thread = $result['thread'];
        $assistantMessage = $thread->messages()->where('role', 'assistant')->latest('id')->first();

        return response()->json([
            'thread_id' => $thread->id,
            'answer' => $assistantMessage?->content,
            'citations' => $assistantMessage?->citations ?? [],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        if (! RagConnectionGuard::available()) {
            return response()->json(['thread_id' => null, 'messages' => []]);
        }

        // SECURITY: scope strictly to the authenticated user's own thread — never accept
        // or trust any thread/user identifier from the request (same IDOR-safety
        // convention as store() above).
        $thread = ChatThread::where('user_id', $request->user()->id)
            ->latest('updated_at')
            ->first();

        if (! $thread) {
            return response()->json(['thread_id' => null, 'messages' => []]);
        }

        $messages = $thread->messages()->orderBy('id')->get()->map(fn ($m) => [
            'role' => $m->role,
            'content' => $m->content,
            'citations' => $m->citations ?? [],
        ]);

        return response()->json(['thread_id' => $thread->id, 'messages' => $messages]);
    }

    public function destroy(Request $request, int $thread): JsonResponse
    {
        // SECURITY: scope strictly to the authenticated user's own thread (same IDOR-safety
        // convention as store()/history() above). ->find() (not ->findOrFail()) is
        // deliberate: a not-found/not-owned thread is treated as a silent no-op — deleting
        // something that's already gone (or was never yours) isn't an error from the
        // client's perspective.
        $chatThread = ChatThread::where('user_id', $request->user()->id)->find($thread);

        // The chat_messages.thread_id FK is cascadeOnDelete() at the DB level (see
        // 2026_07_31_100003_create_chat_messages_table.php), so deleting the thread is
        // sufficient — no need to delete messages explicitly first.
        $chatThread?->delete();

        return response()->json(['deleted' => true]);
    }
}
