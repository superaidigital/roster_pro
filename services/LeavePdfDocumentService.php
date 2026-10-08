<?php
declare(strict_types=1);

/**
 * Generate a populated, multi-page PDF from an uploaded background PDF and
 * field positions saved by the Visual Leave Template Editor.
 *
 * This service never sends leave/personnel data to an external API.
 * Coordinate units in leave_form_fields are % from the PDF's top-left CropBox.
 */
final class LeavePdfDocumentService
{
    private const MAX_PAGES = 60;
    private const MAX_FIELDS = 120;

    /** Validate mapping coordinates, including page boundary. Useful in QA. */
    public static function boxMm(array $field, float $pageW, float $pageH): array
    {
        if ($pageW <= 0 || $pageH <= 0) {
            throw new InvalidArgumentException('ขนาดหน้ากระดาษไม่ถูกต้อง');
        }
        $v = [];
        foreach (['x','y','width','height'] as $key) {
            if (!isset($field[$key]) || !is_numeric($field[$key]) || !is_finite((float)$field[$key])) {
                throw new InvalidArgumentException('พิกัดฟิลด์ไม่ถูกต้อง: ' . $key);
            }
            $v[$key] = (float)$field[$key];
        }
        if ($v['x'] < 0 || $v['y'] < 0 || $v['width'] < 2 || $v['height'] < 1
            || $v['x'] + $v['width'] > 100.01 || $v['y'] + $v['height'] > 100.01) {
            throw new InvalidArgumentException('ฟิลด์อยู่นอกหน้ากระดาษ');
        }
        return [
            'x' => $pageW * $v['x'] / 100,
            'y' => $pageH * $v['y'] / 100,
            'width' => $pageW * $v['width'] / 100,
            'height' => $pageH * $v['height'] / 100,
        ];
    }

    /** Throws if the optional PDF dependencies and Thai-capable local font are absent. */
    public static function prerequisites(): array
    {
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            throw new RuntimeException('ยังไม่ได้ติดตั้ง Composer packages: ให้รัน composer install');
        }
        require_once $autoload;
        if (!class_exists(\setasign\Fpdi\Tcpdf\Fpdi::class) || !class_exists(\TCPDF_FONTS::class)) {
            throw new RuntimeException('ไม่พบ FPDI/TCPDF กรุณาติดตั้ง Composer dependencies');
        }

