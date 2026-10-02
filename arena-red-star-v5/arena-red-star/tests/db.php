<?php
// Somente CLI e banco de testes explicitamente separado da instalação real.
if (PHP_SAPI !== 'cli' || !preg_match('/_test$/D', getenv('ARENA_DB_NAME') ?: '')) exit(1);
require dirname(__DIR__) . '/db.php';
$input=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$stmt=$pdo->prepare($input['sql']); $stmt->execute($input['args']??[]);
echo json_encode($stmt->columnCount()?$stmt->fetchAll():['id'=>$pdo->lastInsertId(),'count'=>$stmt->rowCount()],JSON_UNESCAPED_UNICODE);
