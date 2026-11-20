<?php

namespace Antareja\Support;

/**
 * Penulis file Excel (.xlsx / Office Open XML) minimal tanpa dependensi tambahan.
 * Mendukung beberapa sheet, baris judul tebal, angka sebagai angka, teks sebagai inline string,
 * lebar kolom otomatis, dan freeze baris judul.
 */
class XlsxWriter
{
    /** @var array<string, array<int, array<int, mixed>>> */
    private array $sheets = [];

    /**
     * @param  array<int, string>  $header
     * @param  iterable<array<int, mixed>>  $rows
     */
    public function addSheet(string $name, array $header, iterable $rows): self
    {
        $name = mb_substr(preg_replace('/[\[\]\*\?\/\\\\:]/', ' ', $name), 0, 31);
        $base = $name;
        $i = 2;
        while (isset($this->sheets[$name])) {
            $name = mb_substr($base, 0, 28) . ' ' . $i++;
        }
        $data = [$header];
        foreach ($rows as $r) {
            $data[] = array_values($r);
        }
        $this->sheets[$name] = $data;

        return $this;
    }

    public function save(string $path): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Tidak bisa membuat {$path}");
        }
        $names = array_keys($this->sheets);
        $n = count($names);

        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>';
        for ($i = 1; $i <= $n; $i++) {
            $ct .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $zip->addFromString('[Content_Types].xml', $ct . '</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>');

        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>ANTAREJA NMS</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created></cp:coreProperties>');

        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        foreach ($names as $i => $name) {
            $id = $i + 1;
            $wb .= '<sheet name="' . $this->esc($name) . '" sheetId="' . $id . '" r:id="rId' . $id . '"/>';
            $rels .= '<Relationship Id="rId' . $id . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $id . '.xml"/>';
        }
        $rels .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $zip->addFromString('xl/workbook.xml', $wb . '</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $rels . '</Relationships>');

        // style 0: normal, 1: judul tebal dengan latar abu, 2: angka 2 desimal
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE7E5E4"/></patternFill></fill></fills>'
            . '<borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="3"><xf/><xf fontId="1" fillId="2" applyFont="1" applyFill="1"/><xf numFmtId="4" applyNumberFormat="1"/></cellXfs></styleSheet>');

        foreach ($names as $i => $name) {
            $zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $this->sheetXml($this->sheets[$name]));
        }
        $zip->close();
    }

    private function sheetXml(array $rows): string
    {
        $widths = [];
        $body = '';
        foreach ($rows as $r => $cells) {
            $rowNo = $r + 1;
            $body .= '<row r="' . $rowNo . '">';
            foreach ($cells as $c => $v) {
                $ref = self::col($c) . $rowNo;
                $len = mb_strlen((string) $v);
                $widths[$c] = max($widths[$c] ?? 8, min(60, $len + 2));
                if ($v === null || $v === '') {
                    continue;
                }
                if ($r > 0 && (is_int($v) || is_float($v))) {
                    $style = is_float($v) && floor($v) != $v ? ' s="2"' : '';
                    $body .= '<c r="' . $ref . '"' . $style . '><v>' . $v . '</v></c>';
                } else {
                    $body .= '<c r="' . $ref . '" t="inlineStr"' . ($r === 0 ? ' s="1"' : '') . '><is><t xml:space="preserve">' . $this->esc((string) $v) . '</t></is></c>';
                }
            }
            $body .= '</row>';
        }
        $cols = '';
        foreach ($widths as $c => $w) {
            $cols .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="' . $w . '" customWidth="1"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . ($cols ? '<cols>' . $cols . '</cols>' : '') . '<sheetData>' . $body . '</sheetData></worksheet>';
    }

    public static function col(int $i): string
    {
        $s = '';
        $i++;
        while ($i > 0) {
            $m = ($i - 1) % 26;
            $s = chr(65 + $m) . $s;
            $i = intdiv($i - 1, 26);
        }

        return $s;
    }

    private function esc(string $s): string
    {
        // Buang karakter kontrol yang tidak sah di XML
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s) ?? '';

        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
