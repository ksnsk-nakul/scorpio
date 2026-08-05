<?php
namespace App\Services;

class SeriesNameExtractor
{
    /** @return array{series_name: ?string, volume_number: ?int} */
    public function extract(string $rawTitle): array
    {
        $title = $rawTitle;

        // Step 1: strip trailing bracket/paren tags, repeatedly (files stack multiple:
        // "... Volume 01 [Yen Press][Kobo]")
        while (preg_match('/\s*[\[\(][^\]\)]*[\]\)]\s*$/u', $title)) {
            $title = preg_replace('/\s*[\[\(][^\]\)]*[\]\)]\s*$/u', '', $title);
        }

        $patterns = [
            '/^(?<series>.+?)\s*[-:,]\s*Volume\s+0*(?<vol>\d+)$/iu',
            '/^(?<series>.+?)\s*[-:,]\s*Vol\.?\s*0*(?<vol>\d+)$/iu',
            '/^(?<series>.+?)\s*[-:,]\s*Book\s+0*(?<vol>\d+)$/iu',
            '/^(?<series>.+?)\s*[-:,]\s*v0*(?<vol>\d+)$/iu',
            '/^(?<series>.+?)\s*#\s*0*(?<vol>\d+)$/u',
            '/^(?<series>.+?)\s*-\s*0*(?<vol>\d{1,3})$/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $title, $m)) {
                $series = trim($m['series'], " \t\n\r\0\x0B-:,");
                if ($series === '') {
                    continue;
                }
                return ['series_name' => $series, 'volume_number' => (int) $m['vol']];
            }
        }

        return ['series_name' => null, 'volume_number' => null];
    }

    /** Normalized form used for exact-match duplicate detection (no series case). */
    public function normalize(string $rawTitle): string
    {
        $title = $rawTitle;
        while (preg_match('/\s*[\[\(][^\]\)]*[\]\)]\s*$/u', $title)) {
            $title = preg_replace('/\s*[\[\(][^\]\)]*[\]\)]\s*$/u', '', $title);
        }
        $title = mb_strtolower(trim($title));
        $title = preg_replace('/\s+/u', ' ', $title);
        return trim($title, " \t\n\r\0\x0B-:,.");
    }
}
