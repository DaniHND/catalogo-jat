<?php
declare(strict_types=1);
namespace App;

/** Lista del usuario guardada en sesión: [product_id => cantidad] */
final class ListStore
{
    public function __construct(private ProductRepository $repo) {}

    public function set(int $id, int $qty): void
    {
        if ($qty < 1) { $this->remove($id); return; }
        if (!$this->repo->exists($id)) throw new \InvalidArgumentException('Producto no existe');
        $_SESSION['list'][$id] = min($qty, 999999);
    }

    public function add(int $id, int $qty): void
    {
        $this->set($id, ($_SESSION['list'][$id] ?? 0) + max(1, $qty));
    }

    public function remove(int $id): void { unset($_SESSION['list'][$id]); }

    public function clear(): void { $_SESSION['list'] = []; }

    public function items(): array
    {
        $raw = $_SESSION['list'] ?? [];
        $products = $this->repo->findMany(array_keys($raw));
        $items = [];
        foreach ($raw as $id => $qty) {
            if (isset($products[$id])) $items[] = $products[$id] + ['qty' => $qty];
        }
        return $items;
    }
}
