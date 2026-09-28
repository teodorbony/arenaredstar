<?php
require_once __DIR__ . '/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';

function enviarCodigoRecuperacao(string $destinatario, string $codigo): bool {
    $arquivo = __DIR__ . '/email_config.php';
    if (!is_file($arquivo)) {
        error_log('Recuperação de senha: email_config.php não foi configurado.');
        return false;
    }

    $config = require $arquivo;
    if (!is_array($config) || empty($config['email']) || empty($config['senha_app'])) {
        error_log('Recuperação de senha: configure email e senha_app em email_config.php.');
        return false;
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = trim($config['email']);
        $mail->Password = str_replace(' ', '', trim($config['senha_app']));
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->Timeout = 20;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($config['email'], $config['nome'] ?? 'Arena Red Star');
        $mail->addAddress($destinatario);
        $mail->isHTML(false);
        $mail->Subject = 'Código para redefinir sua senha';
        $mail->Body = "Seu código de verificação é: {$codigo}\n\nEle expira em 10 minutos. Se você não solicitou a troca de senha, ignore este e-mail.";

        return $mail->send();
    } catch (\Throwable $e) {
        error_log('Falha ao enviar código de recuperação com PHPMailer: ' . $mail->ErrorInfo);
        return false;
    }
}
