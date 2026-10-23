<?php
session_start(); require_once 'db.php'; require_once 'mailer.php'; require_once __DIR__.'/icons.php';
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$mensagem=''; $tipo=''; $email='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']??'')) { $mensagem='Sessão expirada. Atualize a página.'; $tipo='danger'; }
    else {
        $email=trim($_POST['email']??'');
        $stmt=$pdo->prepare('SELECT id,email FROM usuarios WHERE email=:email LIMIT 1'); $stmt->execute(['email'=>$email]); $usuario=$stmt->fetch();
        if (!$usuario) { $mensagem='Se o e-mail estiver cadastrado, enviaremos um código de verificação.'; $tipo='success'; }
        else {
            $codigo=(string)random_int(100000,999999);
            $pdo->prepare('DELETE FROM recuperacoes_senha WHERE usuario_id=:id OR expira_em < NOW()')->execute(['id'=>$usuario['id']]);
            $stmt=$pdo->prepare('INSERT INTO recuperacoes_senha (usuario_id,codigo_hash,expira_em) VALUES (:id,:hash,DATE_ADD(NOW(), INTERVAL 10 MINUTE))'); $stmt->execute(['id'=>$usuario['id'],'hash'=>password_hash($codigo,PASSWORD_DEFAULT)]);
            if (enviarCodigoRecuperacao($usuario['email'],$codigo)) { $_SESSION['reset_email']=$usuario['email']; header('Location: redefinir-senha.php'); exit; }
            $pdo->prepare('DELETE FROM recuperacoes_senha WHERE usuario_id=:id')->execute(['id'=>$usuario['id']]);
            $mensagem='Não foi possível enviar o e-mail. Configure o arquivo email_config.php com a senha de app do Gmail.'; $tipo='danger';
        }
    }
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Recuperar senha | Red Star</title><link rel="stylesheet" href="style.css"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Bebas+Neue&display=swap"><link rel="stylesheet" href="tema.css"></head><body class="auth-page"><a class="back-link" href="login.php">← Voltar ao login</a><main class="auth-layout"><section class="brand-side"><img class="hero-logo" src="assets/logo-red-star.png" alt="Red Star"></section><section class="auth-panel"><div class="panel-kicker">RECUPERAR SENHA</div><h1>Esqueceu a senha?</h1><p class="muted">Informe seu e-mail para receber um código de verificação.</p><?php if($mensagem):?><div class="alert <?=$tipo?>"><?=htmlspecialchars($mensagem,ENT_QUOTES,'UTF-8')?></div><?php endif;?><form method="post" class="form-stack"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($_SESSION['csrf_token'],ENT_QUOTES,'UTF-8')?>"><label>E-mail<div class="input-box"><span><?= rs_icon('mail') ?></span><input type="email" name="email" value="<?=htmlspecialchars($email,ENT_QUOTES,'UTF-8')?>" required></div></label><button class="primary-btn">Enviar código</button></form></section></main></body></html>
