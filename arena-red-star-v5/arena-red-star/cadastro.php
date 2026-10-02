<?php
require_once 'app.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: ' . (($_SESSION['usuario_tipo'] ?? 'cliente') === 'admin' ? 'admin.php' : 'usuario.php'));
    exit;
}

$erro = '';
$nome = $email = $telefone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try { csrf(); } catch (DomainException $ex) { http_response_code(403); exit(e($ex->getMessage())); }
    $nome = trim($_POST['nome'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmar = $_POST['confirmar_senha'] ?? '';

    if ($nome === '' || $telefone === '' || $email === '' || $senha === '' || $confirmar === '') {
        $erro = 'Preencha todos os campos.';
    } elseif (mb_strlen($nome) < 3 || mb_strlen($nome)>120 || mb_strlen($telefone)>25 || strlen($email)>150) {
        $erro = 'Digite seu nome completo.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Digite um e-mail válido.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($senha !== $confirmar) {
        $erro = 'As senhas não coincidem.';
    } else {
        $check = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1');
        $check->execute(['email' => $email]);

        if ($check->fetch()) {
            $erro = 'Este e-mail já está cadastrado.';
        } else {
            try { atomic(function() use ($pdo,$nome,$telefone,$email,$senha) {
            $stmt = $pdo->prepare('INSERT INTO usuarios (nome, telefone, email, senha, tipo) VALUES (:nome, :telefone, :email, :senha, :tipo)');
            $stmt->execute([
                'nome' => $nome,
                'telefone' => $telefone,
                'email' => $email,
                'senha' => password_hash($senha, PASSWORD_DEFAULT),
                'tipo' => 'cliente'
            ]);
            $uid=(int)$pdo->lastInsertId();
            query("INSERT INTO usuario_cupons(usuario_id,cupom_id) SELECT ?,id FROM cupons WHERE codigo='REDSTAR10'",[$uid]);
            }); } catch (Throwable $ex) { handleError($ex); redirect('cadastro.php'); }
            $_SESSION['sucesso_login'] = '🎉 Bem-vindo à Arena Red Star! Você ganhou o cupom REDSTAR10 com 10% de desconto na sua primeira reserva. Faça seu login.';
            header('Location: login.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cadastro | Red Star Point Ball</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">
<div class="stadium-overlay"></div>
<a class="back-link" href="login.php">← Voltar para o login</a>
<main class="auth-layout register-layout">
    <section class="brand-side reveal">
        <img class="hero-logo" src="assets/logo-red-star.png" alt="Logo Red Star Point Ball">
        <div class="slogan">MAIS QUE FUTEBOL,<br><span>É RESENHA!</span></div>
    </section>

    <section class="auth-panel reveal delay-1">
        <div class="panel-kicker">NOVO JOGADOR</div>
        <h1>Crie sua conta</h1>
        <p class="muted">Preencha seus dados para se cadastrar.</p>

        <?php if ($erro): ?><div class="alert danger"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

        <?php showNotice(); ?><form method="post" class="form-stack" id="cadastroForm"><?php csrfField(); ?>
            <label>Nome completo<div class="input-box"><span>♙</span><input type="text" name="nome" value="<?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?>" placeholder="Seu nome completo" required maxlength="120"></div></label>
            <label>Telefone<div class="input-box"><span>☎</span><input type="text" name="telefone" id="telefone" value="<?= htmlspecialchars($telefone, ENT_QUOTES, 'UTF-8') ?>" placeholder="(00) 00000-0000" required maxlength="20"></div></label>
            <label>E-mail<div class="input-box"><span>✉</span><input type="email" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" placeholder="Seu e-mail" required></div></label>
            <label>Senha<div class="input-box"><span>🔒</span><input type="password" name="senha" id="senha" placeholder="Mínimo de 6 caracteres" required minlength="6"><button type="button" class="icon-btn" onclick="togglePassword('senha', this)">◉</button></div></label>
            <label>Confirmar senha<div class="input-box"><span>🔒</span><input type="password" name="confirmar_senha" id="confirmar_senha" placeholder="Repita sua senha" required minlength="6"><button type="button" class="icon-btn" onclick="togglePassword('confirmar_senha', this)">◉</button></div></label>
            <button class="primary-btn" type="submit">👤 Cadastrar</button>
        </form>
        <p class="already">Já tem uma conta? <a href="login.php">Fazer login</a></p>
    </section>
</main>
<footer class="auth-footer">© <?= date('Y') ?> Red Star Point Ball · <a href="https://www.instagram.com/redstarpointball/" target="_blank" rel="noopener">@redstarpointball</a></footer>
<script>
function togglePassword(id,btn){const el=document.getElementById(id);el.type=el.type==='password'?'text':'password';btn.textContent=el.type==='password'?'◉':'⊘';}
const tel=document.getElementById('telefone');tel.addEventListener('input',function(){let v=this.value.replace(/\D/g,'').slice(0,11);if(v.length>10)v=v.replace(/(\d{2})(\d{5})(\d{4})/,'($1) $2-$3');else if(v.length>6)v=v.replace(/(\d{2})(\d{4})(\d{0,4})/,'($1) $2-$3');else if(v.length>2)v=v.replace(/(\d{2})(\d+)/,'($1) $2');this.value=v;});
</script>
</body>
</html>

