<?php
require_once 'app.php'; $u=auth();
if($u['tipo']==='admin') redirect('admin.php');
$_SESSION['usuario_nome']=$u['nome']; $_SESSION['usuario_telefone']=$u['telefone']??'';
if($_SERVER['REQUEST_METHOD']==='POST') {
 try {
  csrf(); $acao=$_POST['acao']??'';$id=(int)($_POST['id']??0);
  if($acao==='agendar') {
   $id=createBooking($u,['data'=>$_POST['data']??'','inicio'=>$_POST['horario_inicio']??'','fim'=>$_POST['horario_fim']??'','pagamento'=>$_POST['forma_pagamento']??'','cupom'=>$_POST['cupom']??'']);
   notice('Reserva #'.$id.' enviada! Aguarde a confirmação do administrador.');
  } elseif($acao==='cancelar') {
   atomic(function()use($u,$id) { $a=receipt((int)$u['id'],$id);if(!in_array($a['status'],['Pendente','Confirmado'],true) || strtotime($a['data'].' '.$a['horario_inicio'])<=time()) throw new DomainException('Esta reserva não pode mais ser cancelada.');query("UPDATE agendamentos SET status='Cancelado' WHERE id=? AND usuario_id=?",[$id,$u['id']]); });notice('Reserva cancelada.');
  } elseif($acao==='solicitar_confirmacao_pix') {
   $s=query("UPDATE agendamentos SET pagamento_status='Solicitado' WHERE id=? AND usuario_id=? AND forma_pagamento='Pix' AND status='Pendente' AND pagamento_status='Pendente'",[$id,$u['id']]);if(!$s->rowCount()) throw new DomainException('Não é possível solicitar esse pagamento.');notice('Confirmação do Pix enviada ao administrador.');
  }
 }catch(Throwable $ex){handleError($ex);} redirect('usuario.php#meus');
}
$agendamentos=query('SELECT a.*,v.id avaliacao_id FROM agendamentos a LEFT JOIN avaliacoes v ON v.agendamento_id=a.id WHERE a.usuario_id=? ORDER BY a.data DESC,a.horario_inicio DESC',[$u['id']])->fetchAll();
$meusCupons=query('SELECT c.*,uc.concedido_em,(SELECT COUNT(*) FROM usos_cupons uso WHERE uso.cupom_id=c.id AND uso.usuario_id=uc.usuario_id) utilizado,(SELECT COUNT(*) FROM usos_cupons uso WHERE uso.cupom_id=c.id) total_usos FROM usuario_cupons uc JOIN cupons c ON c.id=uc.cupom_id WHERE uc.usuario_id=?',[$u['id']])->fetchAll();
$mensagem='';$tipoMensagem='success';
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
    <nav class="nav-tabs"><a href="#agendar" class="active">▣ Agendar</a><a href="#meus">☷ Meus agendamentos</a><a href="calendario.php">▣ Calendário</a><a href="perfil.php">Meu Perfil</a><a href="#cupons">Meus Cupons</a><a href="index.php">Início</a></nav>
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
        <?php showNotice(); ?><?php if ($mensagem): ?><div class="alert <?= $tipoMensagem === 'danger' ? 'danger' : 'success' ?>"><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <form method="post" class="booking-grid">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="acao" value="agendar">
            <label>Data<input type="date" name="data" id="data" required></label>
            <label>Início<input type="time" name="horario_inicio" required></label>
            <label>Fim<input type="time" name="horario_fim" required></label>
            <label class="full">Forma de pagamento<select name="forma_pagamento" id="forma_pagamento" required><option value="" selected disabled>Selecione uma opção</option><option value="Pix">Pix</option><option value="Dinheiro">Dinheiro (pagar no jogo)</option><option value="Cartão">Cartão (pagar no jogo)</option></select></label>
            <label class="full">Cupom de desconto<div class="coupon-input"><input name="cupom" id="cupom" maxlength="30" placeholder="Ex.: REDSTAR10"><button type="button" class="outline-btn" id="aplicar-cupom">Aplicar</button></div></label>
            <div class="price-summary full" id="resumo-valor" aria-live="polite">Escolha a data e os horários para calcular o valor.</div>
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

