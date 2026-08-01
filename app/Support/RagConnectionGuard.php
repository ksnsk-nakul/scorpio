<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RagConnectionGuard
{
    private static ?bool $available = null;

    /**
     * Whether the `rag` Postgres connection is configured and reachable. Cached for the
     * life of the process so migrate/test runs only pay the connection-attempt cost once,
     * whether or not real Supabase credentials are present.
     */
    public static function available(): bool
    {
        if (self::$available !== null) {
            return self::$available;
        }

        try {
            DB::connection('rag')->getPdo();
            self::$available = true;
        } catch (Throwable $e) {
            Log::warning('Skipping `rag` Postgres migration: connection is not configured or unreachable.', [
                'exception' => $e->getMessage(),
            ]);
            self::$available = false;
        }

        return self::$available;
    }
}
