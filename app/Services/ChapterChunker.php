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
        // Only leaf block nodes: EPUB-exported HTML commonly nests block tags
        // (e.g. <blockquote><p>...</p></blockquote>, <li><p>...</p></li>). Matching
        // both the outer and inner tag would duplicate the same text into two
        // "paragraphs" — the [not(descendant::...)] predicates keep only the
        // innermost match in any such nesting.
        $blockTags = ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'blockquote'];
        $descendantCheck = implode(' or ', array_map(fn ($tag) => "descendant::{$tag}", $blockTags));
        $query = implode(' | ', array_map(fn ($tag) => "//{$tag}[not({$descendantCheck})]", $blockTags));
        $blocks = $xpath->query($query);

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