<section id="cupons" class="section-wrap landing-section"><h2>Meus Cupons</h2><div class="reviews-grid"><?php if(!$meusCupons):?><p class="muted">Os cupons de boas-vindas são concedidos às novas contas.</p><?php endif;foreach($meusCupons as $c):$estado=$c['utilizado']?'Utilizado':(!$c['ativo']?'Desativado':(($c['validade']&&$c['validade']<date('Y-m-d'))?'Vencido':(($c['max_usos']!==null && $c['total_usos']>=$c['max_usos'])?'Esgotado':(count($agendamentos)>0?'Primeira reserva já realizada':'Disponível'))));?><article class="review-card"><div class="panel-kicker">CUPOM DE BOAS-VINDAS</div><h3><?=e($c['codigo'])?></h3><strong><?=e($c['porcentagem'])?>% de desconto</strong><p>Status: <?=e($estado)?></p><?php if($c['validade']):?><small>Válido até <?=date('d/m/Y',strtotime($c['validade']))?></small><?php endif;?></article><?php endforeach;?></div></section>
<section id="meus" class="section-wrap table-section">
    <div class="section-heading"><div><div class="panel-kicker">MINHA AGENDA</div><h2>Meus agendamentos</h2><p class="muted">Acompanhe suas reservas e status.</p></div></div>
    <div class="table-card">
        <div class="table-scroll"><table><thead><tr><th>Data</th><th>Início</th><th>Fim</th><th>Valor</th><th>Pagamento</th><th>Campo</th><th>Status</th><th>Ações</th></tr></thead><tbody>
        <?php if (!$agendamentos): ?><tr><td colspan="8" class="empty">Você ainda não possui agendamentos.</td></tr>
        <?php else: foreach($agendamentos as $a): $classe=in_array($a['status'],['Confirmado','Concluído'],true)?'confirmed':($a['status']==='Cancelado'?'cancelled':'pending'); ?>
            <tr><td><?= date('d/m/Y', strtotime($a['data'])) ?></td><td><?= date('H:i', strtotime($a['horario_inicio'])) ?></td><td><?= date('H:i', strtotime($a['horario_fim'])) ?></td><td>R$ <?= number_format((float)$a['valor'], 2, ',', '.') ?></td><td><?= htmlspecialchars($a['forma_pagamento'], ENT_QUOTES, 'UTF-8') ?><?= $a['forma_pagamento']==='Pix' ? ' · '.htmlspecialchars($a['pagamento_status'], ENT_QUOTES, 'UTF-8') : '' ?></td><td>Campo 1</td><td><span class="status <?= $classe ?>"><?= htmlspecialchars($a['status'], ENT_QUOTES, 'UTF-8') ?></span></td><td>
            <?php if ($a['forma_pagamento']==='Pix' && $a['pagamento_status']==='Pendente' && $a['status']==='Pendente'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="acao" value="solicitar_confirmacao_pix"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="success-outline small" type="submit">Já realizei o Pix</button></form><?php endif; ?>
            <a class="outline-btn small" href="comprovante.php?id=<?=(int)$a['id']?>">Ver comprovante</a><?php if($a['status']==='Concluído' && !$a['avaliacao_id']):?><a class="success-outline small" href="avaliar.php?id=<?=(int)$a['id']?>">Avaliar Arena ★</a><?php endif;?>
            <?php if (in_array($a['status'],['Pendente','Confirmado'],true) && strtotime($a['data'].' '.$a['horario_inicio'])>time()): ?><form method="post" onsubmit="return confirm('Cancelar esta reserva?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="acao" value="cancelar"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="danger-outline small" type="submit">Cancelar</button></form><?php else: ?>—<?php endif; ?></td></tr>
        <?php endforeach; endif; ?>
        </tbody></table></div>
    </div>
</section>
</main>
<footer class="site-footer">© <?= date('Y') ?> Red Star Point Ball · <a href="https://www.instagram.com/redstarpointball/" target="_blank" rel="noopener">Instagram @redstarpointball</a></footer>
<script src="reserva.js"></script>
</body></html>
