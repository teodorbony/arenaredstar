<?php
require_once 'app.php';
auth(true);
require_once 'admin_actions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf();
        adminAction($_POST);
        notice('Agenda atualizada.');
    } catch (Throwable $ex) {
        handleError($ex);
    }
    redirect('agendamentos.php');
}

$agendamentos = $pdo->query(
    'SELECT id,usuario_id,nome_cliente,telefone,data,horario_inicio,horario_fim,forma_pagamento,valor,pagamento_status,campo,status
     FROM agendamentos
     ORDER BY data ASC, horario_inicio ASC'
)->fetchAll();
$total = count($agendamentos);
$confirmados = count(array_filter($agendamentos, fn($a) => $a['status'] === 'Confirmado'));
$pendentes = count(array_filter($agendamentos, fn($a) => $a['status'] === 'Pendente'));
$cancelados = count(array_filter($agendamentos, fn($a) => $a['status'] === 'Cancelado'));

pageStart('Agendamentos');
?>
<div class="admin-title">
    <div>
        <div class="panel-kicker">AGENDA RED STAR</div>
        <h1>Agendamentos</h1>
        <p class="muted">Cadastre, confirme e acompanhe todas as reservas da arena.</p>
    </div>
    <div class="page-actions">
        <button class="primary-btn" type="button" data-modal-open="admin-booking-modal">Novo agendamento +</button>
        <a class="outline-btn" href="admin.php">← Voltar ao painel</a>
    </div>
</div>

<section class="stats booking-stats">
    <article><div><small>Total de registros</small><strong><?= $total ?></strong></div></article>
    <article class="green"><div><small>Confirmados</small><strong><?= $confirmados ?></strong></div></article>
    <article class="yellow"><div><small>Pendentes</small><strong><?= $pendentes ?></strong></div></article>
    <article class="red"><div><small>Cancelados</small><strong><?= $cancelados ?></strong></div></article>
</section>

<div class="modal-backdrop" id="admin-booking-modal" hidden>
    <section class="modal-card modal-wide" role="dialog" aria-modal="true" aria-labelledby="admin-booking-title">
        <button class="modal-close" type="button" data-modal-close aria-label="Fechar modal">×</button>
        <div class="panel-kicker">NOVO REGISTRO</div>
        <h2 id="admin-booking-title">Adicionar agendamento manual</h2>
        <p class="muted">Preencha os dados do cliente e escolha um horário disponível.</p>
        <form method="post" class="admin-form-grid">
            <?php csrfField(); ?>
            <input type="hidden" name="acao" value="adicionar">
            <label>Cliente<input type="text" name="nome_cliente" required maxlength="120" placeholder="Nome do cliente"></label>
            <label>Telefone<input type="text" name="telefone" id="telefone" required maxlength="25" placeholder="(00) 00000-0000"></label>
            <label>Data<input type="date" name="data" id="data" required></label>
            <label>Início<input type="time" name="horario_inicio" required></label>
            <label>Fim<input type="time" name="horario_fim" required></label>
            <label>Campo<div class="fixed-field compact">⚽ Campo 1 (Único)</div></label>
            <label>Pagamento<select name="forma_pagamento"><option>Dinheiro</option><option>Cartão</option><option>Pix</option></select></label>
            <label>Status<select name="status"><option>Pendente</option><option>Confirmado</option><option>Cancelado</option></select></label>
            <div class="modal-actions full-span"><button class="outline-btn" type="button" data-modal-close>Cancelar</button><button class="primary-btn" type="submit">Adicionar reserva +</button></div>
        </form>
    </section>
</div>

<section class="table-section">
    <div class="section-heading">
        <div><div class="panel-kicker">AGENDA</div><h2>Todos os agendamentos</h2></div>
        <span class="count-badge"><?= $total ?> registro(s)</span>
    </div>
    <div class="table-card">
        <div class="table-scroll">
            <table>
                <thead><tr><th>Cliente</th><th>Telefone</th><th>Data</th><th>Início</th><th>Fim</th><th>Valor</th><th>Pagamento</th><th>Campo</th><th>Status</th><th>Ações</th></tr></thead>
                <tbody>
                <?php if (!$agendamentos): ?>
                    <tr><td colspan="10" class="empty">Nenhum agendamento cadastrado.</td></tr>
                <?php else: foreach ($agendamentos as $a):
                    $classe = in_array($a['status'], ['Confirmado', 'Concluído'], true) ? 'confirmed' : ($a['status'] === 'Cancelado' ? 'cancelled' : 'pending');
                ?>
                    <tr>
                        <td><strong><?= e($a['nome_cliente']) ?></strong></td>
                        <td><?= e($a['telefone']) ?></td>
                        <td><?= date('d/m/Y', strtotime($a['data'])) ?></td>
                        <td><?= date('H:i', strtotime($a['horario_inicio'])) ?></td>
                        <td><?= date('H:i', strtotime($a['horario_fim'])) ?></td>
                        <td><?= money($a['valor']) ?></td>
                        <td><?= e($a['forma_pagamento']) ?><?= $a['forma_pagamento'] === 'Pix' ? ' · '.e($a['pagamento_status']) : '' ?></td>
                        <td>Campo 1</td>
                        <td><span class="status <?= $classe ?>"><?= e($a['status']) ?></span></td>
                        <td><div class="action-set">
                            <?php if ($a['status'] === 'Pendente' && ($a['forma_pagamento'] !== 'Pix' || (float)$a['valor'] === 0.0)): ?>
                                <form method="post"><?php csrfField(); ?><input type="hidden" name="acao" value="confirmar"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="success-outline small">Confirmar</button></form>
                            <?php endif; ?>
                            <?php if ($a['status'] === 'Pendente' && $a['forma_pagamento'] === 'Pix' && ($a['pagamento_status'] === 'Solicitado' || ($a['usuario_id'] === null && $a['pagamento_status'] === 'Pendente'))): ?>
                                <form method="post"><?php csrfField(); ?><input type="hidden" name="acao" value="confirmar_pix"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="success-outline small">Confirmar Pix</button></form>
                            <?php endif; ?>
                            <?php if (in_array($a['status'], ['Pendente', 'Confirmado'], true)): ?>
                                <form method="post"><?php csrfField(); ?><input type="hidden" name="acao" value="cancelar"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="warning-outline small">Cancelar</button></form>
                            <?php endif; ?>
                            <?php if ($a['status'] !== 'Cancelado' && $a['forma_pagamento'] !== 'Pix' && $a['pagamento_status'] !== 'Confirmado'): ?>
                                <form method="post"><?php csrfField(); ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="success-outline small" name="acao" value="receber">Confirmar recebimento</button></form>
                            <?php endif; ?>
                            <?php if ($a['status'] === 'Confirmado' && strtotime($a['data'].' '.$a['horario_fim']) <= time()): ?>
                                <form method="post"><?php csrfField(); ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="outline-btn small" name="acao" value="concluir">Concluir reserva</button></form>
                            <?php endif; ?>
                        </div></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<script>
const data = document.getElementById('data');
const hoje = new Date();
data.min = new Date(hoje.getTime() - hoje.getTimezoneOffset() * 60000).toISOString().split('T')[0];
const tel = document.getElementById('telefone');
tel.addEventListener('input', function () {
    let v = this.value.replace(/\D/g, '').slice(0, 11);
    if (v.length > 10) v = v.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    else if (v.length > 6) v = v.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
    else if (v.length > 2) v = v.replace(/(\d{2})(\d+)/, '($1) $2');
    this.value = v;
});
</script>
<script src="modal.js"></script>
<?php pageEnd(); ?>
