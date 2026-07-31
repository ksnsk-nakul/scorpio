<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;

class ChapterChunker
{
    private const MAX_CHUNK_CHARS = 3000;

    /** @return string[] */
    public function chunk(string $html): array
    {
        $paragraphs = $this->extractParagraphs($html);
        if (empty($paragraphs)) {
            return [];
        }

        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            $candidate = $current === '' ? $paragraph : $current . "\n\n" . $paragraph;

            if (mb_strlen($candidate) > self::MAX_CHUNK_CHARS && $current !== '') {
                $chunks[] = $current;
                $current = $paragraph;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /** @return string[] */
    private function extractParagraphs(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>');
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $blocks = $xpath->query('//p | //h1 | //h2 | //h3 | //h4 | //h5 | //h6 | //li | //blockquote');

        $paragraphs = [];
        foreach ($blocks as $block) {
            $text = trim(preg_replace('/\s+/', ' ', $block->textContent));
            if ($text !== '') {
                $paragraphs[] = $text;
            }
        }

        return $paragraphs;
    }
}
