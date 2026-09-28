-- Migração segura para ativar a recuperação de senha sem apagar dados.
-- Execute no phpMyAdmin com o banco arena_red_star selecionado.
CREATE TABLE IF NOT EXISTS recuperacoes_senha (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    codigo_hash VARCHAR(255) NOT NULL,
    expira_em DATETIME NOT NULL,
    usado_em DATETIME NULL,
    tentativas TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_recuperacao_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_recuperacao_usuario (usuario_id, expira_em)
) ENGINE=InnoDB;
