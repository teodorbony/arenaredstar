<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['usuario_id'])) { header('Location: login.php'); exit; }
if (($_SESSION['usuario_tipo'] ?? '') !== 'admin') { header('Location: usuario.php'); exit; }
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

function adminFlash(string $msg, string $tipo='success'): void {
    $_SESSION['flash_admin'] = $msg; $_SESSION['flash_admin_type'] = $tipo;
    header('Location: admin.php'); exit;
}
$mensagem=$_SESSION['flash_admin']??''; $tipoMensagem=$_SESSION['flash_admin_type']??'success'; unset($_SESSION['flash_admin'],$_SESSION['flash_admin_type']);

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) adminFlash('Sessão expirada. Atualize a página.', 'danger');
    $acao=$_POST['acao']??'';

    if ($acao==='adicionar') {
        $nome=trim($_POST['nome_cliente']??''); $tel=trim($_POST['telefone']??''); $data=$_POST['data']??''; $inicio=$_POST['horario_inicio']??''; $fim=$_POST['horario_fim']??''; $status=$_POST['status']??'Pendente'; $campo='Campo 1';
        if ($nome===''||$tel===''||$data===''||$inicio===''||$fim==='') adminFlash('Preencha todos os campos.', 'danger');
        if (!in_array($status,['Pendente','Confirmado','Cancelado'],true)) $status='Pendente';
        $d=DateTime::createFromFormat('Y-m-d',$data); if(!$d||$d->format('Y-m-d')!==$data) adminFlash('Data inválida.','danger');
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$inicio) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$fim) || $fim <= $inicio) adminFlash('Informe um período válido.','danger');
        if ($status!=='Cancelado') {
            $c=$pdo->prepare("SELECT COUNT(*) FROM agendamentos WHERE data=:data AND campo='Campo 1' AND status <> 'Cancelado' AND horario_inicio < :fim AND horario_fim > :inicio"); $c->execute(['data'=>$data,'inicio'=>$inicio,'fim'=>$fim]);
            if((int)$c->fetchColumn()>0) adminFlash('Já existe uma reserva ativa nesse período.','danger');
        }
        $stmt=$pdo->prepare('INSERT INTO agendamentos (usuario_id,nome_cliente,telefone,data,horario_inicio,horario_fim,campo,status) VALUES (NULL,:nome,:telefone,:data,:inicio,:fim,:campo,:status)');
        $stmt->execute(['nome'=>$nome,'telefone'=>$tel,'data'=>$data,'inicio'=>$inicio,'fim'=>$fim,'campo'=>$campo,'status'=>$status]); adminFlash('Agendamento adicionado.');
    }

    $id=filter_input(INPUT_POST,'id',FILTER_VALIDATE_INT); if(!$id) adminFlash('Agendamento inválido.','danger');
    if($acao==='confirmar' || $acao==='confirmar_pix'){
        $alvo=$pdo->prepare('SELECT data,horario_inicio,horario_fim,forma_pagamento,pagamento_status FROM agendamentos WHERE id=:id'); $alvo->execute(['id'=>$id]); $reserva=$alvo->fetch();
        if(!$reserva) adminFlash('Agendamento não encontrado.','danger');
        if($reserva['forma_pagamento']==='Pix' && $acao!=='confirmar_pix') adminFlash('Para Pix, confirme primeiro o pagamento enviado pelo cliente.','danger');
        if($acao==='confirmar_pix' && ($reserva['forma_pagamento']!=='Pix'||$reserva['pagamento_status']!=='Solicitado')) adminFlash('O cliente ainda não solicitou a confirmação desse Pix.','danger');
        $c=$pdo->prepare("SELECT COUNT(*) FROM agendamentos WHERE id<>:id AND data=:data AND status='Confirmado' AND horario_inicio < :fim AND horario_fim > :inicio"); $c->execute(['id'=>$id,'data'=>$reserva['data'],'inicio'=>$reserva['horario_inicio'],'fim'=>$reserva['horario_fim']]);
        if((int)$c->fetchColumn()>0) adminFlash('Não é possível confirmar: já existe um horário confirmado que se sobrepõe nesse dia.','danger');
        $stmt=$pdo->prepare("UPDATE agendamentos SET status='Confirmado', pagamento_status=CASE WHEN forma_pagamento='Pix' THEN 'Confirmado' ELSE pagamento_status END WHERE id=:id");$stmt->execute(['id'=>$id]);adminFlash($acao==='confirmar_pix'?'Pix e agendamento confirmados.':'Agendamento confirmado.');
    }
    if($acao==='cancelar'){$stmt=$pdo->prepare("UPDATE agendamentos SET status='Cancelado' WHERE id=:id");$stmt->execute(['id'=>$id]);adminFlash('Agendamento cancelado.');}
    if($acao==='excluir'){$stmt=$pdo->prepare('DELETE FROM agendamentos WHERE id=:id');$stmt->execute(['id'=>$id]);adminFlash('Agendamento excluído.');}
}

