<?php
function smtpResposta($socket): string {
    $resposta = '';
    while (($linha = fgets($socket, 515)) !== false) {
        $resposta .= $linha;
        if (isset($linha[3]) && $linha[3] === ' ') break;
    }
    return $resposta;
}
function smtpComando($socket, string $comando, array $esperados): bool {
    fwrite($socket, $comando . "\r\n");
    $resposta = smtpResposta($socket);
    return in_array((int)substr($resposta, 0, 3), $esperados, true);
}
function enviarCodigoRecuperacao(string $destinatario, string $codigo): bool {
    $arquivo = __DIR__ . '/email_config.php';
    if (!file_exists($arquivo)) return false;
    $config = require $arquivo;
    if (empty($config['email']) || empty($config['senha_app'])) return false;
    $socket = @stream_socket_client('tcp://smtp.gmail.com:587', $erro, $erroTexto, 15);
    if (!$socket || !in_array((int)substr(smtpResposta($socket), 0, 3), [220], true)) return false;
    $host = 'localhost';
    if (!smtpComando($socket, 'EHLO ' . $host, [250]) || !smtpComando($socket, 'STARTTLS', [220])) { fclose($socket); return false; }
    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) || !smtpComando($socket, 'EHLO ' . $host, [250])) { fclose($socket); return false; }
    if (!smtpComando($socket, 'AUTH LOGIN', [334]) || !smtpComando($socket, base64_encode($config['email']), [334]) || !smtpComando($socket, base64_encode(str_replace(' ', '', $config['senha_app'])), [235])) { fclose($socket); return false; }
    if (!smtpComando($socket, 'MAIL FROM:<' . $config['email'] . '>', [250]) || !smtpComando($socket, 'RCPT TO:<' . $destinatario . '>', [250,251]) || !smtpComando($socket, 'DATA', [354])) { fclose($socket); return false; }
    $assunto = '=?UTF-8?B?' . base64_encode('Código para redefinir sua senha') . '?=';
    $nome = $config['nome'] ?? 'Arena Red Star';
    $mensagem = "From: {$nome} <{$config['email']}>\r\nTo: <{$destinatario}>\r\nSubject: {$assunto}\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nSeu código de verificação é: {$codigo}\r\n\r\nEle expira em 10 minutos. Se você não solicitou a troca de senha, ignore este e-mail.\r\n";
    fwrite($socket, $mensagem . "\r\n.\r\n");
    $ok = in_array((int)substr(smtpResposta($socket), 0, 3), [250], true);
    smtpComando($socket, 'QUIT', [221]); fclose($socket);
    return $ok;
}
