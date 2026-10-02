-- Arena Red Star V4
-- Banco completo para XAMPP / Laragon

CREATE DATABASE IF NOT EXISTS arena_red_star CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE arena_red_star;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    telefone VARCHAR(25) NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    tipo ENUM('admin','cliente') NOT NULL DEFAULT 'cliente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS agendamentos (
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

CREATE TABLE IF NOT EXISTS recuperacoes_senha (
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
INSERT IGNORE INTO usuarios (nome, telefone, email, senha, tipo) VALUES
('Administrador', NULL, 'admin@arenaredstar.com', '$2y$12$qbJmIqTDEmxVQIDQ0NMxBexpt8YqJMXdOZPmY6VcnmR6ifW/IIhra', 'admin');


-- Atualização não destrutiva e repetível. Execute no banco arena_red_star existente.
-- Preserve backup antes de aplicar. Não há exclusão de usuários/reservas/pagamentos.
USE arena_red_star;
ALTER TABLE agendamentos MODIFY status ENUM('Pendente','Confirmado','Cancelado','Concluído') NOT NULL DEFAULT 'Pendente';
DELIMITER $$
CREATE PROCEDURE arena_melhorias_20261002()
BEGIN
 IF NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='agendamentos' AND column_name='valor_original') THEN
  ALTER TABLE agendamentos ADD valor_original DECIMAL(10,2) NULL;
 END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='agendamentos' AND column_name='desconto') THEN
  ALTER TABLE agendamentos ADD desconto DECIMAL(10,2) NOT NULL DEFAULT 0;
 END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='agendamentos' AND column_name='cupom_codigo') THEN
  ALTER TABLE agendamentos ADD cupom_codigo VARCHAR(30) NULL;
 END IF;
END$$
CALL arena_melhorias_20261002()$$
DROP PROCEDURE arena_melhorias_20261002$$
DELIMITER ;
CREATE TABLE IF NOT EXISTS avaliacoes (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 usuario_id INT UNSIGNED NOT NULL,
 agendamento_id INT UNSIGNED NOT NULL UNIQUE,
 nota TINYINT UNSIGNED NOT NULL,
 comentario TEXT,
 data_avaliacao DATETIME DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(usuario_id) REFERENCES usuarios(id),
 FOREIGN KEY(agendamento_id) REFERENCES agendamentos(id),
 CHECK(nota BETWEEN 1 AND 5)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS bloqueios_horarios (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 data DATE NOT NULL,
 hora_inicio TIME NOT NULL,
 hora_fim TIME NOT NULL,
 motivo VARCHAR(255),
 criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_bloqueio(data,hora_inicio,hora_fim),
 CHECK(hora_fim>hora_inicio)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS cupons (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 codigo VARCHAR(30) NOT NULL UNIQUE,
 porcentagem DECIMAL(5,2) NOT NULL,
 validade DATE NULL,
 max_usos INT UNSIGNED NULL,
 ativo TINYINT UNSIGNED NOT NULL DEFAULT 1,
 criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
 CHECK(porcentagem>0 AND porcentagem<=100)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS usuario_cupons (
 usuario_id INT UNSIGNED NOT NULL,
 cupom_id INT UNSIGNED NOT NULL,
 concedido_em DATETIME DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(usuario_id,cupom_id),
 FOREIGN KEY(usuario_id) REFERENCES usuarios(id),
 FOREIGN KEY(cupom_id) REFERENCES cupons(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS usos_cupons (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 cupom_id INT UNSIGNED NOT NULL,
 usuario_id INT UNSIGNED NOT NULL,
 agendamento_id INT UNSIGNED NOT NULL UNIQUE,
 usado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(cupom_id,usuario_id),
 FOREIGN KEY(cupom_id) REFERENCES cupons(id),
 FOREIGN KEY(usuario_id) REFERENCES usuarios(id),
 FOREIGN KEY(agendamento_id) REFERENCES agendamentos(id)
) ENGINE=InnoDB;
INSERT INTO cupons(codigo,porcentagem) SELECT 'REDSTAR10',10 WHERE NOT EXISTS(SELECT 1 FROM cupons WHERE codigo='REDSTAR10');
