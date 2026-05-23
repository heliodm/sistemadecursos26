<?php
// Endpoint AJAX para verificação assíncrona de atualizações (não bloqueia o carregamento da página)
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/updater.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache');
header('X-Robots-Tag: noindex');

initSession();

if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

try {
    $info = upd_checkGithub(false); // respeita cache de 1 hora
    echo json_encode([
        'update_available' => !empty($info['update_available']),
        'latest_commit'    => $info['latest_commit'] ?? null,
        'current_commit'   => $info['commit'] ?? null,
        'api_error'        => $info['api_error'] ?? null,
        'checked_at'       => $info['checked_at'] ?? null,
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'check_failed']);
}
