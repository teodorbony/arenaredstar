<?php
session_start();

if (isset($_SESSION['usuario_id'])) {
    header('Location: ' . (($_SESSION['usuario_tipo'] ?? 'cliente') === 'admin' ? 'admin.php' : 'usuario.php'));
    exit;
}

header('Location: login.php');
exit;
