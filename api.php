<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\{Database, ProductRepository, ListStore, Exporter};

session_start();

function respond(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $config = require __DIR__ . '/config.php';
    $repo   = new ProductRepository(Database::connect($config['db']));
    $list   = new ListStore($repo);
    $route  = $_GET['r'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
    $body   = json_decode(file_get_contents('php://input') ?: '[]', true) ?? [];

    switch ("$method $route") {
        case 'GET products':
            respond($repo->search(trim($_GET['q'] ?? ''), trim($_GET['type'] ?? '')));
        case 'GET types':
            respond($repo->types());
        case 'GET list':
            respond($list->items());
        case 'POST list/add':
            $list->add((int)($body['product_id'] ?? 0), (int)($body['qty'] ?? 1));
            respond($list->items());
        case 'POST list/update':
            $list->set((int)($body['product_id'] ?? 0), (int)($body['qty'] ?? 1));
            respond($list->items());
        case 'POST list/remove':
            $list->remove((int)($body['product_id'] ?? 0));
            respond($list->items());
        case 'POST list/clear':
            $list->clear();
            respond([]);
        case 'GET export':
            $items = $list->items();
            if (!$items) respond(['error' => 'La lista está vacía'], 400);
            $empresa = mb_substr(trim($_GET['empresa'] ?? ''), 0, 120) ?: $config['empresa'];
            $contacto = mb_substr(trim($_GET['contacto'] ?? ''), 0, 120);
            $detalle = ($contacto !== '' ? "Contacto: $contacto · " : '')
                . "Aparcería Pilgrim's: " . (($_GET['pilgrims'] ?? '') === '1' ? 'Sí' : 'No');
            $ex = new Exporter(__DIR__ . '/uploads', $empresa, __DIR__ . '/assets/logo-jat.png', $detalle);
            ($_GET['format'] ?? '') === 'pdf' ? $ex->pdf($items) : $ex->xlsx($items);
        default:
            respond(['error' => 'Ruta no encontrada'], 404);
    }
} catch (InvalidArgumentException $e) {
    respond(['error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log($e->getMessage());
    respond(['error' => 'Error interno'], 500);
}
