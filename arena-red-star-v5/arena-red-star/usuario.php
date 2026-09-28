<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['usuario_id'])) { header('Location: login.php'); exit; }
if (($_SESSION['usuario_tipo'] ?? '') === 'admin') { header('Location: admin.php'); exit; }

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

function flashUser(string $msg, string $tipo='success'): void {
    $_SESSION['flash_user'] = $msg;
    $_SESSION['flash_user_type'] = $tipo;
    header('Location: usuario.php');
    exit;
}

$mensagem = $_SESSION['flash_user'] ?? '';
$tipoMensagem = $_SESSION['flash_user_type'] ?? 'success';
unset($_SESSION['flash_user'], $_SESSION['flash_user_type']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) flashUser('Sessão expirada. Atualize a página.', 'danger');
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'agendar') {
        $data = $_POST['data'] ?? '';
        $horarioInicio = $_POST['horario_inicio'] ?? '';
        $horarioFim = $_POST['horario_fim'] ?? '';
        $pagamento = $_POST['forma_pagamento'] ?? 'Dinheiro';
        $duracaoMinutos = (strtotime($horarioFim) - strtotime($horarioInicio)) / 60;
        $valor = $duracaoMinutos * 2; // R$ 120 por hora
        $campo = 'Campo 1'; // A Arena Red Star possui somente um campo.

        $d = DateTime::createFromFormat('Y-m-d', $data);
        if (!$d || $d->format('Y-m-d') !== $data || $data < date('Y-m-d')) flashUser('Escolha uma data válida.', 'danger');
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $horarioInicio) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $horarioFim) || $horarioFim <= $horarioInicio) flashUser('Informe um horário de início e fim válidos.', 'danger');
        if (!in_array($pagamento, ['Pix','Dinheiro','Cartão'], true)) flashUser('Escolha uma forma de pagamento válida.', 'danger');
        if ($duracaoMinutos < 60 || $duracaoMinutos % 60 !== 0) flashUser('A reserva deve ter períodos inteiros de 1 hora.', 'danger');

        $conflito = $pdo->prepare("SELECT COUNT(*) FROM agendamentos WHERE data=:data AND campo='Campo 1' AND status <> 'Cancelado' AND horario_inicio < :fim AND horario_fim > :inicio");
        $conflito->execute(['data'=>$data,'inicio'=>$horarioInicio,'fim'=>$horarioFim]);
        if ((int)$conflito->fetchColumn() > 0) flashUser('Esse período já possui uma reserva. Escolha outro.', 'danger');

        $stmt = $pdo->prepare('INSERT INTO agendamentos (usuario_id, nome_cliente, telefone, data, horario_inicio, horario_fim, forma_pagamento, valor, pagamento_status, campo, status) VALUES (:usuario_id, :nome, :telefone, :data, :inicio, :fim, :pagamento, :valor, :pagamento_status, :campo, :status)');
        $stmt->execute([
            'usuario_id' => $_SESSION['usuario_id'],
            'nome' => $_SESSION['usuario_nome'],
            'telefone' => $_SESSION['usuario_telefone'],
            'data' => $data,
            'inicio' => $horarioInicio,
            'fim' => $horarioFim,
            'pagamento' => $pagamento,
            'valor' => $valor,
            'pagamento_status' => $pagamento === 'Pix' ? 'Pendente' : 'Não se aplica',
            'campo' => $campo,
            'status' => 'Pendente'
        ]);
        flashUser('Reserva enviada! Aguarde a confirmação do administrador.');
    }

    if ($acao === 'cancelar') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) flashUser('Reserva inválida.', 'danger');
        $stmt = $pdo->prepare("UPDATE agendamentos SET status='Cancelado' WHERE id=:id AND usuario_id=:usuario_id AND status <> 'Cancelado'");
        $stmt->execute(['id'=>$id, 'usuario_id'=>$_SESSION['usuario_id']]);
        flashUser('Reserva cancelada.');
    }
    if ($acao === 'solicitar_confirmacao_pix') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if (!$id) flashUser('Reserva inválida.', 'danger');
        $stmt = $pdo->prepare("UPDATE agendamentos SET pagamento_status='Solicitado' WHERE id=:id AND usuario_id=:usuario_id AND forma_pagamento='Pix' AND status='Pendente'");
        $stmt->execute(['id'=>$id,'usuario_id'=>$_SESSION['usuario_id']]);
        flashUser('Confirmação de Pix enviada ao administrador. Sua reserva será confirmada após a verificação.');
    }
}

