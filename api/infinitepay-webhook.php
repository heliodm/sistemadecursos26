<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/infinitepay.php';

header('Content-Type: application/json; charset=utf-8');

$rawBody = file_get_contents('php://input');
if (empty($rawBody)) {
    http_response_code(400);
    echo json_encode(['error' => 'empty_body']);
    exit;
}

// Defesa em profundidade: se um segredo de webhook estiver configurado, valida a
// assinatura HMAC. A confirmação real, porém, NÃO depende disso — ela é feita
// re-verificando o pagamento direto na API da InfinitePay (abaixo).
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

$orderNsu    = (string)($payload['order_nsu'] ?? '');
$invoiceSlug = (string)($payload['invoice_slug'] ?? '');
$txNsu       = (string)($payload['transaction_nsu'] ?? '');

// IMPORTANTE: nunca confiar no corpo do webhook para confirmar pagamento.
// order_nsu (INS-{id}) é sequencial e adivinhável; um POST forjado poderia
// marcar qualquer inscrição como paga. Confirmamos consultando a própria
// InfinitePay via payment_check, que só retorna pago quando houve pagamento real.
if ($orderNsu !== '') {
    try {
        $ipay = new InfinitePay();
        if ($ipay->isConfigured() && $ipay->verificarPagamento($orderNsu, $invoiceSlug, $txNsu)) {
            $db = getDB();
            $stmt = $db->prepare("UPDATE inscricoes SET status_pagamento = 'confirmado' WHERE payment_id = ? AND status_pagamento = 'pendente'");
            $stmt->execute([$orderNsu]);
        }
    } catch (\Throwable $e) {
        http_response_code(400); // 400 faz a InfinitePay reenviar o webhook
        echo json_encode(['success' => false, 'message' => 'processing_error']);
        exit;
    }
}

http_response_code(200);
echo json_encode(['success' => true, 'message' => null]);
