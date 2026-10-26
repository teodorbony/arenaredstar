<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__.'/icons.php';
require_once __DIR__.'/db.php';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function money($v): string { return 'R$ '.number_format((float)$v, 2, ',', '.'); }
function query(string $sql, array $params=[]): PDOStatement { global $pdo; $s=$pdo->prepare($sql); $s->execute($params); return $s; }
function auth(bool $admin=false): array {
    if (empty($_SESSION['usuario_id'])) { header('Location: login.php'); exit; }
    $u=query('SELECT * FROM usuarios WHERE id=?', [$_SESSION['usuario_id']])->fetch();
    if (!$u) { session_destroy(); header('Location: login.php'); exit; }
    if ($admin && $u['tipo'] !== 'admin') { http_response_code(403); exit('Acesso restrito ao administrador.'); }
    return $u;
}
function csrf(): void { if (!is_string($_POST['csrf_token']??null) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) throw new DomainException('Sessão expirada. Atualize a página.'); }
function csrfField(): void { echo '<input type="hidden" name="csrf_token" value="'.e($_SESSION['csrf_token']).'">'; }
function notice(string $message, string $type='success'): void { $_SESSION['notice']=[$message,$type]; }
function showNotice(): void { if(isset($_SESSION['notice'])) { [$m,$t]=$_SESSION['notice']; unset($_SESSION['notice']); echo '<div role="status" class="alert '.e($t).'">'.e($m).'</div>'; } }
function redirect(string $path): void { header('Location: '.$path); exit; }
function renderTopbar(): void {
    $logged = !empty($_SESSION['usuario_id']);
    $admin = $logged && (($_SESSION['usuario_tipo'] ?? '') === 'admin');
    $current = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
    $brandHref = $admin ? 'admin.php' : ($logged ? 'usuario.php' : 'index.php');
    if ($admin) {
        $links = [
            ['admin.php', 'Painel'],
            ['agendamentos.php', 'Agendamentos'],
            ['calendario.php', 'Calendário'],
            ['bloqueios.php', 'Bloqueios'],
            ['cupons.php', 'Cupons'],
            ['financeiro.php', 'Financeiro'],
            ['perfil.php', 'Meu Perfil']
        ];
    } elseif ($logged) {
        $links = [
            ['index.php', 'Início'],
            ['usuario.php#agendar', 'Agendar'],
            ['meus-agendamentos.php', 'Meus agendamentos'],
            ['calendario.php', 'Calendário'],
            ['perfil.php', 'Meu Perfil']
        ];
    } else {
        $links = [
            ['index.php', 'Início'],
            ['login.php', 'Entrar'],
            ['cadastro.php', 'Criar conta']
        ];
    }
    ?>
<header class="topbar">
    <a class="brand-mini" href="<?= e($brandHref) ?>"><img src="assets/logo-red-star.png" alt="Arena Red Star"><span>Red Star<br><small>Point Ball</small></span></a>
    <nav class="nav-tabs">
        <?php foreach ($links as [$href, $label]): $path = basename(parse_url($href, PHP_URL_PATH)); ?>
            <a href="<?= e($href) ?>"<?= $current === $path ? ' class="active"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <?php if ($logged): ?>
        <div class="user-actions"><span><?= rs_icon('user') ?> Olá, <?= e(explode(' ', trim($_SESSION['usuario_nome'] ?? 'Usuário'))[0]) ?></span><a href="logout.php"><?= rs_icon('logout') ?> Sair</a></div>
    <?php endif; ?>
</header>
<?php
}
function pageStart(string $title): void { ?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($title)?> | Arena Red Star</title><link rel="stylesheet" href="style.css"><link rel="stylesheet" href="interface.css"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@700;800&family=Manrope:wght@400;500;600;700;800&display=swap"><link rel="stylesheet" href="tema.css"><link rel="stylesheet" href="profissional.css"></head><body class="dashboard-page"><?php renderTopbar(); ?><main class="section-wrap content-page"><?php showNotice(); }
function pageEnd(): void { ?></main><footer class="site-footer">© <?=date('Y')?> Arena Red Star · <a href="https://www.instagram.com/redstarpointball/" target="_blank" rel="noopener">@redstarpointball</a></footer></body></html><?php }
function validDate(string $s): bool { $d=DateTimeImmutable::createFromFormat('!Y-m-d',$s); return $d && $d->format('Y-m-d')===$s; }
function period(string $date, string $start, string $end, bool $future=true): int {
    $validStart = preg_match('/^(?:[01]\d|2[0-3]):00$/', $start);
    $validEnd = $end === '24:00' || preg_match('/^(?:[01]\d|2[0-3]):00$/', $end);
    if (!validDate($date) || !$validStart || !$validEnd) throw new DomainException('Escolha um horário válido em hora cheia.');
    $weekday = (int)date('N', strtotime($date));
    $opening = $weekday <= 5 ? '18:00' : '14:00';
    if ($start < $opening) throw new DomainException($weekday <= 5 ? 'Em dias de semana, os agendamentos começam às 18h.' : 'Nos finais de semana, os agendamentos começam às 14h.');
    $startMinutes = ((int)substr($start, 0, 2) * 60) + (int)substr($start, 3, 2);
    $endMinutes = $end === '24:00' ? 1440 : ((int)substr($end, 0, 2) * 60) + (int)substr($end, 3, 2);
    if ($endMinutes <= $startMinutes) throw new DomainException('O horário final deve ser posterior ao início.');
    if ($future && strtotime("$date $start") <= time()) throw new DomainException('Escolha um horário futuro.');
    $minutes = $endMinutes - $startMinutes;
    if ($minutes < 60 || $minutes % 60 !== 0) throw new DomainException('A reserva deve ter períodos inteiros de 1 hora.');
    return $minutes;
}
function available(string $date,string $start,string $end,int $exclude=0,int $blockExclude=0,bool $checkBookings=true): void {
    if($checkBookings && query("SELECT id FROM agendamentos WHERE data=? AND status<>'Cancelado' AND horario_inicio<? AND horario_fim>? AND id<>? LIMIT 1",[$date,$end,$start,$exclude])->fetch()) throw new DomainException('Esse período já possui uma reserva. Escolha outro.');
    if(query('SELECT id FROM bloqueios_horarios WHERE data=? AND hora_inicio<? AND hora_fim>? AND id<>? LIMIT 1',[$date,$end,$start,$blockExclude])->fetch()) throw new DomainException('Esse período já está bloqueado pela administração.');
}
// Todas as alterações da agenda/cupons usam a mesma trava; inclusive intervalos ainda vazios.
function atomic(callable $fn) {
    global $pdo;
    if((int)query("SELECT GET_LOCK('arena_red_star_agenda',10)")->fetchColumn()!==1) throw new DomainException('Sistema ocupado. Tente novamente.');
    try { $pdo->beginTransaction(); $result=$fn(); $pdo->commit(); return $result; }
    catch(Throwable $ex) { if($pdo->inTransaction()) $pdo->rollBack(); throw $ex; }
    finally { query("SELECT RELEASE_LOCK('arena_red_star_agenda')"); }
}
function quote(int $uid,string $date,string $start,string $end,string $code=''): array {
    $minutes=period($date,$start,$end); $original=$minutes*200; $discount=0; $coupon=null;
    $code=strtoupper(trim($code));
    if($code!=='') {
        $coupon=query('SELECT * FROM cupons WHERE codigo=?',[$code])->fetch();
        if(!$coupon || !$coupon['ativo'] || ($coupon['validade'] && $coupon['validade']<date('Y-m-d'))) throw new DomainException('Cupom inválido, desativado ou vencido.');
        if(query('SELECT id FROM usos_cupons WHERE cupom_id=? AND usuario_id=?',[$coupon['id'],$uid])->fetch()) throw new DomainException('Você já utilizou este cupom.');
        if($coupon['max_usos']!==null && (int)query('SELECT COUNT(*) FROM usos_cupons WHERE cupom_id=?',[$coupon['id']])->fetchColumn()>=(int)$coupon['max_usos']) throw new DomainException('Este cupom atingiu o limite de usos.');
        if($code==='REDSTAR10') {
            if(!query('SELECT usuario_id FROM usuario_cupons WHERE usuario_id=? AND cupom_id=?',[$uid,$coupon['id']])->fetch()) throw new DomainException('Este benefício é exclusivo de contas novas.');
            if((int)query('SELECT COUNT(*) FROM agendamentos WHERE usuario_id=?',[$uid])->fetchColumn()>0) throw new DomainException('REDSTAR10 vale somente para sua primeira reserva.');
        }
        $discount=(int)round($original*(float)$coupon['porcentagem']/100);
    }
    return ['original'=>$original/100,'desconto'=>$discount/100,'total'=>($original-$discount)/100,'cupom'=>$coupon,'minutos'=>$minutes];
}
function createBooking(array $u,array $in): int {
    return atomic(function()use($u,$in) {
        $q=quote((int)$u['id'],$in['data'],$in['inicio'],$in['fim'],$in['cupom']??'');
        available($in['data'],$in['inicio'],$in['fim']);
        if(!in_array($in['pagamento'],['Pix','Dinheiro','Cartão'],true)) throw new DomainException('Selecione uma forma de pagamento.');
        query("INSERT INTO agendamentos(usuario_id,nome_cliente,telefone,data,horario_inicio,horario_fim,forma_pagamento,valor,valor_original,desconto,cupom_codigo,pagamento_status,campo,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,'Campo 1','Pendente')",[$u['id'],$u['nome'],$u['telefone']??'',$in['data'],$in['inicio'],$in['fim'],$in['pagamento'],$q['total'],$q['original'],$q['desconto'],$q['cupom']['codigo']??null,$q['total']==0?'Confirmado':'Pendente']);
        global $pdo; $id=(int)$pdo->lastInsertId();
        if($q['cupom']) query('INSERT INTO usos_cupons(cupom_id,usuario_id,agendamento_id) VALUES(?,?,?)',[$q['cupom']['id'],$u['id'],$id]);
        return $id;
    });
}
function addReview(int $uid,int $id,int $score,string $comment): void {
    if($score<1 || $score>5 || mb_strlen($comment)>2000) throw new DomainException('Escolha de 1 a 5 estrelas e um comentário de até 2000 caracteres.');
    atomic(function()use($uid,$id,$score,$comment){
        $r=query("SELECT id FROM agendamentos WHERE id=? AND usuario_id=? AND status<>'Cancelado'",[$id,$uid])->fetch();
        if(!$r) throw new DomainException('Escolha uma reserva válida da sua conta para avaliar.');
        if(query('SELECT id FROM avaliacoes WHERE agendamento_id=?',[$id])->fetch()) throw new DomainException('Esta reserva já foi avaliada.');
        query('INSERT INTO avaliacoes(usuario_id,agendamento_id,nota,comentario) VALUES(?,?,?,?)',[$uid,$id,$score,trim($comment)]);
    });
}
function receipt(int $uid,int $id): array {
    $r=query('SELECT * FROM agendamentos WHERE id=? AND usuario_id=?',[$id,$uid])->fetch();
    if(!$r) throw new DomainException('Comprovante não encontrado.'); return $r;
}
function financial(array $f): array {
    $where=[];$args=[];
    foreach(['de'=>'>=','ate'=>'<='] as $k=>$op) if(!empty($f[$k])) { if(!validDate($f[$k])) throw new DomainException('Data de filtro inválida.'); $where[]="data $op ?";$args[]=$f[$k]; }
    if(!empty($f['de']) && !empty($f['ate']) && $f['de']>$f['ate']) throw new DomainException('O início deve ser anterior ao fim.');
    $status=$f['status']??''; if($status!=='') {
        if(!in_array($status,['Pendente','Solicitado','Confirmado','Cancelado'],true)) throw new DomainException('Status inválido.');
        if($status==='Cancelado') $where[]="status='Cancelado'"; else { $where[]="status<>'Cancelado' AND pagamento_status=?";$args[]=$status; }
    }
    if(!empty($f['forma'])) { if(!in_array($f['forma'],['Pix','Dinheiro','Cartão'],true)) throw new DomainException('Forma inválida.'); $where[]='forma_pagamento=?';$args[]=$f['forma']; }
    return query('SELECT * FROM agendamentos'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY data DESC,id DESC',$args)->fetchAll();
}
function handleError(Throwable $ex): void {
    if($ex instanceof DomainException) notice($ex->getMessage(),'danger'); else { error_log((string)$ex); notice('Não foi possível concluir. Confira a atualização do banco e tente novamente.','danger'); }
}
