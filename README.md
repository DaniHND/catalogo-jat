# Catálogo de productos JAT

Buscador de productos por código, nombre o tipo, con imagen y descripción. Permite armar una lista con cantidades y exportarla a **Excel** o **PDF**.

Incluye los 463 productos del catálogo JAT 2023 (`sql/productos_catalogo.sql`) y sus imágenes (`uploads/`).

## Requisitos

- PHP 8.1 o superior con las extensiones `pdo_mysql`, `zip`, `gd` y `mbstring`
- MySQL 8 o MariaDB
- Composer

## Instalación

```bash
git clone https://github.com/DaniHND/catalogo-jat.git
cd catalogo-jat
composer install --no-dev
cp config.example.php config.php   # luego edita los datos de la base de datos
```

Crea la base de datos e importa los datos (o usa phpMyAdmin → Importar, en ese orden):

```bash
mysql -u root -p -e "CREATE DATABASE catalogo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p --default-character-set=utf8mb4 catalogo < sql/schema.sql
mysql -u root -p --default-character-set=utf8mb4 catalogo < sql/productos_catalogo.sql
```

Abre `index.php` en el navegador (por ejemplo `http://localhost/catalogo-jat/`).

> En un hosting sin Composer, ejecuta `composer install` en tu equipo y sube también la carpeta `vendor/`.

## Volver a extraer el catálogo

Si cambia el PDF, regenera el SQL y las imágenes (requiere Python con PyMuPDF):

```bash
pip install pymupdf
python scripts/extract_catalog.py "CATALOGO JAT 2023-13Mb 230623.pdf"
```

## Versión demo (GitHub Pages)

La carpeta `docs/` contiene una versión estática del catálogo que no necesita PHP ni MySQL: los productos están en `docs/data/products.js`, la lista se guarda en el navegador y el Excel/PDF se generan en el propio navegador.

Para publicarla: *Settings → Pages → Build and deployment → Deploy from a branch → `main` / `/docs` → Save*. Queda en `https://danihnd.github.io/catalogo-jat/`.

Si cambian los productos, regenera `docs/data/products.js` y `docs/img/` desde la base de datos.
