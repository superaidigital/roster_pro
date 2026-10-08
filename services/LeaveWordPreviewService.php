<?php
/**
 * Safe, server-side DOCX content preview for the Leave Template Designer.
 *
 * This is a readable CONTENT preview, not pixel-perfect Word pagination.
 * Never serve uploaded DOCX as HTML and never embed unescaped OOXML.
 */
final class LeaveWordPreviewService
{
    private const MAX_XML_BYTES = 3145728;
    private const MAX_BLOCKS = 220;
    private const MAX_CELLS = 800;
    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    public static function preview(string $storedPath): array
    {
        if (!class_exists(ZipArchive::class) || !class_exists(DOMDocument::class)) {
            throw new RuntimeException('PHP ต้องเปิดส่วนขยาย zip และ dom/xml');
        }
        $root = realpath(dirname(__DIR__) . '/storage/leave_templates');
        $path = realpath(dirname(__DIR__) . '/' . ltrim(str_replace('\\', '/', $storedPath), '/'));
        if (!$root || !$path || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
            throw new RuntimeException('ไม่พบไฟล์ Word ภายในพื้นที่จัดเก็บแบบฟอร์ม');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('เปิดเอกสาร DOCX ไม่สำเร็จ');
        }
        try {
            $stat = $zip->statName('word/document.xml');
            if (!is_array($stat) || (int)($stat['size'] ?? 0) <= 0
                || (int)$stat['size'] > self::MAX_XML_BYTES) {
                throw new RuntimeException('เนื้อหา Word มีขนาดเกินกว่าที่รองรับการแสดงตัวอย่าง');
            }
            $xml = $zip->getFromName('word/document.xml');
        } finally {
            $zip->close();
        }

        if (!is_string($xml) || strlen($xml) > self::MAX_XML_BYTES) {
            throw new RuntimeException('ไม่สามารถอ่านข้อมูล Word ได้');
        }
        $dom = new DOMDocument();
        // Avoid network access to external resources and expansion of untrusted entities.
        $prev = libxml_use_internal_errors(true);
        try {
            if (!$dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
                throw new RuntimeException('ไฟล์ Word มีโครงสร้าง XML ไม่ถูกต้อง');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::WORD_NS);
        $bodies = $xpath->query('/w:document/w:body');
        if (!$bodies || !$bodies->length) {
            throw new RuntimeException('ไม่พบเนื้อหาเอกสาร Word');
        }

        $blocks = [];
        $cellCount = 0;
        foreach ($bodies->item(0)->childNodes as $element) {
            if (!$element instanceof DOMElement || $element->namespaceURI !== self::WORD_NS) continue;
            if (count($blocks) >= self::MAX_BLOCKS) break;
            if ($element->localName === 'p') {
                $text = self::paragraphText($xpath, $element);
                if ($text === '') continue;
                $styleNodes = $xpath->query('./w:pPr/w:pStyle', $element);
                $style = $styleNodes && $styleNodes->length
                    ? (string)$styleNodes->item(0)->getAttributeNS(self::WORD_NS, 'val') : '';
                $kind = preg_match('/^(title|heading[1-6])$/i', $style) ? 'heading' : 'paragraph';
                $blocks[] = ['kind' => $kind, 'text' => $text];
            } elseif ($element->localName === 'tbl') {
                $rows = [];
                foreach ($xpath->query('./w:tr', $element) as $row) {
                    if (count($rows) >= 40 || $cellCount >= self::MAX_CELLS) break;
                    $cells = [];
                    foreach ($xpath->query('./w:tc', $row) as $cell) {
                        if (count($cells) >= 20 || $cellCount++ >= self::MAX_CELLS) break;
                        $parts = [];
                        foreach ($xpath->query('./w:p', $cell) as $paragraph) {
                            $parts[] = self::paragraphText($xpath, $paragraph);
                        }
                        $cells[] = implode("\n", array_filter($parts, 'strlen'));
                    }
                    if ($cells) $rows[] = $cells;
                }
                if ($rows) $blocks[] = ['kind' => 'table', 'rows' => $rows];
            }
        }
        return [
            'blocks' => $blocks,
            'truncated' => count($blocks) >= self::MAX_BLOCKS || $cellCount >= self::MAX_CELLS,
            'notes' => 'ตัวอย่างแสดงข้อความและตารางจาก DOCX โดยไม่จำลองการจัดหน้าหรือรูปภาพทั้งหมด',
        ];
    }

    private static function paragraphText(DOMXPath $xpath, DOMElement $paragraph): string
    {
        $out = '';
        // Collect text in document order; never interpret text as executable markup.
        foreach ($xpath->query('.//w:t | .//w:tab | .//w:br | .//w:cr', $paragraph) as $node) {
            if ($node->localName === 't') $out .= $node->textContent;
            elseif ($node->localName === 'tab') $out .= "\t";
            else $out .= "\n";
            if (mb_strlen($out, 'UTF-8') > 10000) {
                return mb_substr($out, 0, 10000, 'UTF-8') . '…';
            }
        }
        return trim($out);
    }
}
