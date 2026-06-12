<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

initSession();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verificarCSRF($_POST['csrf_token'] ?? '')) {
    logout();
    redirect('/login.php', 'Você saiu do sistema com sucesso.', 'success');
}

// GET direto (favorito, link antigo) — apenas redireciona sem mensagem
redirect('/login.php');
