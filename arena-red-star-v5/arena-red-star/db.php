<?php
/**
 * Conexão com o banco de dados usando PDO.
 * Ajuste usuário e senha abaixo caso seu MySQL seja diferente do padrão do XAMPP.
 */
$host = 'localhost';
$dbname = 'arena_red_star';
$usuarioBanco = 'root';
$senhaBanco = '';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";

try {
    $pdo = new PDO($dsn, $usuarioBanco, $senhaBanco, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die('Não foi possível conectar ao banco. Importe o banco.sql e confira o db.php.');
}
