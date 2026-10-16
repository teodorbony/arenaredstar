<?php
require_once 'app.php';$u=auth(true);
if($_SERVER['REQUEST_METHOD']==='POST') { try {
 csrf();$id=(int)($_POST['id']??0);
 atomic(function()use($id){
  if(($_POST['acao']??'')==='excluir') {query('DELETE FROM bloqueios_horarios WHERE id=?',[$id]);return;}
  $date=$_POST['data']??'';$start=$_POST['hora_inicio']??'';$end=$_POST['hora_fim']??'';$reason=trim($_POST['motivo']??'');
  if(!validDate($date)||!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/',$start)||!($end==='24:00'||preg_match('/^(?:[01]\d|2[0-3]):00$/',$end))||$end<=$start||$reason===''||mb_strlen($reason)>255) throw new DomainException('Informe data, intervalo e motivo válidos.');
  available($date,$start,$end,0,$id,false);
  if($id) {if(!query('SELECT id FROM bloqueios_horarios WHERE id=?',[$id])->fetch()) throw new DomainException('Bloqueio não encontrado.'); query('UPDATE bloqueios_horarios SET data=?,hora_inicio=?,hora_fim=?,motivo=? WHERE id=?',[$date,$start,$end,$reason,$id]);}
  else query('INSERT INTO bloqueios_horarios(data,hora_inicio,hora_fim,motivo) VALUES(?,?,?,?)',[$date,$start,$end,$reason]);
 });notice('Bloqueios atualizados.');
 }catch(Throwable $ex){handleError($ex);} redirect('bloqueios.php'); }
$edit=query('SELECT * FROM bloqueios_horarios WHERE id=?',[(int)($_GET['editar']??0)])->fetch()?:[];
$rows=query('SELECT * FROM bloqueios_horarios ORDER BY data DESC,hora_inicio')->fetchAll();pageStart('Bloqueio de Horários'); ?>
<h1>Bloqueio de Horários</h1><p class="muted">Manutenção, eventos particulares e indisponibilidade. O bloqueio impede novas reservas; reservas já existentes permanecem ativas.</p><section class="admin-form-card"><h2><?=$edit?'Editar bloqueio':'Criar bloqueio'?></h2><form method="post" class="admin-form-grid"><?php csrfField();?><input type="hidden" name="id" value="<?=(int)($edit['id']??0)?>"><label>Data<input type="date" name="data" value="<?=e($edit['data']??'')?>" required></label><label>Início<input type="time" name="hora_inicio" value="<?=e(substr($edit['hora_inicio']??'',0,5))?>" required></label><label>Fim<input type="text" inputmode="numeric" pattern="(?:[01][0-9]|2[0-3]):00|24:00" placeholder="Ex.: 00:00" name="hora_fim" value="<?=e(substr($edit['hora_fim']??'',0,5))?>" required></label><label>Motivo<input name="motivo" list="motivos" value="<?=e($edit['motivo']??'')?>" maxlength="255" required><datalist id="motivos"><option>Manutenção</option><option>Evento particular</option><option>Indisponibilidade</option><option>Outros motivos</option></datalist></label><button class="primary-btn">Salvar bloqueio</button><?php if($edit):?><a href="bloqueios.php">Cancelar edição</a><?php endif;?></form></section>
<div class="table-card table-scroll"><table><thead><tr><th>Data</th><th>Início</th><th>Fim</th><th>Motivo</th><th>Ações</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="5">Nenhum bloqueio.</td></tr><?php endif;foreach($rows as $r):?><tr><td><?=date('d/m/Y',strtotime($r['data']))?></td><td><?=e(substr($r['hora_inicio'],0,5))?></td><td><?=e(substr($r['hora_fim'],0,5))?></td><td><?=e($r['motivo'])?></td><td><div class="action-set"><a class="outline-btn small" href="?editar=<?=(int)$r['id']?>">Editar</a><form method="post" onsubmit="return confirm('Excluir este bloqueio?')"><?php csrfField();?><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button class="danger-outline small" name="acao" value="excluir">Excluir</button></form></div></td></tr><?php endforeach;?></tbody></table></div>
<?php pageEnd();
