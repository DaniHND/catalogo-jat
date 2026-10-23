<?php
declare(strict_types=1);
namespace App;

use PDO;

final class ProductRepository
{
    public function __construct(private PDO $db) {}

    public function search(string $q, string $type, int $limit = 50): array
    {
        $sql = 'SELECT id, code, name, type, description, image FROM products WHERE 1=1';
        $params = [];
        if ($q !== '') {
            $sql .= ' AND (code LIKE :q1 OR name LIKE :q2)';
            $params[':q1'] = $params[':q2'] = '%' . $q . '%';
        }
        if ($type !== '') {
            $sql .= ' AND type = :type';
            $params[':type'] = $type;
        }
        $sql .= ' ORDER BY name LIMIT ' . max(1, min($limit, 200));
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return array_map([$this, 'withImageUrl'], $st->fetchAll());
    }

    public function types(): array
    {
        return $this->db->query("SELECT DISTINCT type FROM products WHERE type <> '' ORDER BY type")
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    public function findMany(array $ids): array
    {
        if (!$ids) return [];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->db->prepare("SELECT id, code, name, type, description, image FROM products WHERE id IN ($ph)");
        $st->execute(array_values($ids));
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $row = $this->withImageUrl($row);
            $out[$row['id']] = $row;
        }
        return $out;
    }

    public function exists(int $id): bool
    {
        $st = $this->db->prepare('SELECT 1 FROM products WHERE id = ?');
        $st->execute([$id]);
        return (bool)$st->fetchColumn();
    }

    private function withImageUrl(array $p): array
    {
        $p['id'] = (int)$p['id'];
        $p['image_url'] = $p['image'] ? 'uploads/' . rawurlencode($p['image']) : null;
        return $p;
    }
}
