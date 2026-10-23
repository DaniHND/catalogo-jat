CREATE TABLE IF NOT EXISTS products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(200) NOT NULL,
  type VARCHAR(100) NOT NULL DEFAULT '',
  description TEXT NULL,
  image VARCHAR(255) NULL,           -- nombre de archivo dentro de /uploads
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_name (name),
  INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO products (code, name, type, description, image) VALUES
('PRD-001', 'Teclado inalámbrico', 'Periféricos', 'Teclado inalámbrico 2.4GHz, layout español.', NULL),
('PRD-002', 'Mouse óptico USB', 'Periféricos', 'Mouse óptico de 1600 DPI.', NULL),
('PRD-003', 'Switch 24 puertos', 'Redes', 'Switch gigabit administrable de 24 puertos.', NULL);
