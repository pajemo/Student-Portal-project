<?php

declare(strict_types=1);

namespace App\Services;

final class TranscriptPdf
{
    public function build(array $pages): string
    {
        $objects = [];
        $contentObjects = [];
        $pageObjects = [];

        $currentId = 4;

        foreach ($pages as $page) {
            $content = $this->buildContentStream($page['lines'] ?? [], $page['title'] ?? 'Transcript');
            $contentObjectId = $currentId++;
            $pageObjectId = $currentId++;
            $contentObjects[$contentObjectId] = $content;
            $pageObjects[] = $pageObjectId;
            $objects[$pageObjectId] = $this->buildPageObject($contentObjectId);
        }

        $objects[1] = $this->buildCatalogObject();
        $objects[2] = $this->buildPagesObject($pageObjects);
        $objects[3] = $this->buildFontObject();

        foreach ($contentObjects as $id => $content) {
            $objects[$id] = $this->buildStreamObject($content);
        }

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xrefPosition = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= sprintf("%010d 65535 f \n", 0);
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n" . $xrefPosition . "\n%%EOF";

        return $pdf;
    }

    private function buildCatalogObject(): string
    {
        return '<< /Type /Catalog /Pages 2 0 R >>';
    }

    private function buildPagesObject(array $pageObjects): string
    {
        $kids = implode(' ', array_map(static fn (int $id) => $id . ' 0 R', $pageObjects));
        return '<< /Type /Pages /Kids [' . $kids . '] /Count ' . count($pageObjects) . ' >>';
    }

    private function buildFontObject(): string
    {
        return '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    }

    private function buildPageObject(int $contentObjectId): string
    {
        return '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentObjectId . ' 0 R >>';
    }

    private function buildStreamObject(string $content): string
    {
        return '<< /Length ' . strlen($content) . ' >>\nstream\n' . $content . '\nendstream';
    }

    private function buildContentStream(array $lines, string $title): string
    {
        $content = [];
        $content[] = 'BT';
        $content[] = '/F1 18 Tf';
        $content[] = '72 790 Td';
        $content[] = '(' . $this->escape($title) . ') Tj';
        $content[] = '/F1 10 Tf';

        $y = 768;
        foreach ($lines as $line) {
            if ($y < 60) {
                break;
            }
            $content[] = '1 0 0 1 72 ' . $y . ' Tm';
            $content[] = '(' . $this->escape((string) $line) . ') Tj';
            $y -= 14;
        }

        $content[] = 'ET';

        return implode("\n", $content);
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
