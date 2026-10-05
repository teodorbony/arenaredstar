<?php
// Endereço antigo preservado para links já compartilhados; a avaliação agora abre em modal.
require_once 'app.php';
$u = auth();
if ($u['tipo'] === 'admin') redirect('admin.php');
$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf();
        addReview((int)$u['id'], $id, (int)filter_var($_POST['nota'] ?? 0, FILTER_VALIDATE_INT), $_POST['comentario'] ?? '');
        notice('Obrigado! Sua avaliação foi publicada.');
        redirect('meus-agendamentos.php');
    } catch (Throwable $ex) {
        handleError($ex);
    }
}
redirect('meus-agendamentos.php'.($id > 0 ? '?avaliar='.$id : ''));
