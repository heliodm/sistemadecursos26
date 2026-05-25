<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/infinitepay.php';

// "ref" é nosso order_nsu (INS-{id}) que colocamos na redirect_url
// InfinitePay pode também enviar order_nsu, invoice_slug, transaction_nsu como params extras
$ref         = preg_replace('/[^A-Za-z0-9_-]/', '', $_GET['ref'] ?? $_GET['order_nsu'] ?? '');
$invoiceSlug = preg_replace('/[^A-Za-z0-9_-]/', '', $_GET['invoice_slug'] ?? '');
$txNsu       = preg_replace('/[^A-Za-z0-9_-]/', '', $_GET['transaction_nsu'] ?? '');

if (!$ref) {
    redirect('/index.php', 'Parâmetro de retorno ausente.', 'warning');
}

$db   = getDB();
$stmt = $db->prepare(
    "SELECT i.id, i.status_pagamento, c.slug
     FROM inscricoes i
     JOIN cursos c ON c.id = i.curso_id
     WHERE i.payment_id = ?
     LIMIT 1"
);
$stmt->execute([$ref]);
$row = $stmt->fetch();

if (!$row) {
    redirect('/index.php', 'Inscrição não encontrada.', 'warning');
}

$slug = $row['slug'];
$base = '/curso.php?slug=' . urlencode($slug) . '&inscrito=1&order_nsu=' . urlencode($ref);

// Já confirmado pelo webhook
if ($row['status_pagamento'] === 'confirmado') {
    redirect($base, 'Pagamento confirmado! Inscrição realizada com sucesso.', 'success');
}

// Ainda pendente — tenta verificar via API
$ipay = new InfinitePay();
if ($ipay->isConfigured() && $ipay->verificarPagamento($ref, $invoiceSlug, $txNsu)) {
    $db->prepare("UPDATE inscricoes SET status_pagamento = 'confirmado' WHERE id = ?")
       ->execute([$row['id']]);
    redirect($base, 'Pagamento confirmado! Inscrição realizada com sucesso.', 'success');
}

// Pagamento ainda processando (webhook pode chegar em instantes)
redirect($base, 'Inscrição realizada! Aguardando confirmação do pagamento.', 'info');
