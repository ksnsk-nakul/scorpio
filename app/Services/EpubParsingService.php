<?php
namespace App\Services;

use App\Models\Author;
use App\Models\Book;
use App\Models\Chapter;
use DOMDocument;
use DOMNode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class EpubParsingService
{
    private const OPF_NS = 'http://www.idpf.org/2007/opf';
    private const DC_NS = 'http://purl.org/dc/elements/1.1/';
    private const CONTAINER_NS = 'urn:oasis:names:tc:opendocument:xmlns:container';

    public function parse(Book $book): void
    {
        // Book always stores its source file on the public disk — unlike
        // Media, there's no per-record configurable disk.
        $sourcePath = Storage::disk('public')->path($book->source_epub_path);

        $zip = new ZipArchive();
        if ($zip->open($sourcePath) !== true) {
            throw new RuntimeException('Unable to open EPUB archive.');
        }

        $opfPath = $this->locateOpf($zip);
        $opfDir = dirname($opfPath);
        $opfDir = ($opfDir === '.' || $opfDir === '') ? '' : $opfDir . '/';

        $opf = $this->readXml($zip, $opfPath);

        $metadata = $this->extractMetadata($opf);
        $manifest = $this->extractManifest($opf);
        $spineIds = $this->extractSpine($opf);

        $book->chapters()->delete();

        if (! empty($metadata['title'])) {
            $book->title = $metadata['title'];
            $book->slug = Book::uniqueSlug($metadata['title'], $book->id);
        }
        $book->description = $metadata['description'] ?? null;
        $book->language = $metadata['language'] ?? null;
        $book->publisher = $metadata['publisher'] ?? null;
        $book->published_date = $this->parseDate($metadata['date'] ?? null);
        $book->subject = $metadata['subject'] ?? null;
        $book->author_id = Author::findOrCreateByName($metadata['creator'] ?? 'Unknown')->id;

        $sortOrder = 0;
        foreach ($spineIds as $idref) {
            if (! isset($manifest[$idref])) {
                continue;
            }
            $item = $manifest[$idref];
            if (! str_contains($item['media_type'], 'html')) {
                continue;
            }

            $chapterPath = $opfDir . $item['href'];
            $html = $zip->getFromName($chapterPath);
            if ($html === false) {
                continue;
            }

            [$content, $title] = $this->processChapterHtml($html, $zip, $chapterPath, $book, $sortOrder);

            Chapter::create([
                'book_id' => $book->id,
                'title' => $title,
                'content' => $content,
                'sort_order' => $sortOrder,
            ]);

            $sortOrder++;
        }

        if ($sortOrder === 0) {
            $zip->close();
            throw new RuntimeException('No readable chapters found in the EPUB spine.');
        }

        $this->extractCover($zip, $opf, $manifest, $opfDir, $book);

        $zip->close();
        $book->save();
    }

    private function locateOpf(ZipArchive $zip): string
    {
        $containerXml = $zip->getFromName('META-INF/container.xml');
        if ($containerXml === false) {
            throw new RuntimeException('Missing META-INF/container.xml.');
        }

        $xml = new SimpleXMLElement($containerXml);
        $xml->registerXPathNamespace('c', self::CONTAINER_NS);
        $rootfiles = $xml->xpath('//c:rootfile');

        if (empty($rootfiles)) {
            throw new RuntimeException('No rootfile declared in container.xml.');
        }

        return (string) $rootfiles[0]['full-path'];
    }

    private function readXml(ZipArchive $zip, string $path): SimpleXMLElement
    {
        $content = $zip->getFromName($path);
        if ($content === false) {
            throw new RuntimeException("Missing file in archive: {$path}");
        }

        return new SimpleXMLElement($content);
    }

    private function extractMetadata(SimpleXMLElement $opf): array
    {
        $opf->registerXPathNamespace('dc', self::DC_NS);

        $first = fn (string $tag) => (string) ($opf->xpath("//dc:{$tag}")[0] ?? '') ?: null;
        $subjects = array_map(fn ($n) => (string) $n, $opf->xpath('//dc:subject') ?: []);

        return [
            'title' => $first('title'),
            'creator' => $first('creator'),
            'description' => $first('description'),
            'language' => $first('language'),
            'publisher' => $first('publisher'),
            'date' => $first('date'),
            'subject' => $subjects ? implode(', ', $subjects) : null,
        ];
    }

    private function extractManifest(SimpleXMLElement $opf): array
    {
        $opf->registerXPathNamespace('opf', self::OPF_NS);
        $manifest = [];

        foreach ($opf->xpath('//opf:manifest/opf:item') as $item) {
            $manifest[(string) $item['id']] = [
                'href' => (string) $item['href'],
                'media_type' => (string) $item['media-type'],
                'properties' => (string) $item['properties'],
            ];
        }

        return $manifest;
    }

    private function extractSpine(SimpleXMLElement $opf): array
    {
        $opf->registerXPathNamespace('opf', self::OPF_NS);
        $ids = [];

        foreach ($opf->xpath('//opf:spine/opf:itemref') as $itemref) {
            $ids[] = (string) $itemref['idref'];
        }

        return $ids;
    }

    private function parseDate(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }

        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    // processChapterHtml() and extractCover() are added in Tasks 7 and 8.
    private function processChapterHtml(string $html, ZipArchive $zip, string $chapterPath, Book $book, int $sortOrder): array
    {
        $title = null;
        if (preg_match('/<title>(.*?)<\/title>/is', $html, $m)) {
            $title = trim(strip_tags($m[1])) ?: null;
        }
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $html, $m)) {
            $content = trim($m[1]);
        } else {
            $content = $html;
        }

        return [$content, $title];
    }

    private function extractCover(ZipArchive $zip, SimpleXMLElement $opf, array $manifest, string $opfDir, Book $book): void
    {
        // Implemented fully in Task 8.
    }
}
