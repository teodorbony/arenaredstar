<?php
require_once 'app.php';
$u = auth();
if ($u['tipo'] === 'admin') redirect('admin.php');
$_SESSION['usuario_nome'] = $u['nome'];
$_SESSION['usuario_telefone'] = $u['telefone'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $destino = 'usuario.php#agendar';
    try {
        csrf();
        if (($_POST['acao'] ?? '') !== 'agendar') throw new DomainException('Ação inválida.');
        $id = createBooking($u, [
            'data' => $_POST['data'] ?? '',
            'inicio' => $_POST['horario_inicio'] ?? '',
            'fim' => $_POST['horario_fim'] ?? '',
            'pagamento' => $_POST['forma_pagamento'] ?? '',
            'cupom' => $_POST['cupom'] ?? ''
        ]);
        notice('Reserva #'.$id.' enviada! Acompanhe a confirmação nesta página.');
        $destino = 'meus-agendamentos.php';
    } catch (Throwable $ex) {
        handleError($ex);
    }
    redirect($destino);
}

$quantidadeAgendamentos = (int)query('SELECT COUNT(*) FROM agendamentos WHERE usuario_id=?', [$u['id']])->fetchColumn();
$meusCupons = query("SELECT c.*,uc.concedido_em,(SELECT COUNT(*) FROM usos_cupons uso WHERE uso.cupom_id=c.id AND uso.usuario_id=?) utilizado,(SELECT COUNT(*) FROM usos_cupons uso WHERE uso.cupom_id=c.id) total_usos FROM cupons c LEFT JOIN usuario_cupons uc ON uc.cupom_id=c.id AND uc.usuario_id=? WHERE c.codigo<>'REDSTAR10' OR uc.usuario_id IS NOT NULL ORDER BY c.ativo DESC,c.validade IS NULL,c.validade ASC,c.id DESC", [$u['id'],$u['id']])->fetchAll();
foreach ($meusCupons as &$cupomUsuario) {
    $vencido = $cupomUsuario['validade'] && $cupomUsuario['validade'] < date('Y-m-d');
    $esgotado = $cupomUsuario['max_usos'] !== null && $cupomUsuario['total_usos'] >= $cupomUsuario['max_usos'];
    $primeiraReservaUsada = $cupomUsuario['codigo'] === 'REDSTAR10' && $quantidadeAgendamentos > 0;
    $cupomUsuario['disponivel'] = !$cupomUsuario['utilizado'] && $cupomUsuario['ativo'] && !$vencido && !$esgotado && !$primeiraReservaUsada;
    $cupomUsuario['estado'] = $cupomUsuario['utilizado'] ? 'Utilizado' : (!$cupomUsuario['ativo'] ? 'Desativado' : ($vencido ? 'Vencido' : ($esgotado ? 'Esgotado' : ($primeiraReservaUsada ? 'Exclusivo para a primeira reserva' : 'Disponível'))));
}
unset($cupomUsuario);
$horariosBloqueados = query("SELECT data,hora_inicio,hora_fim,motivo FROM bloqueios_horarios WHERE TIMESTAMP(data,hora_fim)>=NOW() ORDER BY data ASC,hora_inicio ASC")->fetchAll();
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Agendar horário | Red Star Point Ball</title><link rel="stylesheet" href="style.css"><link rel="stylesheet" href="interface.css">
</head>
<body class="dashboard-page">
<?php renderTopbar(); ?>

<main>
<section id="agendar" class="user-hero section-wrap">
    <div class="hero-copy reveal">
        <div class="big-callout">GARANTA<br>SEU HORÁRIO</div>
        <p>Reserve o campo, chame os amigos<br>e venha fazer parte dessa resenha!</p>
        <div class="feature-list"><span>⚽ Campo society</span><span>👥 Confraternização</span><span>🍻 Bebidas</span></div>
    </div>

    <div class="booking-card reveal delay-1">
        <div class="panel-kicker">RESERVA ONLINE</div>
        <h2>Novo agendamento</h2>
        <p class="muted">Escolha a data e os horários de início e fim para reservar o campo.</p>
        <?php showNotice(); ?>
        <form method="post" class="booking-grid">
            <?php csrfField(); ?>
            <input type="hidden" name="acao" value="agendar">
            <label>Data<input type="date" name="data" id="data" required></label>
            <label>Início<input type="time" name="horario_inicio" required></label>
            <label>Fim<input type="time" name="horario_fim" required></label>
            <label class="full">Forma de pagamento<select name="forma_pagamento" id="forma_pagamento" required><option value="" selected disabled>Selecione uma opção</option><option value="Pix">Pix</option><option value="Dinheiro">Dinheiro (pagar no jogo)</option><option value="Cartão">Cartão (pagar no jogo)</option></select></label>
            <label class="full">Cupom de desconto<div class="coupon-input"><input name="cupom" id="cupom" maxlength="30" placeholder="Ex.: REDSTAR10"><button type="button" class="outline-btn" id="aplicar-cupom">Aplicar</button></div></label>
            <button type="button" class="coupon-modal-trigger full" data-modal-open="coupons-modal">🎟 Ver meus cupons disponíveis</button>
            <div class="price-summary full" id="resumo-valor" aria-live="polite">Escolha a data e os horários para calcular o valor.</div>
            <div class="pix-box full" id="pix-box" hidden><strong>Pagamento por Pix</strong><b id="pix-valor">Selecione os horários para calcular o valor.</b><img id="pix-qrcode" alt="QR Code Pix"><small>Após pagar, solicite a confirmação do Pix em Meus agendamentos.</small></div>
            <label class="full">Campo<div class="fixed-field">⚽ Campo 1 (Único)</div></label>
            <p class="single-field-note">Temos apenas 1 campo society.</p>
            <button class="primary-btn full" type="submit">▣ Reservar agora</button>
        </form>
    </div>