$stmt = $pdo->prepare('SELECT id, data, horario_inicio, horario_fim, forma_pagamento, valor, pagamento_status, campo, status, created_at FROM agendamentos WHERE usuario_id=:usuario_id ORDER BY data DESC, horario_inicio DESC');
$stmt->execute(['usuario_id'=>$_SESSION['usuario_id']]);
$agendamentos = $stmt->fetchAll();
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Minha área | Red Star Point Ball</title><link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-page">
<header class="topbar">
    <a class="brand-mini" href="usuario.php"><img src="assets/logo-red-star.png" alt="Red Star"><span>Red Star<br><small>Point Ball</small></span></a>
    <nav class="nav-tabs"><a href="#agendar" class="active">▣ Agendar</a><a href="#meus">☷ Meus agendamentos</a><a href="calendario.php">▣ Calendário</a></nav>
    <div class="user-actions"><span>👤 Olá, <?= htmlspecialchars(explode(' ', trim($_SESSION['usuario_nome']))[0], ENT_QUOTES, 'UTF-8') ?></span><a href="logout.php">↪ Sair</a></div>
</header>

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
        <?php if ($mensagem): ?><div class="alert <?= $tipoMensagem === 'danger' ? 'danger' : 'success' ?>"><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <form method="post" class="booking-grid">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="acao" value="agendar">
            <label>Data<input type="date" name="data" id="data" required></label>
            <label>Início<input type="time" name="horario_inicio" required></label>
            <label>Fim<input type="time" name="horario_fim" required></label>
            <label class="full">Forma de pagamento<select name="forma_pagamento" id="forma_pagamento" required><option value="" selected disabled>Selecione uma opção</option><option value="Pix">Pix</option><option value="Dinheiro">Dinheiro (pagar no jogo)</option><option value="Cartão">Cartão (pagar no jogo)</option></select></label>
            <div class="pix-box full" id="pix-box" hidden><strong>Pagamento por Pix</strong><b id="pix-valor">Selecione os horários para calcular o valor.</b><img id="pix-qrcode" alt="QR Code Pix"><small>Após pagar, envie a confirmação do Pix na sua agenda para o administrador.</small></div>
            <label class="full">Campo<div class="fixed-field">⚽ Campo 1 (Único)</div></label>
            <p class="single-field-note">Temos apenas 1 campo society.</p>
            <button class="primary-btn full" type="submit">▣ Reservar agora</button>
        </form>
    </div>
</section>

<section class="benefit-row section-wrap">
    <article>🏆 <div><strong>Estrutura de qualidade</strong><small>Campo society bem cuidado</small></div></article>
    <article>👥 <div><strong>Ambiente familiar</strong><small>Para todas as idades</small></div></article>
    <article>🍻 <div><strong>Resenha garantida</strong><small>Futebol, amigos e diversão</small></div></article>
</section>

