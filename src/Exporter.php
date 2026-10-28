<?php
declare(strict_types=1);
namespace App;

use Dompdf\Dompdf;
use Dompdf\Options;

final class Exporter
{
    public function __construct(private string $uploadsDir, private string $empresa, private string $logo, private string $detalle = '', private string $comentario = '') {}

    public function xlsx(array $items): never
    {
        $xl = new XlsxWriter([16, 45, 16, 18, 10]);
        $logo = is_file($this->logo);
        $xl->addRow([null, $this->empresa], $logo ? 50 : 24, XlsxWriter::TITLE);
        if ($logo) $xl->addImage($this->logo, 0, 1, 100);
        $xl->addRow([null, 'Lista de productos · ' . date('d/m/Y H:i')]);
        $xl->addRow([null, $this->detalle]);
        $xl->addRow(['Código', 'Nombre', 'Tipo', 'Imagen', 'Cantidad'], null, XlsxWriter::HEADER);
        foreach ($items as $it) {
            $path = $this->imagePath($it);
            $row = $xl->addRow([(string)$it['code'], $it['name'], $it['type'], null, (int)$it['qty']], $path ? 60 : null);
            if ($path) $xl->addImage($path, 3, $row, 72);
        }

        if ($this->comentario !== '') {
            $xl->addRow([null]);
            $xl->addRow(['Comentario', $this->comentario]);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $xl->save($tmp);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="lista-' . date('Ymd-His') . '.xlsx"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        unlink($tmp);
        exit;
    }

    public function pdf(array $items): never
    {
        $h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $rows = '';
        foreach ($items as $it) {
            $img = '';
            if ($path = $this->imagePath($it)) {
                $img = '<img src="' . $this->dataUri($path) . '" style="max-width:70px;max-height:70px">';
            }
            $rows .= '<tr><td>' . $h($it['code']) . '</td><td>' . $img . '</td><td><b>' . $h($it['name']) . '</b><br>'
                . '<small>' . $h($it['type']) . '</small></td>'
                . '<td style="text-align:center">' . (int)$it['qty'] . '</td></tr>';
        }
        $html = '<html><head><meta charset="utf-8"><style>
            body{font-family:DejaVu Sans,sans-serif;font-size:11px}
            .head{width:100%;border-collapse:collapse;border-bottom:3px solid #ed1c24;margin-bottom:14px}
            .head td{border:0;padding:0 0 8px}
            h1{font-size:16px;margin:0} .head p{color:#555;margin:2px 0 0}
            .items{width:100%;border-collapse:collapse}
            th{background:#1f2937;color:#fff;padding:6px;text-align:left}
            .items td{border-bottom:1px solid #ddd;padding:6px;vertical-align:middle}
            .nota{margin-top:14px;padding:8px;border:1px solid #ddd}
        </style></head><body>
        <table class="head"><tr>'
            . (is_file($this->logo) ? '<td style="width:95px"><img src="' . $this->dataUri($this->logo) . '" style="width:85px"></td>' : '')
            . '<td><h1>' . $h($this->empresa) . '</h1><p>Lista de productos · ' . date('d/m/Y H:i') . '</p>'
            . ($this->detalle !== '' ? '<p>' . $h($this->detalle) . '</p>' : '') . '</td></tr></table>
        <table class="items"><thead><tr><th>Código</th><th>Imagen</th><th>Producto</th><th>Cant.</th></tr></thead>
        <tbody>' . $rows . '</tbody></table>'
            . ($this->comentario !== '' ? '<p class="nota"><b>Comentario:</b> ' . nl2br($h($this->comentario)) . '</p>' : '')
            . '</body></html>';

        $opt = new Options();
        $opt->set('isRemoteEnabled', false);
        $pdf = new Dompdf($opt);
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        $pdf->stream('lista-' . date('Ymd-His') . '.pdf', ['Attachment' => true]);
        exit;
    }

    private function dataUri(string $path): string
    {
        return 'data:' . (mime_content_type($path) ?: 'image/png') . ';base64,' . base64_encode(file_get_contents($path));
    }

    private function imagePath(array $it): ?string
    {
        if (empty($it['image'])) return null;
        $p = $this->uploadsDir . '/' . basename($it['image']);
        return is_file($p) ? $p : null;
    }
}