</section>

<section class="landing-section section-wrap" id="horarios-bloqueados">
    <div class="section-heading"><div><div class="panel-kicker">AGENDA DA ARENA</div><h2>Horários bloqueados</h2><p class="muted">Consulte os períodos indisponíveis antes de fazer sua reserva.</p></div><span class="count-badge"><?= count($horariosBloqueados) ?> bloqueio(s)</span></div>
    <?php if (!$horariosBloqueados): ?>
        <div class="admin-form-card"><p class="muted">Não há horários bloqueados a partir de agora.</p></div>
    <?php else: ?>
        <div class="table-card table-scroll"><table><thead><tr><th>Data</th><th>Início</th><th>Fim</th><th>Motivo</th></tr></thead><tbody><?php foreach ($horariosBloqueados as $bloqueio): ?><tr><td><?= date('d/m/Y', strtotime($bloqueio['data'])) ?></td><td><?= e(substr($bloqueio['hora_inicio'], 0, 5)) ?></td><td><?= e(substr($bloqueio['hora_fim'], 0, 5)) ?></td><td><?= e($bloqueio['motivo'] ?: 'Indisponível') ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
</section>

<section class="benefit-row section-wrap">
    <article>🏆 <div><strong>Estrutura de qualidade</strong><small>Campo society bem cuidado</small></div></article>
    <article>👥 <div><strong>Ambiente familiar</strong><small>Para todas as idades</small></div></article>
    <article>☷ <div><strong>Agenda organizada</strong><small><a href="meus-agendamentos.php">Acompanhar minhas reservas →</a></small></div></article>
</section>
</main>

<div class="modal-backdrop" id="coupons-modal" hidden>
    <section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="coupons-title">
        <button class="modal-close" type="button" data-modal-close aria-label="Fechar modal">×</button>
        <div class="panel-kicker">BENEFÍCIOS DA SUA CONTA</div>
        <h2 id="coupons-title">Meus cupons</h2>
        <p class="muted">Escolha um cupom disponível para aplicar nesta reserva.</p>
        <div class="coupon-list">
            <?php if (!$meusCupons): ?><p class="empty-state">Você não possui cupons disponíveis no momento.</p><?php endif; ?>
            <?php foreach ($meusCupons as $c): ?>
                <article class="coupon-card<?= $c['disponivel'] ? '' : ' unavailable' ?>">
                    <div><span><?= e($c['estado']) ?></span><h3><?= e($c['codigo']) ?></h3><strong><?= e($c['porcentagem']) ?>% de desconto</strong><?php if ($c['validade']): ?><small>Válido até <?= date('d/m/Y', strtotime($c['validade'])) ?></small><?php endif; ?></div>
                    <?php if ($c['disponivel']): ?><button class="primary-btn small" type="button" data-coupon-code="<?= e($c['codigo']) ?>">Usar este cupom</button><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<footer class="site-footer">© <?= date('Y') ?> Red Star Point Ball · <a href="https://www.instagram.com/redstarpointball/" target="_blank" rel="noopener">Instagram @redstarpointball</a></footer>
<script src="reserva.js"></script>
<script src="modal.js"></script>
<script>
document.querySelectorAll('[data-coupon-code]').forEach(button => button.addEventListener('click', () => {
    const input = document.getElementById('cupom');
    input.value = button.dataset.couponCode;
    input.dispatchEvent(new Event('input', {bubbles: true}));
    document.getElementById('coupons-modal').hidden = true;
    document.body.classList.remove('modal-open');
    document.getElementById('aplicar-cupom').click();
}));
</script>
</body></html>
