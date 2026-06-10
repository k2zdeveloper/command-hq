<?php

namespace App\Libraries;

use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\Slide\Background\Color as BgColor;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Bullet;
use PhpOffice\PhpPresentation\Style\Color;
use PhpOffice\Common\Adapter\Zip\PclZipAdapter;

/**
 * PptxService — generates .pptx files from structured slide data.
 *
 * Slide themes:
 *   dark      — navy background, white text, gold accents  (default)
 *   light     — white background, dark text, gold accents
 */
class PptxService
{
    private const THEMES = [
        'dark'  => ['bg' => '1C2B3A', 'title' => 'FFFFFF', 'body' => 'D0D8E0', 'accent' => 'F59E0B'],
        'light' => ['bg' => 'FFFFFF', 'title' => '1C2B3A', 'body' => '374151', 'accent' => 'D97706'],
    ];

    // Slide canvas in pixels at 96 dpi  (standard 10" × 7.5")
    private const W = 960;
    private const H = 720;

    /**
     * Generate a .pptx file and return its full filesystem path (in sys_get_temp_dir()).
     *
     * @param  string $presentationTitle  Document title (shown in Office properties)
     * @param  array  $slides             Each element: ['title'=>…, 'subtitle'=>…, 'content'=>…, 'type'=>…]
     * @param  string $theme              'dark' or 'light'
     * @return string                     Absolute path to the generated .pptx temp file
     */
    public function generate(string $presentationTitle, array $slides, string $theme = 'dark'): string
    {
        $colors = self::THEMES[$theme] ?? self::THEMES['dark'];

        $prs = new PhpPresentation();
        $prs->getDocumentProperties()->setTitle($presentationTitle);

        // Remove the blank slide that PhpPresentation creates automatically
        $prs->removeSlideByIndex(0);

        foreach ($slides as $slideData) {
            $prs->createSlide();
            $prs->setActiveSlideIndex($prs->getSlideCount() - 1);
            $slide = $prs->getActiveSlide();
            $this->buildSlide($slide, $slideData, $colors);
        }

        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pptx_') . '.pptx';
        $writer = IOFactory::createWriter($prs, 'PowerPoint2007');
        // PclZip is pure-PHP and avoids the ext-zip / ZipArchive requirement.
        // Suppress its PHP 8 non-numeric warnings so they don't corrupt SSE output.
        $writer->setZipAdapter(new PclZipAdapter());
        $prev = error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
        $writer->save($path);
        error_reporting($prev);

        return $path;
    }

    private function buildSlide($slide, array $data, array $colors): void
    {
        // Background
        $bg = new BgColor();
        $bg->setColor(new Color('FF' . $colors['bg']));
        $slide->setBackground($bg);

        $type = strtolower($data['type'] ?? 'content');

        if ($type === 'title' || $type === 'cover') {
            $this->buildTitleSlide($slide, $data, $colors);
        } else {
            $this->buildContentSlide($slide, $data, $colors);
        }
    }

    private function buildTitleSlide($slide, array $data, array $colors): void
    {
        $pad = 55;

        // Main title (centered, large)
        $title = $slide->createRichTextShape();
        $title->setWidth(self::W - $pad * 2)->setHeight(220)->setOffsetX($pad)->setOffsetY(180);
        $title->getActiveParagraph()->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_BOTTOM);
        $run = $title->createTextRun($data['title'] ?? '');
        $run->getFont()->setBold(true)->setSize(40)->setColor(new Color('FF' . $colors['title']));

        // Subtitle
        if (!empty($data['subtitle'])) {
            $sub = $slide->createRichTextShape();
            $sub->setWidth(self::W - $pad * 2)->setHeight(80)->setOffsetX($pad)->setOffsetY(410);
            $sub->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $subRun = $sub->createTextRun($data['subtitle']);
            $subRun->getFont()->setSize(22)->setColor(new Color('FF' . $colors['accent']));
        }

        // Thin divider (visually implied by text layout — no line drawing in this version)
        if (!empty($data['content'])) {
            $note = $slide->createRichTextShape();
            $note->setWidth(self::W - $pad * 2)->setHeight(60)->setOffsetX($pad)->setOffsetY(510);
            $note->getActiveParagraph()->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $noteRun = $note->createTextRun($data['content']);
            $noteRun->getFont()->setSize(14)->setColor(new Color('FF' . $colors['body']));
        }
    }

    private function buildContentSlide($slide, array $data, array $colors): void
    {
        $pad = 55;

        // Title
        $title = $slide->createRichTextShape();
        $title->setWidth(self::W - $pad * 2)->setHeight(70)->setOffsetX($pad)->setOffsetY(30);
        $run = $title->createTextRun($data['title'] ?? '');
        $run->getFont()->setBold(true)->setSize(30)->setColor(new Color('FF' . $colors['title']));

        // Accent underline (thin separator below title — simulated as a text shape with underline)
        $sep = $slide->createRichTextShape();
        $sep->setWidth(self::W - $pad * 2)->setHeight(4)->setOffsetX($pad)->setOffsetY(105);
        $sepRun = $sep->createTextRun('');
        $sepRun->getFont()->setColor(new Color('FF' . $colors['accent']));

        // Content bullets
        if (!empty($data['content'])) {
            $box = $slide->createRichTextShape();
            $box->setWidth(self::W - $pad * 2)->setHeight(560)->setOffsetX($pad)->setOffsetY(120);

            $lines = array_values(array_filter(
                array_map('trim', explode("\n", $data['content'])),
                fn($l) => $l !== ''
            ));

            foreach ($lines as $i => $line) {
                $line = ltrim($line, "•·-–—* \t");
                if ($line === '') continue;

                if ($i > 0) {
                    $box->createParagraph();
                }
                $para = $box->getActiveParagraph();
                $para->getBulletStyle()
                    ->setBulletType(Bullet::TYPE_BULLET)
                    ->setBulletChar('▸')
                    ->setBulletColor(new Color('FF' . $colors['accent']));
                $para->getAlignment()
                    ->setMarginLeft(30)
                    ->setIndent(-30);

                $run = $box->createTextRun($line);
                $run->getFont()->setSize(18)->setColor(new Color('FF' . $colors['body']));
            }
        }
    }
}
