<?php

namespace Tests\Support;

use ZipArchive;

class EpubFixtureBuilder
{
    /**
     * @param array<int, array{title: string, body: string, images?: array<string, string>}> $chapters
     *   Each chapter's 'images' maps a relative src (e.g. "images/fig1.jpg") to raw image bytes.
     * @param array{ext: string, bytes: string}|null $cover
     */
    public static function build(
        string $bookTitle,
        string $author,
        array $chapters,
        ?array $cover = null,
        ?string $description = null,
        string $language = 'en',
    ): string {
        $path = tempnam(sys_get_temp_dir(), 'epub_fixture_') . '.epub';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);

        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->addFromString('META-INF/container.xml', <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
  <rootfiles>
    <rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);

        $manifestItems = [];
        $spineItems = [];

        foreach ($chapters as $i => $chapter) {
            $n = $i + 1;
            $chapterHtml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
                . "<html xmlns=\"http://www.w3.org/1999/xhtml\">\n"
                . "<head><title>{$chapter['title']}</title></head>\n"
                . "<body>{$chapter['body']}</body>\n"
                . "</html>";
            $zip->addFromString("OEBPS/chapter{$n}.xhtml", $chapterHtml);
            $manifestItems[] = "<item id=\"chap{$n}\" href=\"chapter{$n}.xhtml\" media-type=\"application/xhtml+xml\"/>";
            $spineItems[] = "<itemref idref=\"chap{$n}\"/>";

            foreach ($chapter['images'] ?? [] as $relativeSrc => $bytes) {
                $zip->addFromString("OEBPS/{$relativeSrc}", $bytes);
                $imgId = 'img_' . preg_replace('/[^a-zA-Z0-9]/', '_', $relativeSrc);
                $ext = strtolower(pathinfo($relativeSrc, PATHINFO_EXTENSION));
                $manifestItems[] = "<item id=\"{$imgId}\" href=\"{$relativeSrc}\" media-type=\"image/{$ext}\"/>";
            }
        }

        $coverMeta = '';
        if ($cover) {
            $zip->addFromString("OEBPS/cover.{$cover['ext']}", $cover['bytes']);
            $manifestItems[] = "<item id=\"cover-image\" href=\"cover.{$cover['ext']}\" media-type=\"image/{$cover['ext']}\" properties=\"cover-image\"/>";
            $coverMeta = '<meta name="cover" content="cover-image"/>';
        }

        $manifestXml = implode("\n    ", $manifestItems);
        $spineXml = implode("\n    ", $spineItems);
        $descriptionTag = $description ? "<dc:description>{$description}</dc:description>" : '';

        $opf = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf" version="2.0" unique-identifier="bookid">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:title>{$bookTitle}</dc:title>
    <dc:creator>{$author}</dc:creator>
    <dc:language>{$language}</dc:language>
    {$descriptionTag}
    {$coverMeta}
  </metadata>
  <manifest>
    {$manifestXml}
  </manifest>
  <spine>
    {$spineXml}
  </spine>
</package>
XML;

        $zip->addFromString('OEBPS/content.opf', $opf);
        $zip->close();

        return $path;
    }
}
