<?php
function adminAction(array $in): void {
 atomic(function()use($in){
  $action=$in['acao']??'';
  if($action==='adicionar') {
   $name=trim($in['nome_cliente']??'');$tel=trim($in['telefone']??'');$date=$in['data']??'';$start=$in['horario_inicio']??'';$end=$in['horario_fim']??'';$status=$in['status']??'Pendente';$pay=$in['forma_pagamento']??'Dinheiro';
   if($name===''||mb_strlen($name)>120||$tel===''||mb_strlen($tel)>25) throw new DomainException('Informe nome e telefone válidos.');
   if(!in_array($status,['Pendente','Confirmado','Cancelado'],true)||!in_array($pay,['Pix','Dinheiro','Cartão'],true)) throw new DomainException('Status ou forma de pagamento inválidos.');
   $minutes=period($date,$start,$end);if($status!=='Cancelado')available($date,$start,$end);
   if($pay==='Pix'&&$status==='Confirmado') throw new DomainException('Cadastre o Pix pendente e confirme após verificar o pagamento.');
   query("INSERT INTO agendamentos(usuario_id,nome_cliente,telefone,data,horario_inicio,horario_fim,forma_pagamento,valor,valor_original,pagamento_status,campo,status) VALUES(NULL,?,?,?,?,?,?,?,?,'Pendente','Campo 1',?)",[$name,$tel,$date,$start,$end,$pay,$minutes*2,$minutes*2,$status]);return;
  }
  $id=(int)($in['id']??0);$r=query('SELECT * FROM agendamentos WHERE id=?',[$id])->fetch();if(!$r)throw new DomainException('Reserva não encontrada.');
  if(in_array($action,['confirmar','confirmar_pix'],true)) {
   if($r['status']!=='Pendente') throw new DomainException('Somente reservas pendentes podem ser confirmadas.');
   if($r['forma_pagamento']==='Pix'&&(float)$r['valor']>0&&$action!=='confirmar_pix')throw new DomainException('Verifique e confirme o Pix.');
   if($action==='confirmar_pix' && ($r['forma_pagamento']!=='Pix'||$r['pagamento_status']!=='Solicitado')) throw new DomainException('O cliente ainda não solicitou a confirmação do Pix.');
   available($r['data'],$r['horario_inicio'],$r['horario_fim'],$id);
   query("UPDATE agendamentos SET status='Confirmado',pagamento_status=? WHERE id=?",[$action==='confirmar_pix'?'Confirmado':$r['pagamento_status'],$id]);
  }elseif($action==='receber') {
   if($r['status']==='Cancelado'||$r['forma_pagamento']==='Pix')throw new DomainException('Para Pix use a confirmação específica.');
   query("UPDATE agendamentos SET pagamento_status='Confirmado' WHERE id=?",[$id]);
  }elseif($action==='concluir') {
   if($r['status']!=='Confirmado'||strtotime($r['data'].' '.$r['horario_fim'])>time())throw new DomainException('Conclua somente reservas confirmadas cujo horário já terminou.');
   query("UPDATE agendamentos SET status='Concluído' WHERE id=?",[$id]);
  }elseif(in_array($action,['cancelar','excluir'],true)) {
   if($r['status']==='Concluído')throw new DomainException('Uma reserva concluída deve ser preservada.');
   query("UPDATE agendamentos SET status='Cancelado' WHERE id=?",[$id]);
  }else throw new DomainException('Ação inválida.');
 });
}
