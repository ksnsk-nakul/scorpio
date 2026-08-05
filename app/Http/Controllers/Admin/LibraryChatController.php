<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatThread;
use App\Services\ChatService;
use App\Support\RagConnectionGuard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        // ChatThread::messages() already applies orderBy('id') ascending, so chaining
        // latest('id') here just appends a second, ineffective ORDER BY -- the first
        // clause wins since 'id' is unique, silently returning the OLDEST assistant
        // message instead of the answer to the question just asked. reorder() clears
        // the existing order first so this actually gets the most recent one.
        $assistantMessage = $thread->messages()->where('role', 'assistant')->reorder('id', 'desc')->first();

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

    public function index(Request $request): JsonResponse
    {
        // SECURITY: scope strictly to the authenticated user's own threads (same IDOR-safety
        // convention as store()/history()/destroy() above).
        $threads = ChatThread::where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (ChatThread $thread) {
                // NOTE: ChatThread::messages() already applies orderBy('id') ascending;
                // chaining latest('id')/orderByDesc('id') directly would append a second,
                // ineffective ORDER BY clause (the first one wins since `id` is unique).
                // reorder() clears it first so we actually get the most recent message.
                $last = $thread->messages()->reorder('id', 'desc')->first();

                return [
                    'id' => $thread->id,
                    'title' => $thread->title,
                    'last_message' => $last ? Str::limit($last->content, 100) : null,
                    'last_message_role' => $last?->role,
                    'updated_at' => $thread->updated_at,
                ];
            });

        return response()->json(['threads' => $threads]);
    }

    public function show(Request $request, int $thread): JsonResponse
    {
        // SECURITY: scope strictly to the authenticated user's own thread (same IDOR-safety
        // convention as store()/history()/destroy() above). Unlike destroy()'s silent
        // no-op, a 404 here is correct — this is a read the user explicitly asked for by
        // clicking a specific thread, not a "delete something that may already be gone" action.
        $chatThread = ChatThread::where('user_id', $request->user()->id)->find($thread);

        if (! $chatThread) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        $messages = $chatThread->messages()->orderBy('id')->get()->map(fn ($m) => [
            'role' => $m->role,
            'content' => $m->content,
            'citations' => $m->citations ?? [],
        ]);

        return response()->json(['thread_id' => $chatThread->id, 'messages' => $messages]);
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
