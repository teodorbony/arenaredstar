<?php
require_once 'app.php'; $u=auth(); header('Content-Type: application/json; charset=utf-8');
try {
 if($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit; }
 csrf(); $q=quote((int)$u['id'],$_POST['data']??'',$_POST['inicio']??'',$_POST['fim']??'',$_POST['cupom']??'');
 available($_POST['data']??'',$_POST['inicio']??'',$_POST['fim']??'');
 unset($q['cupom']); echo json_encode($q);
} catch(Throwable $ex) { http_response_code(422); echo json_encode(['erro'=>$ex instanceof DomainException?$ex->getMessage():'Não foi possível calcular. Confira a atualização do banco.']); }