$agendamentos=$pdo->query('SELECT id,usuario_id,nome_cliente,telefone,data,horario_inicio,horario_fim,forma_pagamento,valor,pagamento_status,campo,status FROM agendamentos ORDER BY data ASC, horario_inicio ASC')->fetchAll();
$total=count($agendamentos);$confirmados=count(array_filter($agendamentos,fn($a)=>$a['status']==='Confirmado'));$pendentes=count(array_filter($agendamentos,fn($a)=>$a['status']==='Pendente'));$cancelados=count(array_filter($agendamentos,fn($a)=>$a['status']==='Cancelado'));
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin | Red Star Point Ball</title><link rel="stylesheet" href="style.css"></head>
<body class="dashboard-page admin-page">
<header class="topbar"><a class="brand-mini" href="admin.php"><img src="assets/logo-red-star.png" alt="Red Star"><span>Red Star<br><small>Point Ball</small></span></a><nav class="nav-tabs"><a href="#painel" class="active">▣ Painel</a><a href="#agenda">▣ Agendamentos</a><a href="calendario.php">▣ Calendário</a></nav><div class="user-actions"><span>👤 Olá, Admin</span><a href="logout.php">↪ Sair</a></div></header>
<main class="section-wrap admin-main" id="painel">
    <div class="admin-title"><div><div class="panel-kicker">GESTÃO RED STAR</div><h1>Painel Administrativo</h1><p class="muted">Gerencie os agendamentos do único campo da arena.</p></div></div>
    <?php if($mensagem):?><div class="alert <?= $tipoMensagem==='danger'?'danger':'success' ?>"><?=htmlspecialchars($mensagem,ENT_QUOTES,'UTF-8')?></div><?php endif;?>
    <section class="stats"><article><span>☷</span><div><small>Total</small><strong><?=$total?></strong></div></article><article class="green"><span>●</span><div><small>Confirmados</small><strong><?=$confirmados?></strong></div></article><article class="yellow"><span>●</span><div><small>Pendentes</small><strong><?=$pendentes?></strong></div></article><article class="red"><span>●</span><div><small>Cancelados</small><strong><?=$cancelados?></strong></div></article></section>

    <section class="admin-form-card">
        <div class="section-heading"><div><div class="panel-kicker">NOVO REGISTRO</div><h2>Adicionar agendamento manual</h2></div></div>
        <form method="post" class="admin-form-grid">
            <input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'],ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="acao" value="adicionar">
            <label>Cliente<input type="text" name="nome_cliente" required placeholder="Nome do cliente"></label><label>Telefone<input type="text" name="telefone" id="telefone" required placeholder="(00) 00000-0000"></label><label>Data<input type="date" name="data" id="data" required></label><label>Início<input type="time" name="horario_inicio" required></label><label>Fim<input type="time" name="horario_fim" required></label><label>Campo<div class="fixed-field compact">⚽ Campo 1 (Único)</div></label><label>Status<select name="status"><option>Pendente</option><option>Confirmado</option><option>Cancelado</option></select></label><button class="primary-btn" type="submit">Adicionar reserva +</button>
        </form>
    </section>

    <section class="table-section" id="agenda"><div class="section-heading"><div><div class="panel-kicker">AGENDA</div><h2>Todos os agendamentos</h2></div><span class="count-badge"><?=$total?> registro(s)</span></div><div class="table-card"><div class="table-scroll"><table><thead><tr><th>Cliente</th><th>Telefone</th><th>Data</th><th>Início</th><th>Fim</th><th>Valor</th><th>Pagamento</th><th>Campo</th><th>Status</th><th>Ações</th></tr></thead><tbody>
    <?php if(!$agendamentos):?><tr><td colspan="10" class="empty">Nenhum agendamento cadastrado.</td></tr><?php else:foreach($agendamentos as $a):$classe=$a['status']==='Confirmado'?'confirmed':($a['status']==='Cancelado'?'cancelled':'pending');?><tr><td><strong><?=htmlspecialchars($a['nome_cliente'],ENT_QUOTES,'UTF-8')?></strong></td><td><?=htmlspecialchars($a['telefone'],ENT_QUOTES,'UTF-8')?></td><td><?=date('d/m/Y',strtotime($a['data']))?></td><td><?=date('H:i',strtotime($a['horario_inicio']))?></td><td><?=date('H:i',strtotime($a['horario_fim']))?></td><td>R$ <?=number_format((float)$a['valor'],2,',','.')?></td><td><?=htmlspecialchars($a['forma_pagamento'],ENT_QUOTES,'UTF-8')?><?= $a['forma_pagamento']==='Pix'?' · '.htmlspecialchars($a['pagamento_status'],ENT_QUOTES,'UTF-8'):''?></td><td>Campo 1</td><td><span class="status <?=$classe?>"><?=htmlspecialchars($a['status'],ENT_QUOTES,'UTF-8')?></span></td><td><div class="action-set">
    <?php if($a['status']!=='Confirmado' && $a['forma_pagamento']!=='Pix'):?><form method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'],ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="acao" value="confirmar"><input type="hidden" name="id" value="<?=(int)$a['id']?>"><button class="success-outline small">Confirmar</button></form><?php endif;?>
    <?php if($a['status']!=='Confirmado' && $a['forma_pagamento']==='Pix' && $a['pagamento_status']==='Solicitado'):?><form method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'],ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="acao" value="confirmar_pix"><input type="hidden" name="id" value="<?=(int)$a['id']?>"><button class="success-outline small">Confirmar Pix</button></form><?php endif;?>
    <?php if($a['status']!=='Cancelado'):?><form method="post"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'],ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="acao" value="cancelar"><input type="hidden" name="id" value="<?=(int)$a['id']?>"><button class="warning-outline small">Cancelar</button></form><?php endif;?>
    <form method="post" onsubmit="return confirm('Excluir definitivamente este agendamento?');"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'],ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?=(int)$a['id']?>"><button class="danger-outline small">Excluir</button></form>
    </div></td></tr><?php endforeach;endif;?></tbody></table></div></div></section>
</main>
<footer class="site-footer">© <?=date('Y')?> Red Star Point Ball · <a href="https://www.instagram.com/redstarpointball/" target="_blank" rel="noopener">@redstarpointball</a></footer>
<script>const data=document.getElementById('data');const hoje=new Date();data.min=new Date(hoje.getTime()-hoje.getTimezoneOffset()*60000).toISOString().split('T')[0];const tel=document.getElementById('telefone');tel.addEventListener('input',function(){let v=this.value.replace(/\D/g,'').slice(0,11);if(v.length>10)v=v.replace(/(\d{2})(\d{5})(\d{4})/,'($1) $2-$3');else if(v.length>6)v=v.replace(/(\d{2})(\d{4})(\d{0,4})/,'($1) $2-$3');else if(v.length>2)v=v.replace(/(\d{2})(\d+)/,'($1) $2');this.value=v;});</script>
</body></html>
