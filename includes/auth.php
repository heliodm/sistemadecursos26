<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Verificar se está autenticado
function isLoggedIn(): bool {
    initSession();
    return !empty($_SESSION['usuario_id']) && !empty($_SESSION['usuario_tipo']);
}

// Verificar se é admin
function isAdmin(): bool {
    return isLoggedIn() && $_SESSION['usuario_tipo'] === 'admin';
}

// Verificar se é usuário comum (painel)
function isUsuario(): bool {
    return isLoggedIn() && in_array($_SESSION['usuario_tipo'], ['admin', 'usuario'], true);
}

// Exigir autenticação - redireciona para login se não estiver logado
function requireLogin(string $redirect = '/login.php'): void {
    if (!isLoggedIn()) {
        redirect($redirect, 'Acesso restrito. Faça login para continuar.', 'warning');
    }
    // Verificar validade da sessão
    if (isset($_SESSION['ultimo_acesso']) && (time() - $_SESSION['ultimo_acesso']) > SESSION_LIFETIME) {
        logout();
        redirect($redirect, 'Sessão expirada. Faça login novamente.', 'warning');
    }
    $_SESSION['ultimo_acesso'] = time();
}

// Exigir que seja admin
function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        redirect('/admin/index.php', 'Acesso negado. Apenas administradores.', 'danger');
    }
}

// Fazer login
function login(string $email, string $senha): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, nome, email, senha, tipo, ativo FROM usuarios WHERE email = ? LIMIT 1");
    $stmt->execute([strtolower(trim($email))]);
    $usuario = $stmt->fetch();

    if (!$usuario || !$usuario['ativo']) {
        // Tempo constante para evitar timing attack
        password_verify('dummy', '$2y$12$dummy_hash_to_prevent_timing_attacks');
        return ['sucesso' => false, 'erro' => 'E-mail ou senha incorretos.'];
    }

    if (!password_verify($senha, $usuario['senha'])) {
        return ['sucesso' => false, 'erro' => 'E-mail ou senha incorretos.'];
    }

    initSession();
    session_regenerate_id(true);

    $_SESSION['usuario_id']   = $usuario['id'];
    $_SESSION['usuario_nome'] = $usuario['nome'];
    $_SESSION['usuario_email'] = $usuario['email'];
    $_SESSION['usuario_tipo'] = $usuario['tipo'];
    $_SESSION['ultimo_acesso'] = time();
    $_SESSION['last_regen']   = time();

    return ['sucesso' => true, 'usuario' => $usuario];
}

// Fazer logout
function logout(): void {
    initSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// Obter dados do usuário logado
function getUsuarioLogado(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'id'    => $_SESSION['usuario_id'],
        'nome'  => $_SESSION['usuario_nome'],
        'email' => $_SESSION['usuario_email'],
        'tipo'  => $_SESSION['usuario_tipo'],
    ];
}
