<?php
require_once 'app.php';$u=auth();$id=(int)($_POST['id']??$_GET['id']??0);
if($_SERVER['REQUEST_METHOD']==='POST') { try { csrf(); addReview((int)$u['id'],$id,(int)filter_var($_POST['nota']??0,FILTER_VALIDATE_INT),$_POST['comentario']??'');notice('Obrigado! Sua avaliação foi publicada.'); }catch(Throwable $ex){handleError($ex);}redirect('meus-agendamentos.php'); }
$r=query("SELECT id FROM agendamentos WHERE id=? AND usuario_id=? AND status='Concluído' AND TIMESTAMP(data,horario_fim)<=?",[$id,$u['id'],date('Y-m-d H:i:s')])->fetch();
if(!$r || query('SELECT id FROM avaliacoes WHERE agendamento_id=?',[$id])->fetch()){http_response_code(403);exit('Esta reserva não pode ser avaliada.');}
pageStart('Avaliar a Arena'); ?>
<section class="admin-form-card narrow"><h1>Como foi seu jogo?</h1><p class="muted">Sua avaliação da reserva #<?=$id?> ajuda quem vai conhecer a Arena.</p><form method="post" class="form-stack"><?php csrfField();?><input type="hidden" name="id" value="<?=$id?>"><fieldset class="rating-options"><legend>Nota de 1 a 5 estrelas</legend><?php for($i=1;$i<=5;$i++):?><label><input type="radio" name="nota" value="<?=$i?>" required><?=$i?> ★</label><?php endfor;?></fieldset><label>Comentário (opcional)<textarea name="comentario" maxlength="2000" rows="5"></textarea></label><button class="primary-btn">Publicar avaliação</button></form></section>
<?php pageEnd();
