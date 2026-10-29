<?php
declare(strict_types=1);
namespace App;

/** Lista del usuario guardada en sesión: [product_id => ['qty' => cantidad, 'price' => precio unitario]] */
final class ListStore
{
    public function __construct(private ProductRepository $repo) {}

    /** $price null conserva el precio actual. */
    public function set(int $id, int $qty, ?float $price = null): void
    {
        if ($qty < 1) { $this->remove($id); return; }
        if (!$this->repo->exists($id)) throw new \InvalidArgumentException('Producto no existe');
        $_SESSION['list'][$id] = [
            'qty' => min($qty, 999999),
            'price' => $price === null ? $this->entry($id)['price'] : min(max($price, 0), 999999999),
        ];
    }

    /** Si el producto ya está en la lista, reemplaza la cantidad (y el precio si se indica) en vez de sumarla. */
    public function add(int $id, int $qty, ?float $price = null): void
    {
        $this->set($id, $qty >= 1 ? $qty : max(1, $this->entry($id)['qty']), $price);
    }

    /** Compatible con listas antiguas guardadas como [id => cantidad]. */
    private function entry(int $id): array
    {
        $e = $_SESSION['list'][$id] ?? null;
        if (is_array($e)) return ['qty' => (int)$e['qty'], 'price' => (float)$e['price']];
        return ['qty' => (int)$e, 'price' => 0.0];
    }

    public function remove(int $id): void { unset($_SESSION['list'][$id]); }

    public function clear(): void { $_SESSION['list'] = []; }

    public function items(): array
    {
        $raw = $_SESSION['list'] ?? [];
        $products = $this->repo->findMany(array_keys($raw));
        $items = [];
        foreach (array_keys($raw) as $id) {
            if (isset($products[$id])) $items[] = $products[$id] + $this->entry($id);
        }
        return $items;
    }
}
