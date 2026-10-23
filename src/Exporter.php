<?php
declare(strict_types=1);
namespace App;

use Dompdf\Dompdf;
use Dompdf\Options;

final class Exporter
{
    public function __construct(private string $uploadsDir, private string $empresa) {}

    public function xlsx(array $items): never
    {
        $xl = new XlsxWriter([14, 30, 16, 45, 18, 10]);
        $xl->addRow(['Código', 'Nombre', 'Tipo', 'Descripción', 'Imagen', 'Cantidad']);
        foreach ($items as $it) {
            $path = $this->imagePath($it);
            $row = $xl->addRow([(string)$it['code'], $it['name'], $it['type'], (string)$it['description'], null, (int)$it['qty']], $path ? 60 : null);
            if ($path) $xl->addImage($path, 4, $row, 72);
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
                $mime = mime_content_type($path) ?: 'image/png';
                $img = '<img src="data:' . $mime . ';base64,' . base64_encode(file_get_contents($path)) . '" style="max-width:70px;max-height:70px">';
            }
            $rows .= '<tr><td>' . $h($it['code']) . '</td><td>' . $img . '</td><td><b>' . $h($it['name']) . '</b><br>'
                . '<small>' . $h($it['type']) . '</small></td><td>' . nl2br($h($it['description'])) . '</td>'
                . '<td style="text-align:center">' . (int)$it['qty'] . '</td></tr>';
        }
        $html = '<html><head><meta charset="utf-8"><style>
            body{font-family:DejaVu Sans,sans-serif;font-size:11px}
            h1{font-size:16px;margin:0} p{color:#555;margin:2px 0 12px}
            table{width:100%;border-collapse:collapse}
            th{background:#1f2937;color:#fff;padding:6px;text-align:left}
            td{border-bottom:1px solid #ddd;padding:6px;vertical-align:middle}
        </style></head><body>
        <h1>' . $h($this->empresa) . ' — Lista de productos</h1><p>' . date('d/m/Y H:i') . '</p>
        <table><thead><tr><th>Código</th><th>Imagen</th><th>Producto</th><th>Descripción</th><th>Cant.</th></tr></thead>
        <tbody>' . $rows . '</tbody></table></body></html>';

        $opt = new Options();
        $opt->set('isRemoteEnabled', false);
        $pdf = new Dompdf($opt);
        $pdf->loadHtml($html);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        $pdf->stream('lista-' . date('Ymd-His') . '.pdf', ['Attachment' => true]);
        exit;
    }

    private function imagePath(array $it): ?string
    {
        if (empty($it['image'])) return null;
        $p = $this->uploadsDir . '/' . basename($it['image']);
        return is_file($p) ? $p : null;
    }
}
