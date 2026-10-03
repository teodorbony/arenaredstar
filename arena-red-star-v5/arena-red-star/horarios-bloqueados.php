<?php
require_once 'app.php';
$u = auth();
if ($u['tipo'] === 'admin') redirect('admin.php');
$_SESSION['usuario_nome'] = $u['nome'];
$_SESSION['usuario_telefone'] = $u['telefone'] ?? '';

$horariosBloqueados = query("SELECT data,hora_inicio,hora_fim,motivo FROM bloqueios_horarios WHERE TIMESTAMP(data,hora_fim)>=NOW() ORDER BY data ASC,hora_inicio ASC")->fetchAll();

pageStart('Horários bloqueados');
?>
<div class="admin-title">
    <div>
        <div class="panel-kicker">AGENDA DA ARENA</div>
        <h1>Horários bloqueados</h1>
        <p class="muted">Consulte os períodos indisponíveis antes de fazer seu agendamento.</p>
    </div>
    <a class="primary-btn" href="usuario.php#agendar">Agendar horário</a>
</div>

<?php if (!$horariosBloqueados): ?>
    <section class="admin-form-card">
        <h2>Agenda disponível</h2>
        <p class="muted">Não há horários bloqueados a partir de agora.</p>
    </section>
<?php else: ?>
    <div class="section-heading">
        <div><h2>Próximos bloqueios</h2><p class="muted">Esses períodos não podem receber reservas.</p></div>
        <span class="count-badge"><?= count($horariosBloqueados) ?> bloqueio(s)</span>
    </div>
    <div class="table-card table-scroll">
        <table>
            <thead><tr><th>Data</th><th>Início</th><th>Fim</th><th>Motivo</th></tr></thead>
            <tbody><?php foreach ($horariosBloqueados as $bloqueio): ?><tr><td><?= date('d/m/Y', strtotime($bloqueio['data'])) ?></td><td><?= e(substr($bloqueio['hora_inicio'], 0, 5)) ?></td><td><?= e(substr($bloqueio['hora_fim'], 0, 5)) ?></td><td><?= e($bloqueio['motivo'] ?: 'Indisponível') ?></td></tr><?php endforeach; ?></tbody>
        </table>
    </div>
<?php endif; ?>
<?php pageEnd(); ?>
