<?php

namespace App\Libraries;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * DocxService — generates .docx files from markdown-like content.
 *
 * Supported syntax in the content field:
 *   # Title          → Heading 1
 *   ## Section       → Heading 2
 *   ### Sub-section  → Heading 3
 *   • bullet / - bullet / * bullet  → bulleted list item
 *   **bold text**    → bold inline
 *   blank line       → paragraph break
 *   ---              → page break
 *   anything else    → body paragraph
 */
class DocxService
{
    private const ACCENT = 'F59E0B';   // gold
    private const DARK   = '1C2B3A';   // navy
    private const BODY   = '374151';   // dark grey

    public function generate(string $title, string $markdownContent): string
    {
        // PHPWord defaults to writeRaw() (no escaping), which breaks on & < > in content.
        // Enable escaping so special characters are properly written as XML entities.
        Settings::setOutputEscapingEnabled(true);

        $word = new PhpWord();
        $word->getSettings()->setThemeFontLang(new \PhpOffice\PhpWord\Style\Language('en-US'));

        // ── Font defaults ───────────────────────────────────────────────
        $word->setDefaultFontName('Calibri');
        $word->setDefaultFontSize(11);

        // ── Heading styles ──────────────────────────────────────────────
        $word->addTitleStyle(1, [
            'name'      => 'Calibri',
            'size'      => 24,
            'bold'      => true,
            'color'     => self::DARK,
        ], ['spaceAfter' => 160, 'spaceBefore' => 240]);

        $word->addTitleStyle(2, [
            'name'      => 'Calibri',
            'size'      => 16,
            'bold'      => true,
            'color'     => self::ACCENT,
        ], ['spaceAfter' => 120, 'spaceBefore' => 200]);

        $word->addTitleStyle(3, [
            'name'      => 'Calibri',
            'size'      => 13,
            'bold'      => true,
            'color'     => self::BODY,
        ], ['spaceAfter' => 80, 'spaceBefore' => 160]);

        // ── Named styles ────────────────────────────────────────────────
        $bodyFont = ['name' => 'Calibri', 'size' => 11, 'color' => self::BODY];
        $bodyPara = ['spaceAfter' => 120, 'lineHeight' => 1.15];
        $listPara = ['spaceAfter' => 60, 'lineHeight' => 1.15, 'indentation' => ['left' => 360]];

        // ── Section ─────────────────────────────────────────────────────
        $section = $word->addSection([
            'marginTop'    => 1440,
            'marginBottom' => 1440,
            'marginLeft'   => 1440,
            'marginRight'  => 1440,
        ]);

        // Document title block
        $section->addTitle($title, 1);
        $section->addTextBreak(1);

        // ── Parse and render content ─────────────────────────────────────
        $lines = explode("\n", str_replace("\r\n", "\n", $markdownContent));
        $i     = 0;
        $count = count($lines);

        while ($i < $count) {
            $line = $lines[$i];

            // Page break
            if (trim($line) === '---') {
                $section->addPageBreak();
                $i++;
                continue;
            }

            // Headings
            if (preg_match('/^(#{1,3})\s+(.+)/', $line, $m)) {
                $level = strlen($m[1]);
                $section->addTitle(trim($m[2]), $level);
                $i++;
                continue;
            }

            // Bullet list items (•, -, *, +)
            if (preg_match('/^[\s]*[•\-\*\+]\s+(.+)/', $line, $m)) {
                $section->addListItem(
                    trim($m[1]),
                    0,
                    $bodyFont,
                    ['listType' => \PhpOffice\PhpWord\Style\ListItem::TYPE_BULLET_FILLED],
                    $listPara
                );
                $i++;
                continue;
            }

            // Numbered list items (1. 2. etc.)
            if (preg_match('/^[\s]*\d+\.\s+(.+)/', $line, $m)) {
                $section->addListItem(
                    trim($m[1]),
                    0,
                    $bodyFont,
                    ['listType' => \PhpOffice\PhpWord\Style\ListItem::TYPE_NUMBER],
                    $listPara
                );
                $i++;
                continue;
            }

            // Empty line → soft break (skip extra blanks)
            if (trim($line) === '') {
                $i++;
                continue;
            }

            // Normal paragraph — parse inline **bold**
            $this->addParagraph($section, $line, $bodyFont, $bodyPara);
            $i++;
        }

        // ── Save ─────────────────────────────────────────────────────────
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('docx_') . '.docx';

        // Prefer native ZipArchive (produces Word-compatible files).
        // Fall back to PclZip only when ext-zip is not loaded.
        Settings::setZipClass(extension_loaded('zip') ? Settings::ZIPARCHIVE : Settings::PCLZIP);
        $prev = error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
        $writer = IOFactory::createWriter($word, 'Word2007');
        $writer->save($path);
        error_reporting($prev);

        return $path;
    }

    /**
     * Add a paragraph that supports inline **bold** and *italic* spans.
     */
    private function addParagraph($section, string $text, array $baseFont, array $paraStyle): void
    {
        // If no inline formatting, use simple addText
        if (!preg_match('/\*\*|\*[^*]/', $text)) {
            $section->addText($text, $baseFont, $paraStyle);
            return;
        }

        $run = $section->addTextRun($paraStyle);

        // Split on **bold** and *italic* markers
        $parts = preg_split('/(\*\*[^*]+\*\*|\*[^*]+\*)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $part) {
            if (preg_match('/^\*\*(.+)\*\*$/', $part, $m)) {
                $run->addText($m[1], array_merge($baseFont, ['bold' => true]));
            } elseif (preg_match('/^\*([^*]+)\*$/', $part, $m)) {
                $run->addText($m[1], array_merge($baseFont, ['italic' => true]));
            } elseif ($part !== '') {
                $run->addText($part, $baseFont);
            }
        }
    }
}
