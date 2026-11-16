-- ============================================================
--  PixelStore - E-commerce de games e periféricos
--  Script de criação completa do banco + carga inicial de teste
--  Compatível com MySQL 5.7+/8 e MariaDB 10.4+ (XAMPP)
--
--  Como usar:  phpMyAdmin > Importar > schema.sql
--         ou:  mysql -u root -p < database/schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS pixelstore
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE pixelstore;

SET NAMES utf8mb4;

-- Recriação limpa (ordem inversa das dependências)
DROP TABLE IF EXISTS pedido_itens;
DROP TABLE IF EXISTS pedidos;
DROP TABLE IF EXISTS produtos;
DROP TABLE IF EXISTS categorias;
DROP TABLE IF EXISTS usuarios;

-- ------------------------------------------------------------
-- Autenticação e perfis
-- ------------------------------------------------------------
CREATE TABLE usuarios (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome        VARCHAR(100) NOT NULL,
  email       VARCHAR(150) NOT NULL,
  senha_hash  VARCHAR(255) NOT NULL,                      -- gerado por password_hash()
  perfil      ENUM('cliente','admin') NOT NULL DEFAULT 'cliente',
  criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Catálogo
-- ------------------------------------------------------------
CREATE TABLE categorias (
  id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome  VARCHAR(60) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_categorias_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE produtos (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  categoria_id  INT UNSIGNED NOT NULL,
  nome          VARCHAR(150) NOT NULL,
  descricao     TEXT NOT NULL,
  preco         DECIMAL(10,2) NOT NULL,                   -- valor monetário
  estoque       INT UNSIGNED NOT NULL DEFAULT 0,          -- contador inteiro
  imagem        VARCHAR(255) NULL,                        -- nome do arquivo em assets/img/produtos
  ativo         TINYINT(1) NOT NULL DEFAULT 1,            -- 0 = desativado (some da vitrine)
  criado_em     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_produtos_categoria (categoria_id),
  KEY idx_produtos_nome (nome),
  CONSTRAINT fk_produtos_categoria
    FOREIGN KEY (categoria_id) REFERENCES categorias (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Transações de venda
-- ------------------------------------------------------------
CREATE TABLE pedidos (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id       INT UNSIGNED NOT NULL,
  data_pedido      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  forma_pagamento  ENUM('pix','boleto','cartao') NOT NULL,
  status           ENUM('pago','cancelado') NOT NULL DEFAULT 'pago',
  total            DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_pedidos_usuario (usuario_id),
  CONSTRAINT fk_pedidos_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela associativa: resolve o N:N entre pedidos e produtos.
-- preco_unitario congela o preço praticado no momento da compra.
CREATE TABLE pedido_itens (
  pedido_id       INT UNSIGNED NOT NULL,
  produto_id      INT UNSIGNED NOT NULL,
  quantidade      INT UNSIGNED NOT NULL,
  preco_unitario  DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (pedido_id, produto_id),
  KEY idx_itens_produto (produto_id),
  CONSTRAINT fk_itens_pedido
    FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_itens_produto
    FOREIGN KEY (produto_id) REFERENCES produtos (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  CARGA INICIAL DE TESTE
-- ============================================================

-- Senhas (armazenadas apenas como hash bcrypt via password_hash):
--   admin@pixelstore.com    ->  admin123
--   cliente@pixelstore.com  ->  cliente123
INSERT INTO usuarios (id, nome, email, senha_hash, perfil) VALUES
  (1, 'Administrador', 'admin@pixelstore.com',   '$2y$10$PQxovrkgJx98.OsbwUGDOuytLay63tZpO4BZWJAZ7jPkBIchVOgvO', 'admin'),
  (2, 'Cliente Teste', 'cliente@pixelstore.com', '$2y$10$7cMRdVYKYJzS7AtQxgPJ5OyhqsK2XTI9UYgVKQ4dq1M6nwUb4KDdi', 'cliente');

INSERT INTO categorias (id, nome) VALUES
  (1, 'Consoles'),
  (2, 'Jogos'),
  (3, 'Periféricos'),
  (4, 'Acessórios');

INSERT INTO produtos (id, categoria_id, nome, descricao, preco, estoque, imagem, ativo) VALUES
  (1,  1, 'Console Orion X 1TB',
      'Console de nova geração com SSD de 1TB, suporte a 4K a 120 fps e ray tracing. Acompanha um controle sem fio e cabo HDMI 2.1.',
      3899.90, 8,  'console-orion-x.svg', 1),
  (2,  1, 'Console Portátil Nimbus',
      'Portátil com tela OLED de 7 polegadas, 64GB de armazenamento expansível e bateria para até 9 horas de jogo.',
      2299.00, 12, 'console-nimbus.svg', 1),
  (3,  1, 'Console Retro Arcade Mini',
      'Mini console com 200 clássicos na memória, saída HDMI e dois controles com fio. Diversão retrô plug and play.',
      399.90,  0,  'console-retro-mini.svg', 1),
  (4,  2, 'Jogo Reinos de Aetheria',
      'RPG de mundo aberto com mais de 80 horas de campanha, sistema de classes livre e modo cooperativo online.',
      249.90,  30, 'jogo-aetheria.svg', 1),
  (5,  2, 'Jogo Velocity Rush 3',
      'Corrida arcade com 60 pistas, mais de 150 carros licenciados fictícios e multiplayer em tela dividida.',
      199.90,  25, 'jogo-velocity-rush.svg', 1),
  (6,  2, 'Jogo Sombras de Kyoto',
      'Aventura de ação e furtividade no Japão feudal, com combate preciso e narrativa cinematográfica.',
      299.90,  3,  'jogo-sombras-kyoto.svg', 1),
  (7,  3, 'Teclado Mecânico Falcon TKL',
      'Teclado mecânico tenkeyless com switches lineares, iluminação RGB por tecla e cabo USB-C removível. Layout ABNT2.',
      349.90,  20, 'teclado-falcon.svg', 1),
  (8,  3, 'Mouse Gamer Viper 26K',
      'Mouse ultraleve de 58g com sensor óptico de 26.000 DPI, 6 botões programáveis e cabo flexível.',
      219.90,  40, 'mouse-viper.svg', 1),
  (9,  3, 'Headset Sem Fio Aurora 7.1',
      'Headset wireless com som surround 7.1, microfone destacável com cancelamento de ruído e 30 horas de bateria.',
      459.00,  15, 'headset-aurora.svg', 1),
  (10, 3, 'Controle Sem Fio Phantom',
      'Controle Bluetooth com gatilhos adaptáveis, vibração háptica e compatibilidade com PC e consoles.',
      379.90,  18, 'controle-phantom.svg', 1),
  (11, 4, 'Mousepad Speed XL 90x40',
      'Mousepad estendido de 90x40 cm com superfície speed, base de borracha antiderrapante e bordas costuradas.',
      89.90,   50, 'mousepad-speed.svg', 1),
  (12, 4, 'Suporte Duplo para Headset e Controle',
      'Suporte em alumínio com base pesada para um headset e dois controles. Mantém o setup organizado.',
      129.90,  5,  'suporte-duplo.svg', 1);

-- Pedido de exemplo (para o painel admin já ter algo para exibir)
INSERT INTO pedidos (id, usuario_id, data_pedido, forma_pagamento, status, total) VALUES
  (1, 2, '2026-11-10 14:32:00', 'pix', 'pago', 659.70);

INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario) VALUES
  (1, 7,  1, 349.90),
  (1, 8,  1, 219.90),
  (1, 11, 1, 89.90);
