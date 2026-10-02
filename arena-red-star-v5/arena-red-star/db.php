<?php
/** PDO: padrões do XAMPP; credenciais de produção via variáveis do servidor. */
$host = getenv('ARENA_DB_HOST') ?: 'localhost';
$dbname = getenv('ARENA_DB_NAME') ?: 'arena_red_star';
$usuarioBanco = getenv('ARENA_DB_USER') ?: 'root';
$senhaBanco = getenv('ARENA_DB_PASSWORD') ?: '';
$portaBanco = getenv('ARENA_DB_PORT') ?: '3306';
$dsn = "mysql:host={$host};port={$portaBanco};dbname={$dbname};charset=utf8mb4";
try {
 $pdo = new PDO($dsn,$usuarioBanco,$senhaBanco,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
} catch(PDOException $e) {
 error_log($e->getMessage()); http_response_code(503);
 exit('Não foi possível conectar ao banco. Confira a configuração do servidor.');
}
