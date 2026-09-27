<?php
session_start(); require_once 'db.php';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$erro=''; $email=$_SESSION['reset_email']??'';
if (!$email) { header('Location: recuperar-senha.php'); exit; }
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']??'')) $erro='Sessão expirada. Atualize a página.';
    else {
        $codigo=preg_replace('/\D/','',$_POST['codigo']??''); $senha=$_POST['senha']??''; $confirmacao=$_POST['confirmacao']??'';
        if(strlen($codigo)!==6 || strlen($senha)<6 || $senha!==$confirmacao) $erro='Confira o código e use uma senha com pelo menos 6 caracteres.';
        else {
            $stmt=$pdo->prepare('SELECT r.id,r.usuario_id,r.codigo_hash FROM recuperacoes_senha r INNER JOIN usuarios u ON u.id=r.usuario_id WHERE u.email=:email AND r.usado_em IS NULL AND r.expira_em > NOW() ORDER BY r.id DESC LIMIT 1'); $stmt->execute(['email'=>$email]); $rec=$stmt->fetch();
            if(!$rec || !password_verify($codigo,$rec['codigo_hash'])) { if($rec) $pdo->prepare('UPDATE recuperacoes_senha SET tentativas=tentativas+1 WHERE id=:id')->execute(['id'=>$rec['id']]); $erro='Código inválido ou expirado.'; }
            else { $pdo->beginTransaction(); $pdo->prepare('UPDATE usuarios SET senha=:senha WHERE id=:id')->execute(['senha'=>password_hash($senha,PASSWORD_DEFAULT),'id'=>$rec['usuario_id']]); $pdo->prepare('UPDATE recuperacoes_senha SET usado_em=NOW() WHERE id=:id')->execute(['id'=>$rec['id']]); $pdo->commit(); unset($_SESSION['reset_email']); $_SESSION['sucesso_login']='Senha alterada. Faça login com sua nova senha.'; header('Location: login.php'); exit; }
        }
    }
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Nova senha | Red Star</title><link rel="stylesheet" href="style.css"></head><body class="auth-page"><a class="back-link" href="login.php">← Voltar ao login</a><main class="auth-layout"><section class="brand-side"><img class="hero-logo" src="assets/logo-red-star.png" alt="Red Star"></section><section class="auth-panel"><div class="panel-kicker">VERIFICAÇÃO</div><h1>Defina uma nova senha</h1><p class="muted">Digite o código enviado para <?=htmlspecialchars($email,ENT_QUOTES,'UTF-8')?>.</p><?php if($erro):?><div class="alert danger"><?=htmlspecialchars($erro,ENT_QUOTES,'UTF-8')?></div><?php endif;?><form method="post" class="form-stack"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'],ENT_QUOTES,'UTF-8')?>"><label>Código de 6 dígitos<div class="input-box"><span>🔐</span><input type="text" name="codigo" inputmode="numeric" maxlength="6" required></div></label><label>Nova senha<div class="input-box"><span>🔒</span><input type="password" name="senha" minlength="6" required></div></label><label>Confirme a nova senha<div class="input-box"><span>🔒</span><input type="password" name="confirmacao" minlength="6" required></div></label><button class="primary-btn">Alterar senha</button></form></section></main></body></html>
