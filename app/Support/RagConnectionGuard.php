<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RagConnectionGuard
{
    private static ?bool $available = null;

    // PHP-FPM resets static properties between requests (each request gets a fresh
    // execution context even though the OS worker process is reused), so the static
    // cache above only helps for repeat calls WITHIN one request -- it does nothing
    // for repeat page loads. Since `ragAvailable` is shared on every Inertia page
    // (HandleInertiaRequests), that meant every single request paid the full RAG
    // connect-timeout cost whenever the connection is unreachable. A short-TTL cross-
    // request cache bounds that to once per TTL window instead of once per request.
    private const CACHE_TTL_SECONDS = 30;

    /**
     * Whether the `rag` Postgres connection is configured and reachable. Cached for the
     * life of the process (fast path for repeat calls within one request) and, on top of
     * that, cached across requests for CACHE_TTL_SECONDS so the connection-attempt cost
     * (and its timeout, see config/database.php's `rag` connection options) is paid at
     * most once per window rather than on every page load.
     */
    public static function available(): bool
    {
        if (self::$available !== null) {
            return self::$available;
        }

        self::$available = Cache::remember('rag-connection-available', self::CACHE_TTL_SECONDS, function () {
            try {
                DB::connection('rag')->getPdo();
                return true;
            } catch (Throwable $e) {
                Log::warning('Skipping `rag` Postgres migration: connection is not configured or unreachable.', [
                    'exception' => $e->getMessage(),
                ]);
                return false;
            }
        });

        return self::$available;
    }
}