<section id="meus" class="section-wrap table-section">
    <div class="section-heading"><div><div class="panel-kicker">MINHA AGENDA</div><h2>Meus agendamentos</h2><p class="muted">Acompanhe suas reservas e status.</p></div></div>
    <div class="table-card">
        <div class="table-scroll"><table><thead><tr><th>Data</th><th>Início</th><th>Fim</th><th>Valor</th><th>Pagamento</th><th>Campo</th><th>Status</th><th>Ações</th></tr></thead><tbody>
        <?php if (!$agendamentos): ?><tr><td colspan="8" class="empty">Você ainda não possui agendamentos.</td></tr>
        <?php else: foreach($agendamentos as $a): $classe=$a['status']==='Confirmado'?'confirmed':($a['status']==='Cancelado'?'cancelled':'pending'); ?>
            <tr><td><?= date('d/m/Y', strtotime($a['data'])) ?></td><td><?= date('H:i', strtotime($a['horario_inicio'])) ?></td><td><?= date('H:i', strtotime($a['horario_fim'])) ?></td><td>R$ <?= number_format((float)$a['valor'], 2, ',', '.') ?></td><td><?= htmlspecialchars($a['forma_pagamento'], ENT_QUOTES, 'UTF-8') ?><?= $a['forma_pagamento']==='Pix' ? ' · '.htmlspecialchars($a['pagamento_status'], ENT_QUOTES, 'UTF-8') : '' ?></td><td>Campo 1</td><td><span class="status <?= $classe ?>"><?= htmlspecialchars($a['status'], ENT_QUOTES, 'UTF-8') ?></span></td><td>
            <?php if ($a['forma_pagamento']==='Pix' && $a['pagamento_status']==='Pendente' && $a['status']==='Pendente'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="acao" value="solicitar_confirmacao_pix"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="success-outline small" type="submit">Já realizei o Pix</button></form><?php endif; ?>
            <?php if ($a['status'] !== 'Cancelado'): ?><form method="post" onsubmit="return confirm('Cancelar esta reserva?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="acao" value="cancelar"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="danger-outline small" type="submit">Cancelar</button></form><?php else: ?>—<?php endif; ?></td></tr>
        <?php endforeach; endif; ?>
        </tbody></table></div>
    </div>
</section>
</main>
<footer class="site-footer">© <?= date('Y') ?> Red Star Point Ball · <a href="https://www.instagram.com/redstarpointball/" target="_blank" rel="noopener">Instagram @redstarpointball</a></footer>
<script>
const data=document.getElementById('data'),inicio=document.querySelector('[name=horario_inicio]'),fim=document.querySelector('[name=horario_fim]'),hoje=new Date();data.min=new Date(hoje.getTime()-hoje.getTimezoneOffset()*60000).toISOString().split('T')[0];const pagamento=document.getElementById('forma_pagamento'),pixBox=document.getElementById('pix-box'),pixValor=document.getElementById('pix-valor'),pixQr=document.getElementById('pix-qrcode');function crc16(s){let c=65535;for(let ch of s){c^=ch.charCodeAt(0)<<8;for(let i=0;i<8;i++)c=c&32768?((c<<1)^4129)&65535:(c<<1)&65535}return c.toString(16).toUpperCase().padStart(4,'0')}function tlv(id,value){return id+String(value.length).padStart(2,'0')+value}function atualizarPix(){const selecionado=pagamento.value==='Pix';pixBox.hidden=!selecionado;if(!selecionado){pixQr.removeAttribute('src');return}let valor=null;if(inicio.value&&fim.value){const minutos=(new Date('1970-01-01T'+fim.value)-new Date('1970-01-01T'+inicio.value))/60000;if(minutos<60||minutos%60){pixValor.textContent='Escolha períodos inteiros de 1 hora.';pixQr.removeAttribute('src');return}valor=(minutos/60*120).toFixed(2);pixValor.textContent='Valor a pagar: R$ '+valor.replace('.',',')}else{pixValor.textContent='QR Code Pix pronto. O valor será calculado ao escolher os horários.'}const contaPix=tlv('00','br.gov.bcb.pix')+tlv('01','10323879985');let payload=tlv('00','01')+tlv('26',contaPix)+tlv('52','0000')+tlv('53','986')+(valor?tlv('54',valor):'')+tlv('58','BR')+tlv('59','RED STAR')+tlv('60','SAO PAULO')+tlv('62',tlv('05','***'))+'6304';payload+=crc16(payload);pixQr.src='https://api.qrserver.com/v1/create-qr-code/?size=180x180&data='+encodeURIComponent(payload)}pagamento.addEventListener('change',atualizarPix);inicio.addEventListener('change',atualizarPix);fim.addEventListener('change',atualizarPix);atualizarPix();
</script>
</body></html>
