<?php

namespace App\Support;

class ProfanityGuard
{
    private static array $blocklist = [
        'fuck', 'shit', 'bitch', 'asshole', 'bastard', 'cunt', 'damn', 'dick',
        'cock', 'pussy', 'whore', 'slut', 'nigger', 'faggot', 'retard', 'rape',
    ];

    public static function passes(string $text): bool
    {
        $lower = strtolower($text);
        foreach (self::$blocklist as $word) {
            if (str_contains($lower, $word)) {
                return false;
            }
        }
        return true;
    }

    public static function fails(string $text): bool
    {
        return !self::passes($text);
    }
}
