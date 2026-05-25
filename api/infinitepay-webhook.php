<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$rawBody = file_get_contents('php://input');
if (empty($rawBody)) {
    http_response_code(400);
    echo json_encode(['error' => 'empty_body']);
    exit;
}

$secret = getConfig('infinitepay_webhook_secret');
if (!empty($secret)) {
    $sig      = $_SERVER['HTTP_X_CALLBACK_SIGNATURE'] ?? '';
    $computed = hash_hmac('sha256', $rawBody, $secret);
    if (!hash_equals($computed, $sig)) {
        http_response_code(401);
        echo json_encode(['error' => 'invalid_signature']);
        exit;
    }
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_json']);
    exit;
}

$orderNsu  = $payload['order_nsu'] ?? '';
$paid      = !empty($payload['paid']);
$txNsu     = $payload['transaction_nsu'] ?? '';

if ($orderNsu && $paid) {
    try {
        $db = getDB();
        // payment_id matches either the transaction UUID or the order reference stored at inscription time
        $stmt = $db->prepare("UPDATE inscricoes SET status_pagamento = 'confirmado' WHERE payment_id = ? AND status_pagamento = 'pendente'");
        $stmt->execute([$txNsu ?: $orderNsu]);
    } catch (\Throwable $e) {
        http_response_code(400); // 400 triggers InfinitePay retry
        echo json_encode(['success' => false, 'message' => 'db_error']);
        exit;
    }
}

http_response_code(200);
echo json_encode(['success' => true, 'message' => null]);
