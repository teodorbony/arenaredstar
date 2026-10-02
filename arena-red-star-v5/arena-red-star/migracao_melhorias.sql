-- Atualização não destrutiva e repetível. Execute no banco arena_red_star existente.
-- Preserve backup antes de aplicar. Não há exclusão de usuários/reservas/pagamentos.
USE arena_red_star;
ALTER TABLE agendamentos MODIFY status ENUM('Pendente','Confirmado','Cancelado','Concluído') NOT NULL DEFAULT 'Pendente';
DELIMITER $$
CREATE PROCEDURE arena_melhorias_20261002()
BEGIN
 IF NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='agendamentos' AND column_name='valor') THEN
  ALTER TABLE agendamentos ADD valor DECIMAL(10,2) NOT NULL DEFAULT 0;
 END IF;
 IF NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='agendamentos' AND column_name='pagamento_status') THEN
  ALTER TABLE agendamentos ADD pagamento_status ENUM('Não se aplica','Pendente','Solicitado','Confirmado') NOT NULL DEFAULT 'Não se aplica';
 END IF;
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

