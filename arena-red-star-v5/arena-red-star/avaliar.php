<?php
require_once 'app.php';
$u = auth();
if ($u['tipo'] === 'admin') redirect('admin.php');

$id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf();
        addReview((int)$u['id'], $id, (int)filter_var($_POST['nota'] ?? 0, FILTER_VALIDATE_INT), $_POST['comentario'] ?? '');
        notice('Obrigado! Sua avaliação foi publicada.');
    } catch (Throwable $ex) {
        handleError($ex);
    }
    redirect('avaliar.php');
}

$disponiveis = query("SELECT a.id,a.data,a.horario_inicio,a.horario_fim,a.status
    FROM agendamentos a
    LEFT JOIN avaliacoes v ON v.agendamento_id=a.id
    WHERE a.usuario_id=? AND a.status<>'Cancelado' AND v.id IS NULL
    ORDER BY a.data DESC,a.horario_inicio DESC", [$u['id']])->fetchAll();

$avaliacoes = query("SELECT v.nota,v.comentario,v.data_avaliacao,a.id agendamento_id,a.data
    FROM avaliacoes v
    JOIN agendamentos a ON a.id=v.agendamento_id
    WHERE v.usuario_id=?
    ORDER BY v.data_avaliacao DESC", [$u['id']])->fetchAll();

pageStart('Avaliar a Arena');
?>
<div class="admin-title">
    <div><div class="panel-kicker">SUA OPINIÃO</div><h1>Avaliar a Arena</h1><p class="muted">Você pode avaliar qualquer uma das suas reservas não canceladas, sem precisar esperar a conclusão.</p></div>
</div>

<?php if ($disponiveis): ?>
<section class="admin-form-card narrow">
    <h2>Como foi sua experiência?</h2>
    <form method="post" class="form-stack">
        <?php csrfField(); ?>
        <label>Reserva
            <select name="id" required>
                <option value="">Escolha uma reserva</option>
                <?php foreach ($disponiveis as $reserva): ?>
                    <option value="<?= (int)$reserva['id'] ?>"<?= $id === (int)$reserva['id'] ? ' selected' : '' ?>>#<?= (int)$reserva['id'] ?> · <?= date('d/m/Y', strtotime($reserva['data'])) ?> · <?= date('H:i', strtotime($reserva['horario_inicio'])) ?>–<?= date('H:i', strtotime($reserva['horario_fim'])) ?> · <?= e($reserva['status']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <fieldset class="rating-options">
            <legend>Nota de 1 a 5 estrelas</legend>
            <?php for ($i = 1; $i <= 5; $i++): ?><label><input type="radio" name="nota" value="<?= $i ?>" required><?= $i ?> ★</label><?php endfor; ?>
        </fieldset>
        <label>Comentário (opcional)<textarea name="comentario" maxlength="2000" rows="5" placeholder="Conte como foi sua experiência na Arena Red Star"></textarea></label>
        <button class="primary-btn">Publicar avaliação</button>
    </form>
</section>
<?php else: ?>
<section class="admin-form-card narrow">
    <h2>Nenhuma reserva disponível para avaliar</h2>
    <p class="muted">Confira abaixo as avaliações que você já publicou.</p>
</section>
<?php endif; ?>

<?php if ($avaliacoes): ?>
<section class="landing-section">
    <div class="section-heading"><div><div class="panel-kicker">HISTÓRICO</div><h2>Minhas avaliações</h2></div><span class="count-badge"><?= count($avaliacoes) ?> publicada(s)</span></div>
    <div class="reviews-grid">
        <?php foreach ($avaliacoes as $avaliacao): ?>
            <article class="review-card">
                <div class="stars" aria-label="<?= (int)$avaliacao['nota'] ?> de 5 estrelas"><?= str_repeat('★', (int)$avaliacao['nota']).str_repeat('☆', 5-(int)$avaliacao['nota']) ?></div>
                <?php if ($avaliacao['comentario']): ?><p><?= nl2br(e($avaliacao['comentario'])) ?></p><?php endif; ?>
                <small>Reserva #<?= (int)$avaliacao['agendamento_id'] ?> · <?= date('d/m/Y', strtotime($avaliacao['data'])) ?></small>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<?php pageEnd(); ?>
