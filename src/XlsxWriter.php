<?php
declare(strict_types=1);
namespace App;

use RuntimeException;
use ZipArchive;

/**
 * Generador XLSX mínimo (una hoja, texto/números, imágenes ancladas a celdas).
 * Reemplaza a PhpSpreadsheet, cuyas dependencias no se pueden instalar en este equipo.
 */
final class XlsxWriter
{
    private array $rows = [];      // [ [valor, ...], ... ]
    private array $heights = [];   // fila (1-based) => alto en puntos
    private array $images = [];    // [ ['path','col','row','w','h'], ... ] col/row 0-based

    public function __construct(private array $colWidths = []) {}

    public function addRow(array $cells, ?float $height = null): int
    {
        $this->rows[] = array_values($cells);
        $n = count($this->rows);
        if ($height) $this->heights[$n] = $height;
        return $n;
    }

    public function addImage(string $path, int $col, int $row, int $maxPx): void
    {
        [$w, $h] = getimagesize($path) ?: [$maxPx, $maxPx];
        $scale = min(1, $maxPx / max($w, $h));
        $this->images[] = [$path, $col, $row - 1, (int)($w * $scale), (int)($h * $scale)];
    }

    public function save(string $file): void
    {
        $zip = new ZipArchive();
        if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo XLSX');
        }
        $hasImg = (bool)$this->images;
        $exts = [];
        foreach ($this->images as $i => [$path]) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'png' ? 'png' : 'jpeg';
            $exts[$ext] = true;
            $zip->addFile($path, 'xl/media/image' . ($i + 1) . '.' . $ext);
        }

        $defaults = '';
        foreach (array_keys($exts) as $e) $defaults .= '<Default Extension="' . $e . '" ContentType="image/' . $e . '"/>';
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>' . $defaults
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . ($hasImg ? '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>' : '')
            . '</Types>');
        $zip->addFromString('_rels/.rels', $this->rels(['officeDocument' => 'xl/workbook.xml']));
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Lista" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->rels(['worksheet' => 'worksheets/sheet1.xml', 'styles' => 'styles.xml']));
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1F2937"/></patternFill></fill></fills>'
            . '<borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs>'
            . '<cellXfs count="2"><xf applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '<xf fontId="1" fillId="2" applyFont="1" applyFill="1"/></cellXfs></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet($hasImg));
        if ($hasImg) {
            $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', $this->rels(['drawing' => '../drawings/drawing1.xml']));
            $zip->addFromString('xl/drawings/drawing1.xml', $this->drawing());
            $imgRels = [];
            foreach ($this->images as $i => [$path]) {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'png' ? 'png' : 'jpeg';
                $imgRels[] = ['image', '../media/image' . ($i + 1) . '.' . $ext];
            }
            $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels', $this->rels($imgRels));
        }
        $zip->close();
    }

    private function sheet(bool $hasImg): string
    {
        $cols = '';
        foreach ($this->colWidths as $i => $w) $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
        $data = '';
        foreach ($this->rows as $r => $cells) {
            $n = $r + 1;
            $ht = isset($this->heights[$n]) ? ' ht="' . $this->heights[$n] . '" customHeight="1"' : '';
            $data .= '<row r="' . $n . '"' . $ht . '>';
            foreach ($cells as $c => $v) {
                $ref = chr(65 + $c) . $n;
                $s = $n === 1 ? '1' : '0';
                if ($v === null || $v === '') {
                    $data .= '<c r="' . $ref . '" s="' . $s . '"/>';
                } elseif (is_int($v) || is_float($v)) {
                    $data .= '<c r="' . $ref . '" s="' . $s . '"><v>' . $v . '</v></c>';
                } else {
                    $data .= '<c r="' . $ref . '" s="' . $s . '" t="inlineStr"><is><t xml:space="preserve">' . $this->esc((string)$v) . '</t></is></c>';
                }
            }
            $data .= '</row>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . ($cols ? "<cols>$cols</cols>" : '') . "<sheetData>$data</sheetData>"
            . ($hasImg ? '<drawing r:id="rId1"/>' : '') . '</worksheet>';
    }

    private function drawing(): string
    {
        $emu = 9525; // EMU por píxel
        $x = '';
        foreach ($this->images as $i => [, $col, $row, $w, $h]) {
            $id = $i + 1;
            $x .= '<xdr:oneCellAnchor><xdr:from><xdr:col>' . $col . '</xdr:col><xdr:colOff>' . (4 * $emu) . '</xdr:colOff>'
                . '<xdr:row>' . $row . '</xdr:row><xdr:rowOff>' . (4 * $emu) . '</xdr:rowOff></xdr:from>'
                . '<xdr:ext cx="' . ($w * $emu) . '" cy="' . ($h * $emu) . '"/>'
                . '<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="' . $id . '" name="Imagen ' . $id . '"/><xdr:cNvPicPr/></xdr:nvPicPr>'
                . '<xdr:blipFill><a:blip r:embed="rId' . $id . '"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
                . '<xdr:spPr><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic><xdr:clientData/></xdr:oneCellAnchor>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . $x . '</xdr:wsDr>';
    }

    /** @param array<string,string>|list<array{0:string,1:string}> $targets */
    private function rels(array $targets): string
    {
        $x = '';
        $i = 1;
        foreach ($targets as $type => $target) {
            if (is_array($target)) [$type, $target] = $target;
            $x .= '<Relationship Id="rId' . $i++ . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/'
                . $type . '" Target="' . $target . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $x . '</Relationships>';
    }

    private function esc(string $s): string
    {
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
