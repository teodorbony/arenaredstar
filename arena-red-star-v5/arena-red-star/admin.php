<?php
require_once 'app.php';
auth(true);
pageStart('Painel Administrativo');
?>
<div class="admin-title">
    <div>
        <div class="panel-kicker">GESTÃO RED STAR</div>
        <h1>Painel Administrativo</h1>
        <p class="muted">Acompanhe os principais indicadores da arena.</p>
    </div>
    <a class="primary-btn" href="agendamentos.php">Gerenciar agendamentos</a>
</div>

<?php require 'dashboard.php'; ?>

<?php pageEnd(); ?>