        $font = trim((string)(getenv('LEAVE_PDF_FONT_FILE') ?: ''));
        $candidates = array_filter([
            $font,
            'C:/Windows/Fonts/tahoma.ttf',
            '/usr/share/fonts/truetype/noto/NotoSansThai-Regular.ttf',
            '/usr/share/fonts/truetype/thai/Sarabun-Regular.ttf',
        ]);
        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real !== false && is_file($real) && is_readable($real)
                && in_array(strtolower(pathinfo($real, PATHINFO_EXTENSION)), ['ttf','otf'], true)) {
                return ['font' => $real];
            }
        }
        throw new RuntimeException(
            'ไม่พบฟอนต์ภาษาไทย กรุณาตั้งค่า LEAVE_PDF_FONT_FILE เป็นไฟล์ TTF ภาษาไทยที่มีสิทธิ์ใช้งาน'
        );
    }

    /**
     * @param array<array<string,mixed>> $fields rows from leave_form_fields
     * @param array<string,string> $replacements keyed as {{placeholder}}
     */
    public function render(string $source, string $output, array $fields, array $replacements): int
    {
        if (!is_file($source) || !is_readable($source)) {
            throw new RuntimeException('ไม่พบไฟล์ PDF Template');
        }
        if (!$fields || count($fields) > self::MAX_FIELDS) {
            throw new RuntimeException('แบบฟอร์ม PDF ยังไม่มีฟิลด์ หรือฟิลด์มากเกินไป');
        }
        if (filesize($source) > 10 * 1024 * 1024) {
            throw new RuntimeException('PDF Template มีขนาดเกิน 10 MB');
        }
        $config = self::prerequisites();

        // Uses local TTF. No font files are checked into Git or redistributed.
        $fontName = \TCPDF_FONTS::addTTFfont($config['font'], 'TrueTypeUnicode', '', 32);
        if (!is_string($fontName) || $fontName === '') {
            throw new RuntimeException('เตรียมฟอนต์ภาษาไทยสำหรับ PDF ไม่สำเร็จ กรุณาตรวจสิทธิ์โฟลเดอร์ TCPDF fonts');
        }

        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P','mm','A4',true,'UTF-8',false);
        $pdf->SetCreator('Roster Pro');
        $pdf->SetTitle('Leave Document');
        $pdf->SetAuthor('Roster Pro');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0,0,0);
        $pdf->SetAutoPageBreak(false,0);
        $pdf->setFontSubsetting(true);
        $pdf->SetTextColor(15,23,42);
        try {
            $pageCount = $pdf->setSourceFile($source);
            if ($pageCount < 1 || $pageCount > self::MAX_PAGES) {
                throw new RuntimeException('PDF ต้องมีจำนวนหน้า 1 ถึง ' . self::MAX_PAGES);
            }
            $byPage = [];
            foreach ($fields as $field) {
                $page = (int)($field['page_number'] ?? 0);
                $key = (string)($field['field_key'] ?? '');
                if ($page < 1 || $page > $pageCount || !array_key_exists($key, $replacements)) {
                    throw new RuntimeException('ฟิลด์ PDF อ้างอิงหน้าหรือ Placeholder ที่ไม่ถูกต้อง');
                }
                $byPage[$page][] = $field;
            }

            for ($page=1; $page<=$pageCount; $page++) {
                $id = $pdf->importPage($page, '/CropBox');
                $size = $pdf->getTemplateSize($id);
                $pageW=(float)$size['width'];
                $pageH=(float)$size['height'];
                if ($pageW<=0 || $pageH<=0) throw new RuntimeException('ขนาดหน้า PDF ไม่ถูกต้อง');
                $pdf->AddPage($pageW>$pageH?'L':'P',[$pageW,$pageH]);
                $pdf->useImportedPage($id,0,0,$pageW,$pageH);

                foreach ($byPage[$page] ?? [] as $field) {
                    $key=(string)$field['field_key'];
                    // A placeholder indicating a stored signature is NOT a legal signature image.
                    // Do not stamp misleading strings into a signature area.
                    if (in_array($key,['{{employee_signature}}','{{approver_signature}}'],true)) continue;
                    $value = trim((string)$replacements[$key]);
                    if ($value === '') continue;
                    if (!mb_check_encoding($value, 'UTF-8')) {
                        throw new RuntimeException('ข้อความบนเอกสารมีการเข้ารหัสอักขระผิดพลาด');
                    }
                    $box = self::boxMm($field,$pageW,$pageH);
                    $fontPt = isset($field['font_size']) && (float)$field['font_size']>0
                        ? (float)$field['font_size'] : 12.0;
                    $fontPt = min(22.0,max(7.0,$fontPt));
                    $align = strtoupper((string)($field['alignment'] ?? 'LEFT'));
                    $alignment = ['LEFT'=>'L','CENTER'=>'C','RIGHT'=>'R'][$align] ?? 'L';
                    $pdf->SetFont($fontName,'',$fontPt);
                    $pdf->MultiCell(
                        $box['width'], $box['height'], $value,
                        0, $alignment, false, 0,
                        $box['x'], $box['y'], true, 0, false, false,
                        $box['height'], 'M', true
                    );
                }
            }
            $dir = dirname($output);
            if (!is_dir($dir) && !mkdir($dir,0750,true) && !is_dir($dir)) {
                throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์เก็บเอกสาร PDF');
            }
            $pdf->Output($output,'F');
            if (!is_file($output) || filesize($output)<100) {
                throw new RuntimeException('บันทึก PDF ไม่สำเร็จ');
            }
            return $pageCount;
        } catch (Throwable $e) {
            if (is_file($output)) @unlink($output);
            throw $e;
        }
    }
}
