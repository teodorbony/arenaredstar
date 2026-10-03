<?php
// Execute somente em um banco descartável com nome terminado em _test.
if(PHP_SAPI!=='cli') { http_response_code(403); exit; }
if(!str_ends_with(getenv('ARENA_DB_NAME')?:'', '_test')) exit("Defina ARENA_DB_NAME para um banco descartável terminado em _test.\n");
require __DIR__.'/../app.php';require __DIR__.'/../admin_actions.php';
$n=0;
function check(bool $ok,string $label):void{global $n;if(!$ok)throw new RuntimeException('Falhou: '.$label);$n++;echo "OK $label\n";}
function denied(callable $fn,string $label):void{try{$fn();}catch(DomainException $ex){check(true,$label);return;}throw new RuntimeException('Não bloqueou: '.$label);}
function user(string $name):array{global $pdo;$tag=bin2hex(random_bytes(4));query("INSERT INTO usuarios(nome,telefone,email,senha,tipo) VALUES(?,?,?,?,'cliente')",[$name,'11999999999',"$tag@test.invalid",password_hash('teste123',PASSWORD_DEFAULT)]);$id=(int)$pdo->lastInsertId();query("INSERT INTO usuario_cupons(usuario_id,cupom_id) SELECT ?,id FROM cupons WHERE codigo='REDSTAR10'",[$id]);return query('SELECT * FROM usuarios WHERE id=?',[$id])->fetch();}
$u=user('Teste Novo');$v=user('Teste Outro');$date=date('Y-m-d',strtotime('+10 days'));$next=date('Y-m-d',strtotime('+11 days'));
$input=['data'=>$date,'inicio'=>'10:00','fim'=>'11:00','pagamento'=>'Pix','cupom'=>'REDSTAR10'];
$q=quote($u['id'],$date,'10:00','11:00','REDSTAR10');check($q['total']==108 && $q['original']==120 && $q['desconto']==12,'10% de R$ 120 resulta em R$ 108');
$q=quote($u['id'],$date,'10:00','12:00','REDSTAR10');check($q['total']==216,'Duas horas custam R$ 216 com REDSTAR10');
check((int)query('SELECT COUNT(*) FROM usos_cupons WHERE usuario_id=?',[$u['id']])->fetchColumn()===0,'Prévia não consome cupom');
query('INSERT INTO bloqueios_horarios(data,hora_inicio,hora_fim,motivo) VALUES(?,?,?,?)',[$date,'10:00','11:00','Manutenção de teste']);
denied(fn()=>createBooking($u,$input),'Reserva bloqueada');check((int)query('SELECT COUNT(*) FROM usos_cupons WHERE usuario_id=?',[$u['id']])->fetchColumn()===0,'Falha não consome benefício');
$input['inicio']='11:00';$input['fim']='12:00';$input['valor']=0;$input['desconto']=100;$id=createBooking($u,$input);$a=receipt($u['id'],$id);
check((float)$a['valor']===108.0,'Valor manipulado é ignorado');check($a['pagamento_status']==='Pendente' && $a['status']==='Pendente','Reserva e Pix iniciam pendentes');
check((int)query('SELECT COUNT(*) FROM usos_cupons WHERE usuario_id=?',[$u['id']])->fetchColumn()===1,'Reserva válida registra um uso');
denied(fn()=>quote($u['id'],$date,'12:00','13:00','REDSTAR10'),'REDSTAR10 não pode ser usado duas vezes');
denied(fn()=>createBooking($v,$input),'Reserva sobreposta negada');denied(fn()=>receipt($v['id'],$id),'Comprovante de outro usuário negado');denied(fn()=>addReview($v['id'],$id,5,''),'Avaliação de outro usuário negada');addReview($u['id'],$id,5,'Avaliação imediata');check((int)query('SELECT COUNT(*) FROM avaliacoes WHERE agendamento_id=?',[$id])->fetchColumn()===1,'Reserva ativa recebe avaliação a qualquer momento');
$unused=user('Falha no pagamento');$bad=$input;$bad['data']=$next;$bad['pagamento']='invalido';denied(fn()=>createBooking($unused,$bad),'Forma de pagamento inválida negada');check((int)query('SELECT COUNT(*) FROM usos_cupons WHERE usuario_id=?',[$unused['id']])->fetchColumn()===0,'Transação inválida não registra uso');
adminAction(['acao'=>'cancelar','id'=>$id]);denied(fn()=>adminAction(['acao'=>'confirmar_pix','id'=>$id]),'Reserva cancelada não ressuscita');denied(fn()=>quote($u['id'],$next,'12:00','13:00','REDSTAR10'),'Cancelamento mantém histórico do cupom');
$input['cupom']='';$input['data']=$next;$input['inicio']='13:00';$input['fim']='14:00';$id2=createBooking($v,$input);
query("UPDATE agendamentos SET pagamento_status='Solicitado' WHERE id=?",[$id2]);adminAction(['acao'=>'confirmar_pix','id'=>$id2]);$a=receipt($v['id'],$id2);check($a['status']==='Confirmado'&&$a['pagamento_status']==='Confirmado','Admin confirma Pix e reserva');denied(fn()=>adminAction(['acao'=>'concluir','id'=>$id2]),'Reserva futura não pode ser concluída');
query('UPDATE agendamentos SET data=? WHERE id=?',[date('Y-m-d',strtotime('-2 days')),$id2]);adminAction(['acao'=>'concluir','id'=>$id2]);addReview($v['id'],$id2,5,'<script>alert(1)</script>');check((int)query('SELECT COUNT(*) FROM avaliacoes WHERE agendamento_id=?',[$id2])->fetchColumn()===1,'Reserva concluída recebe avaliação');denied(fn()=>addReview($v['id'],$id2,4,''),'Avaliação duplicada negada');denied(fn()=>addReview($v['id'],$id2,0,''),'Nota zero negada');denied(fn()=>addReview($v['id'],$id2,6,''),'Nota seis negada');check(str_contains(e('<script>'),'&lt;script&gt;'),'HTML escapado');
query("INSERT INTO cupons(codigo,porcentagem,validade) VALUES('TESTEVENCIDO',20,?)",[date('Y-m-d',strtotime('-1 day'))]);denied(fn()=>quote($unused['id'],$next,'15:00','16:00','TESTEVENCIDO'),'Cupom vencido negado');
query("INSERT INTO cupons(codigo,porcentagem,ativo) VALUES('TESTEINATIVO',20,0)");denied(fn()=>quote($unused['id'],$next,'15:00','16:00','TESTEINATIVO'),'Cupom desativado negado');
query("INSERT INTO cupons(codigo,porcentagem,max_usos) VALUES('TESTELIMITE',25,1)");$input['cupom']='TESTELIMITE';$input['inicio']='15:00';$input['fim']='16:00';$id3=createBooking($unused,$input);check((float)receipt($unused['id'],$id3)['valor']===90.0,'Cupom geral calcula valor');denied(fn()=>quote($u['id'],$next,'16:00','17:00','TESTELIMITE'),'Limite global respeitado');
$input['cupom']='';$input['pagamento']='Dinheiro';$input['inicio']='16:00';$input['fim']='17:00';$id4=createBooking($unused,$input);adminAction(['acao'=>'confirmar','id'=>$id4]);check(receipt($unused['id'],$id4)['pagamento_status']==='Pendente','Confirmar reserva não confirma dinheiro');adminAction(['acao'=>'receber','id'=>$id4]);check(receipt($unused['id'],$id4)['pagamento_status']==='Confirmado','Admin confirma recebimento em dinheiro');
check(count(financial(['de'=>$next,'ate'=>$next,'forma'=>'Dinheiro','status'=>'Confirmado']))===1,'Filtros financeiros por período/status/forma');denied(fn()=>financial(['de'=>'2026-02-30']),'Data inválida do filtro negada');denied(fn()=>financial(['de'=>$next,'ate'=>$date]),'Período invertido negado');
check(period($next,'17:00','18:00')===60,'Duração de uma hora');denied(fn()=>period($next,'17:00','17:30'),'Meia hora negada');denied(fn()=>period('2026-02-30','17:00','18:00'),'Data impossível negada');
check(query('SELECT id FROM agendamentos WHERE id=?',[$id])->fetch()!==false,'Histórico cancelado preservado');
query("INSERT INTO cupons(codigo,porcentagem) VALUES('TESTEGRATIS',100)");$input['cupom']='TESTEGRATIS';$input['pagamento']='Pix';$input['inicio']='17:00';$input['fim']='18:00';$free=createBooking($unused,$input);check((float)receipt($unused['id'],$free)['valor']===0.0,'Desconto integral zera valor');check(receipt($unused['id'],$free)['pagamento_status']==='Confirmado','Reserva gratuita não exige Pix');adminAction(['acao'=>'confirmar','id'=>$free]);check(receipt($unused['id'],$free)['status']==='Confirmado','Admin confirma reserva gratuita');
$manualDate=date('Y-m-d',strtotime('+13 days'));
adminAction(['acao'=>'adicionar','nome_cliente'=>'Pix manual teste','telefone'=>'11999999999','data'=>$manualDate,'horario_inicio'=>'10:00','horario_fim'=>'11:00','forma_pagamento'=>'Pix']);
$manual=query('SELECT * FROM agendamentos WHERE nome_cliente=? AND data=?',['Pix manual teste',$manualDate])->fetch();
check($manual['usuario_id']===null && $manual['pagamento_status']==='Pendente','Pix manual inicia pendente sem conta de cliente');
adminAction(['acao'=>'confirmar_pix','id'=>$manual['id']]);
check(query('SELECT pagamento_status FROM agendamentos WHERE id=?',[$manual['id']])->fetchColumn()==='Confirmado','Admin confirma recebimento do Pix manual');
$input['cupom']='';$input['inicio']='18:00';$input['fim']='19:00';$normal=createBooking($unused,$input);
denied(fn()=>adminAction(['acao'=>'confirmar_pix','id'=>$normal]),'Pix de conta continua exigindo solicitação do cliente');
adminAction(['acao'=>'receber','id'=>$normal]);$paidPix=receipt($unused['id'],$normal);
check($paidPix['pagamento_status']==='Confirmado' && $paidPix['status']==='Confirmado','Admin confirma pagamento Pix antigo sem depender de nova reserva');
echo "\n$n verificações passaram.\n";

