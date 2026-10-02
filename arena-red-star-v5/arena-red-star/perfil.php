<?php
require_once 'app.php'; $u=auth();
if($_SERVER['REQUEST_METHOD']==='POST') {
 try {
  csrf(); $nome=trim($_POST['nome']??'');$tel=trim($_POST['telefone']??'');$email=trim($_POST['email']??'');$senha=$_POST['nova_senha']??'';
  if(mb_strlen($nome)<3 || mb_strlen($nome)>120 || mb_strlen($tel)>25 || $tel==='' || strlen($email)>150 || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new DomainException('Preencha nome, telefone e e-mail válidos.');
  atomic(function()use($u,$nome,$tel,$email,$senha) {
   $atual=query('SELECT senha FROM usuarios WHERE id=?',[$u['id']])->fetchColumn();
   if(query('SELECT id FROM usuarios WHERE email=? AND id<>?',[$email,$u['id']])->fetch()) throw new DomainException('Este e-mail já está cadastrado.');
   if($senha!=='') {
    if(!password_verify($_POST['senha_atual']??'',$atual)) throw new DomainException('Senha atual incorreta.');
    if(strlen($senha)<6 || $senha!==($_POST['confirmar_senha']??'')) throw new DomainException('A nova senha deve ter pelo menos 6 caracteres e a confirmação deve coincidir.');
    $atual=password_hash($senha,PASSWORD_DEFAULT);
    query('UPDATE recuperacoes_senha SET usado_em=NOW() WHERE usuario_id=? AND usado_em IS NULL',[$u['id']]);
   }
   query('UPDATE usuarios SET nome=?,telefone=?,email=?,senha=? WHERE id=?',[$nome,$tel,$email,$atual,$u['id']]);
  });
  $_SESSION['usuario_nome']=$nome;$_SESSION['usuario_telefone']=$tel;$_SESSION['usuario_email']=$email;
  notice('Perfil atualizado.');
 }catch(Throwable $ex){handleError($ex);} redirect('perfil.php');
}
pageStart('Meu Perfil'); ?>
<section class="admin-form-card narrow"><div class="panel-kicker">MINHA CONTA</div><h1>Meu Perfil</h1><form method="post" class="form-stack"><?php csrfField(); ?><label>Nome<input name="nome" value="<?=e($u['nome'])?>" maxlength="120" required></label><label>Telefone<input name="telefone" value="<?=e($u['telefone'])?>" maxlength="25" required></label><label>E-mail<input type="email" name="email" value="<?=e($u['email'])?>" maxlength="150" required></label><h2>Alterar senha</h2><p class="muted">Preencha somente se desejar trocar sua senha.</p><label>Senha atual<input type="password" name="senha_atual" autocomplete="current-password"></label><label>Nova senha<input type="password" name="nova_senha" minlength="6" autocomplete="new-password"></label><label>Confirmar nova senha<input type="password" name="confirmar_senha" autocomplete="new-password"></label><button class="primary-btn">Salvar alterações</button></form></section>
<?php pageEnd();
