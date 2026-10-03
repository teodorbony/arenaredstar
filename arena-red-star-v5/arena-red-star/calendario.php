<?php
require_once 'app.php';
$u = auth();

$mes = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mes) || !validDate($mes.'-01')) $mes = date('Y-m');

$primeiro = new DateTime($mes.'-01');
$proximo = (clone $primeiro)->modify('+1 month');
$anterior = (clone $primeiro)->modify('-1 month');

$stmt = $pdo->prepare("SELECT nome_cliente,data,horario_inicio,horario_fim,status FROM agendamentos WHERE data >= :inicio AND data < :fim AND status <> 'Cancelado' ORDER BY data,horario_inicio");
$stmt->execute(['inicio' => $primeiro->format('Y-m-d'), 'fim' => $proximo->format('Y-m-d')]);

$porDia = [];
foreach ($stmt->fetchAll() as $jogo) $porDia[(int)date('j', strtotime($jogo['data']))][] = $jogo;

$blocks = query('SELECT data,hora_inicio horario_inicio,hora_fim horario_fim,motivo FROM bloqueios_horarios WHERE data>=? AND data<? ORDER BY data,hora_inicio', [$primeiro->format('Y-m-d'), $proximo->format('Y-m-d')])->fetchAll();
foreach ($blocks as $b) {
    $b['nome_cliente'] = 'Horário bloqueado';
    $b['status'] = 'Bloqueado';
    $porDia[(int)date('j', strtotime($b['data']))][] = $b;
}

if ($u['tipo'] !== 'admin') {
    foreach ($porDia as &$games) {
        foreach ($games as &$g) {
            if ($g['status'] !== 'Bloqueado') $g['nome_cliente'] = 'Horário reservado';
        }
    }
    unset($games, $g);
}

$diasNoMes = (int)$primeiro->format('t');
$inicioSemana = (int)$primeiro->format('N') - 1;

pageStart('Calendário de horários');
?>
<section class="calendar-page">
    <div class="section-heading">
        <div><div class="panel-kicker">AGENDA RED STAR</div><h1>Calendário de horários</h1><p class="muted">Reservas ativas e horários bloqueados aparecem automaticamente.</p></div>
    </div>
    <div class="calendar-controls">
        <a class="outline-btn" href="?mes=<?= e($anterior->format('Y-m')) ?>">← Mês anterior</a>
        <h2><?= e(ucfirst($primeiro->format('F Y'))) ?></h2>
        <a class="outline-btn" href="?mes=<?= e($proximo->format('Y-m')) ?>">Próximo mês →</a>
    </div>
    <div class="calendar-card">
        <div class="calendar-grid calendar-weekdays"><div>Seg</div><div>Ter</div><div>Qua</div><div>Qui</div><div>Sex</div><div>Sáb</div><div>Dom</div></div>
        <div class="calendar-grid">
            <?php for ($i = 0; $i < $inicioSemana; $i++): ?><div class="calendar-day muted-day"></div><?php endfor; ?>
            <?php for ($dia = 1; $dia <= $diasNoMes; $dia++): ?>
                <div class="calendar-day<?= isset($porDia[$dia]) ? ' has-game' : '' ?>">
                    <strong><?= $dia ?></strong>
                    <?php foreach ($porDia[$dia] ?? [] as $j): ?>
                        <article class="game-chip"><b><?= e($j['nome_cliente']) ?></b><span><?= date('H:i', strtotime($j['horario_inicio'])) ?>–<?= date('H:i', strtotime($j['horario_fim'])) ?></span><small><?= e($j['status']) ?></small></article>
                    <?php endforeach; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</section>
<?php pageEnd(); ?>
