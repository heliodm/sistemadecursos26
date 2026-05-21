<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'erro' => 'Método não permitido.']);
    exit;
}

requireLogin('/login.php');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados inválidos.']);
    exit;
}

$id     = (int)($input['id'] ?? 0);
$status = sanitize($input['status'] ?? '');
$csrf   = sanitize($input['csrf'] ?? '');

initSession();
if (!verificarCSRF($csrf)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Token inválido.']);
    exit;
}

if (!$id || !in_array($status, ['pendente', 'confirmado', 'cancelado'], true)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados inválidos.']);
    exit;
}

$db   = getDB();
$stmt = $db->prepare("UPDATE inscricoes SET status_pagamento = ? WHERE id = ?");
$stmt->execute([$status, $id]);

$st  = formatarStatusPagamento($status);
echo json_encode(['sucesso' => true, 'label' => $st['label'], 'classe' => $st['class']]);
