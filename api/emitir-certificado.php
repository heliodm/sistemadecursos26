<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['sucesso' => false, 'erro' => 'Método inválido.']);
    exit;
}

requireLogin('/login.php');

$input = json_decode(file_get_contents('php://input'), true);
$inscricaoId = (int)($input['inscricao_id'] ?? 0);
$csrf        = sanitize($input['csrf'] ?? '');

initSession();
if (!verificarCSRF($csrf)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Token inválido.']);
    exit;
}

if (!$inscricaoId) {
    echo json_encode(['sucesso' => false, 'erro' => 'ID inválido.']);
    exit;
}

$db = getDB();

// Verificar se já tem certificado
$check = $db->prepare("SELECT id FROM certificados WHERE inscricao_id = ?");
$check->execute([$inscricaoId]);
if ($check->fetch()) {
    echo json_encode(['sucesso' => false, 'erro' => 'Certificado já emitido.']);
    exit;
}

// Buscar inscrição confirmada
$stmt = $db->prepare("SELECT * FROM inscricoes WHERE id = ? AND status_pagamento = 'confirmado'");
$stmt->execute([$inscricaoId]);
$ins = $stmt->fetch();

if (!$ins) {
    echo json_encode(['sucesso' => false, 'erro' => 'Inscrição não encontrada ou pagamento não confirmado.']);
    exit;
}

try {
    $codigo = gerarCodigoCertificado();
    $db->prepare("INSERT INTO certificados (inscricao_id, curso_id, codigo_unico, nome_completo) VALUES (?,?,?,?)")
        ->execute([$inscricaoId, $ins['curso_id'], $codigo, $ins['nome_completo']]);
    $db->prepare("UPDATE inscricoes SET certificado_emitido = 1 WHERE id = ?")->execute([$inscricaoId]);
    echo json_encode(['sucesso' => true, 'codigo' => $codigo]);
} catch (PDOException $e) {
    echo json_encode(['sucesso' => false, 'erro' => 'Certificado já emitido para esta inscrição.']);
}
