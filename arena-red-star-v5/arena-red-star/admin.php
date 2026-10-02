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

<section class="admin-form-card admin-agenda-callout">
    <div>
        <div class="panel-kicker">AGENDA</div>
        <h2>Agendamentos em uma página exclusiva</h2>
        <p class="muted">Cadastre reservas manuais, acompanhe pagamentos e altere o status dos horários.</p>
    </div>
    <a class="outline-btn" href="agendamentos.php">Abrir agendamentos →</a>
</section>
<?php pageEnd(); ?>
