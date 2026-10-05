<?php
require_once 'app.php';
$u = auth();
if ($u['tipo'] === 'admin') redirect('agendamentos.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf();
        $acao = $_POST['acao'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        if ($acao === 'cancelar') {
            atomic(function () use ($u, $id) {
                $a = receipt((int)$u['id'], $id);
                if (!in_array($a['status'], ['Pendente', 'Confirmado'], true) || strtotime($a['data'].' '.$a['horario_inicio']) <= time()) throw new DomainException('Esta reserva não pode mais ser cancelada.');
                query("UPDATE agendamentos SET status='Cancelado' WHERE id=? AND usuario_id=?", [$id, $u['id']]);
            });
            notice('Reserva cancelada.');
        } elseif ($acao === 'solicitar_confirmacao_pix') {
            $s = query("UPDATE agendamentos SET pagamento_status='Solicitado' WHERE id=? AND usuario_id=? AND forma_pagamento='Pix' AND status='Pendente' AND pagamento_status='Pendente'", [$id, $u['id']]);
            if (!$s->rowCount()) throw new DomainException('Não é possível solicitar esse pagamento.');
            notice('Confirmação do Pix enviada ao administrador.');
        } elseif ($acao === 'avaliar') {
            addReview((int)$u['id'], $id, (int)filter_var($_POST['nota'] ?? 0, FILTER_VALIDATE_INT), $_POST['comentario'] ?? '');
            notice('Obrigado! Sua avaliação foi publicada.');
        } else {
            throw new DomainException('Ação inválida.');
        }
    } catch (Throwable $ex) {
        handleError($ex);
        if (($_POST['acao'] ?? '') === 'avaliar' && $id > 0) redirect('meus-agendamentos.php?avaliar='.$id);
    }
    redirect('meus-agendamentos.php');
}

$todos = query('SELECT a.*,v.id avaliacao_id FROM agendamentos a LEFT JOIN avaliacoes v ON v.agendamento_id=a.id WHERE a.usuario_id=? ORDER BY a.data DESC,a.horario_inicio DESC', [$u['id']])->fetchAll();
$reviewPromptId = (int)($_GET['avaliar'] ?? $_SESSION['review_prompt_id'] ?? 0);
unset($_SESSION['review_prompt_id']);
if (!array_filter($todos, fn($a) => (int)$a['id'] === $reviewPromptId && $a['status'] !== 'Cancelado' && !$a['avaliacao_id'])) $reviewPromptId = 0;
$filtrosPermitidos = ['Pendente', 'Confirmado', 'Concluído', 'Cancelado'];
$filtro = $_GET['status'] ?? '';
if (!in_array($filtro, $filtrosPermitidos, true)) $filtro = '';
$agendamentos = $filtro === '' ? $todos : array_values(array_filter($todos, fn($a) => $a['status'] === $filtro));
$reviewError = $reviewPromptId && ($_SESSION['notice'][1] ?? '') === 'danger' ? $_SESSION['notice'][0] : '';
$agora = time();
$proximos = count(array_filter($todos, fn($a) => in_array($a['status'], ['Pendente', 'Confirmado'], true) && strtotime($a['data'].' '.$a['horario_inicio']) > $agora));
$confirmados = count(array_filter($todos, fn($a) => $a['status'] === 'Confirmado'));
$concluidos = count(array_filter($todos, fn($a) => $a['status'] === 'Concluído'));
$cancelados = count(array_filter($todos, fn($a) => $a['status'] === 'Cancelado'));

pageStart('Meus Agendamentos');
?>
<div class="admin-title">
    <div><div class="panel-kicker">MINHA AGENDA</div><h1>Meus agendamentos</h1><p class="muted">Acompanhe pagamentos, situação das reservas e seus jogos anteriores.</p></div>
    <a class="primary-btn" href="usuario.php#agendar">Novo agendamento +</a>
</div>

<section class="stats booking-stats">
    <article><div><small>Próximos jogos</small><strong><?= $proximos ?></strong></div></article>
    <article class="green"><div><small>Confirmados</small><strong><?= $confirmados ?></strong></div></article>
    <article><div><small>Concluídos</small><strong><?= $concluidos ?></strong></div></article>
    <article class="red"><div><small>Cancelados</small><strong><?= $cancelados ?></strong></div></article>
</section>

<nav class="filter-tabs" aria-label="Filtrar agendamentos">
    <a class="<?= $filtro === '' ? 'active' : '' ?>" href="meus-agendamentos.php">Todos <span><?= count($todos) ?></span></a>
    <?php foreach ($filtrosPermitidos as $status): $quantidade = count(array_filter($todos, fn($a) => $a['status'] === $status)); ?>
        <a class="<?= $filtro === $status ? 'active' : '' ?>" href="?status=<?= urlencode($status) ?>"><?= e($status) ?> <span><?= $quantidade ?></span></a>
    <?php endforeach; ?>
</nav>

