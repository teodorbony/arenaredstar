-- Arena Red Star V4
-- Banco completo para XAMPP / Laragon

CREATE DATABASE IF NOT EXISTS arena_red_star CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE arena_red_star;

DROP TABLE IF EXISTS agendamentos;
DROP TABLE IF EXISTS recuperacoes_senha;
DROP TABLE IF EXISTS usuarios;

CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    telefone VARCHAR(25) NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('admin','cliente') NOT NULL DEFAULT 'cliente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE agendamentos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NULL,
    nome_cliente VARCHAR(120) NOT NULL,
    telefone VARCHAR(25) NOT NULL,
    data DATE NOT NULL,
    horario_inicio TIME NOT NULL,
    horario_fim TIME NOT NULL,
    forma_pagamento ENUM('Pix','Dinheiro','Cartão') NOT NULL DEFAULT 'Dinheiro',
    valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    pagamento_status ENUM('Não se aplica','Pendente','Solicitado','Confirmado') NOT NULL DEFAULT 'Não se aplica',
    campo VARCHAR(50) NOT NULL DEFAULT 'Campo 1',
    status ENUM('Pendente','Confirmado','Cancelado') NOT NULL DEFAULT 'Pendente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_agendamento_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_agenda (data, horario_inicio, horario_fim),
    INDEX idx_usuario (usuario_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

CREATE TABLE recuperacoes_senha (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    codigo_hash VARCHAR(255) NOT NULL,
    expira_em DATETIME NOT NULL,
    usado_em DATETIME NULL,
    tentativas TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_recuperacao_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_recuperacao_usuario (usuario_id, expira_em)
) ENGINE=InnoDB;

-- Administrador padrão: admin@arenaredstar.com / admin123
INSERT INTO usuarios (nome, telefone, email, senha, tipo) VALUES
('Administrador', NULL, 'admin@arenaredstar.com', '$2y$12$qbJmIqTDEmxVQIDQ0NMxBexpt8YqJMXdOZPmY6VcnmR6ifW/IIhra', 'admin');

-- Exemplos usando apenas o único campo disponível.
INSERT INTO agendamentos (usuario_id, nome_cliente, telefone, data, horario_inicio, horario_fim, forma_pagamento, campo, status) VALUES
(NULL, 'João Silva', '(11) 99999-1111', '2026-09-12', '19:00:00', '20:00:00', 'Pix', 'Campo 1', 'Pendente'),
(NULL, 'Carlos Souza', '(11) 98888-2222', '2026-09-13', '20:00:00', '21:00:00', 'Dinheiro', 'Campo 1', 'Confirmado'),
(NULL, 'Mariana Lima', '(11) 97777-3333', '2026-09-14', '21:00:00', '22:00:00', 'Cartão', 'Campo 1', 'Pendente');
