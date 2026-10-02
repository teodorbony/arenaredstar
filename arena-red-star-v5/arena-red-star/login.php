<?php
require_once 'app.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: ' . (($_SESSION['usuario_tipo'] ?? 'cliente') === 'admin' ? 'admin.php' : 'usuario.php'));
    exit;
}

$erro = '';
$sucesso = $_SESSION['sucesso_login'] ?? '';
unset($_SESSION['sucesso_login']);
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try { csrf(); } catch (DomainException $ex) { http_response_code(403); exit(e($ex->getMessage())); }
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($email === '' || $senha === '') {
        $erro = 'Preencha o e-mail e a senha.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Digite um e-mail válido.';
    } else {
        $stmt = $pdo->prepare('SELECT id, nome, email, telefone, senha, tipo FROM usuarios WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($senha, $usuario['senha'])) {
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = (int)$usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_email'] = $usuario['email'];
            $_SESSION['usuario_telefone'] = $usuario['telefone'] ?? '';
            $_SESSION['usuario_tipo'] = $usuario['tipo'];

            header('Location: ' . ($usuario['tipo'] === 'admin' ? 'admin.php' : 'usuario.php'));
            exit;
        }

        $erro = 'E-mail ou senha inválidos.';
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Red Star Point Ball</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
<div class="stadium-overlay"></div>
<main class="auth-layout">
    <section class="brand-side reveal">
        <img class="hero-logo" src="assets/logo-red-star.png" alt="Logo Red Star Point Ball">
        <div class="slogan">MAIS QUE FUTEBOL,<br><span>É RESENHA!</span></div>
        <div class="brand-pills">
            <span>⚽ Campo society</span>
            <span>👥 Amigos</span>
            <span>🍻 Resenha</span>
        </div>
    </section>

    <section class="auth-panel reveal delay-1">
        <div class="panel-kicker">ARENA RED STAR</div>
        <h1>Bem-vindo!</h1>
        <p class="muted">Faça seu login para continuar.</p>

        <?php if ($sucesso): ?>
            <div class="alert success"><?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($erro): ?>
            <div class="alert danger"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" class="form-stack" id="loginForm"><?php csrfField(); ?>
            <label>E-mail
                <div class="input-box"><span>✉</span><input type="email" name="email" id="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" placeholder="Seu e-mail" required></div>
            </label>
            <label>Senha
                <div class="input-box"><span>🔒</span><input type="password" name="senha" id="senha" placeholder="Sua senha" required><button type="button" class="icon-btn" onclick="togglePassword('senha', this)">◉</button></div>
            </label>
            <div class="form-row-small">
                <label class="check-line"><input type="checkbox"> Lembrar de mim</label>
                <a href="recuperar-senha.php">Esqueci minha senha?</a>
            </div>
            <button class="primary-btn" type="submit">Entrar <span>→</span></button>
        </form>

        <div class="account-divider"><span>Ainda não tem uma conta?</span></div>
        <a class="outline-btn" href="cadastro.php">👤 Criar minha conta</a>
        <a href="index.php">← Conheça a Arena</a>
    </section>
</main>
<footer class="auth-footer">© <?= date('Y') ?> Red Star Point Ball · <a href="https://www.instagram.com/redstarpointball/" target="_blank" rel="noopener">@redstarpointball</a></footer>
<script>
function togglePassword(id, btn){const el=document.getElementById(id);el.type=el.type==='password'?'text':'password';btn.textContent=el.type==='password'?'◉':'⊘';}
document.getElementById('loginForm').addEventListener('submit', function(e){const mail=document.getElementById('email');if(!mail.checkValidity()){e.preventDefault();alert('Digite um e-mail válido.');}});
</script>
</body>
</html>