<section class="table-section user-bookings-section">
    <div class="section-heading"><div><div class="panel-kicker">RESERVAS</div><h2><?= $filtro ? e($filtro) : 'Todos os agendamentos' ?></h2></div><span class="count-badge"><?= count($agendamentos) ?> registro(s)</span></div>
    <div class="table-card"><div class="table-scroll"><table><thead><tr><th>Reserva</th><th>Data e horário</th><th>Duração</th><th>Valor</th><th>Pagamento</th><th>Cupom</th><th>Status</th><th>Ações</th></tr></thead><tbody>
    <?php if (!$agendamentos): ?><tr><td colspan="8" class="empty">Nenhum agendamento encontrado neste filtro.</td></tr>
    <?php else: foreach ($agendamentos as $a):
        $classe = in_array($a['status'], ['Confirmado', 'Concluído'], true) ? 'confirmed' : ($a['status'] === 'Cancelado' ? 'cancelled' : 'pending');
        $duracao = (strtotime($a['horario_fim']) - strtotime($a['horario_inicio'])) / 3600;
    ?>
        <tr>
            <td><strong>#<?= (int)$a['id'] ?></strong><small class="cell-note">Campo 1</small></td>
            <td><strong><?= date('d/m/Y', strtotime($a['data'])) ?></strong><small class="cell-note"><?= date('H:i', strtotime($a['horario_inicio'])) ?>–<?= date('H:i', strtotime($a['horario_fim'])) ?></small></td>
            <td><?= e(number_format($duracao, 0, ',', '.')) ?> hora(s)</td>
            <td><strong><?= money($a['valor']) ?></strong><?php if ((float)($a['desconto'] ?? 0) > 0): ?><small class="cell-note">Economia de <?= money($a['desconto']) ?></small><?php endif; ?></td>
            <td><?= e($a['forma_pagamento']) ?><small class="cell-note"><?= e($a['pagamento_status']) ?></small></td>
            <td><?= $a['cupom_codigo'] ? e($a['cupom_codigo']) : '—' ?></td>
            <td><span class="status <?= $classe ?>"><?= e($a['status']) ?></span></td>
            <td><div class="action-set vertical-actions">
                <?php if ($a['forma_pagamento'] === 'Pix' && $a['pagamento_status'] === 'Pendente' && $a['status'] === 'Pendente'): ?><form method="post"><?php csrfField(); ?><input type="hidden" name="acao" value="solicitar_confirmacao_pix"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="success-outline small" type="submit">Já realizei o Pix</button></form><?php endif; ?>
                <a class="outline-btn small" href="comprovante.php?id=<?= (int)$a['id'] ?>">Ver comprovante</a>
                <?php if ($a['status'] !== 'Cancelado' && !$a['avaliacao_id']): ?><button class="success-outline small" type="button" data-modal-open="review-modal" data-review-id="<?= (int)$a['id'] ?>">Avaliar o site ★</button><?php endif; ?>
                <?php if (in_array($a['status'], ['Pendente', 'Confirmado'], true) && strtotime($a['data'].' '.$a['horario_inicio']) > time()): ?><form method="post" onsubmit="return confirm('Cancelar esta reserva?');"><?php csrfField(); ?><input type="hidden" name="acao" value="cancelar"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="danger-outline small" type="submit">Cancelar</button></form><?php endif; ?>
            </div></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody></table></div></div>
</section>
<div class="modal-backdrop" id="review-modal" hidden>
    <section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="review-title">
        <button class="modal-close" type="button" data-modal-close aria-label="Fechar modal">×</button>
        <div class="panel-kicker">SUA OPINIÃO</div>
        <h2 id="review-title">Avaliar o site</h2>
        <?php if ($reviewError): ?><div role="alert" class="alert danger"><?= e($reviewError) ?></div><?php endif; ?>
        <p class="muted">Como foi sua experiência ao agendar a reserva <strong id="review-booking"></strong>? Sua avaliação pode ser enviada agora ou depois.</p>
        <form method="post" class="form-stack">
            <?php csrfField(); ?>
            <input type="hidden" name="acao" value="avaliar">
            <input type="hidden" name="id" id="review-id">
            <fieldset class="rating-options">
                <legend>Nota de 1 a 5 estrelas</legend>
                <?php for ($i = 1; $i <= 5; $i++): ?><label><input type="radio" name="nota" value="<?= $i ?>" required><?= $i ?> ★</label><?php endfor; ?>
            </fieldset>
            <label>Comentário (opcional)<textarea name="comentario" maxlength="2000" rows="5" placeholder="Conte o que achou do agendamento no site"></textarea></label>
            <div class="modal-actions"><button class="outline-btn" type="button" data-modal-close>Agora não</button><button class="primary-btn" type="submit">Publicar avaliação</button></div>
        </form>
    </section>
</div>
<script src="modal.js"></script>
<script>
function openReview(id) {
    const modal = document.getElementById('review-modal');
    document.getElementById('review-booking').textContent = '#' + id;
    modal.querySelector('form').reset();
    document.getElementById('review-id').value = id;
    modal.hidden = false;
    document.body.classList.add('modal-open');
    modal.querySelector('input[name="nota"]').focus();
}
document.querySelectorAll('[data-review-id]').forEach(button => {
    button.addEventListener('click', () => openReview(button.dataset.reviewId));
});
<?php if ($reviewPromptId): ?>openReview(<?= $reviewPromptId ?>);<?php endif; ?>
</script>
<?php pageEnd(); ?>
