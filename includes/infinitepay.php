<?php
if (!function_exists('getDB')) exit(0);

class InfinitePay
{
    const LINKS_URL        = 'https://api.checkout.infinitepay.io/links';
    const PAYMENT_CHECK_URL = 'https://api.checkout.infinitepay.io/payment_check';

    private string $handle;

    public function __construct()
    {
        $this->handle = getConfig('infinitepay_handle');
    }

    public function isConfigured(): bool
    {
        return !empty($this->handle);
    }

    public function criarLink(array $dados): array
    {
        $valor = (float)$dados['valor'];
        if ($valor <= 0) {
            return ['sucesso' => false, 'erro' => 'Valor do curso inválido.'];
        }

        $telefone = preg_replace('/\D/', '', $dados['telefone'] ?? '');
        $body = [
            'handle'       => $this->handle,
            'redirect_url' => $dados['redirect_url'],
            'webhook_url'  => $dados['webhook_url'],
            'order_nsu'    => $dados['order_nsu'],
            'customer'     => [
                'name'         => $dados['nome'],
                'email'        => $dados['email'],
                'phone_number' => $telefone ? '+55' . $telefone : '',
            ],
            'items' => [[
                'quantity'    => 1,
                'price'       => (int)round($valor * 100),
                'description' => mb_substr($dados['curso_nome'], 0, 200),
            ]],
        ];

        $ch = curl_init(self::LINKS_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
        ]);

        $respBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return ['sucesso' => false, 'erro' => 'Erro de conexão com o gateway de pagamento.'];
        }

        $resp = json_decode($respBody, true);

        if ($httpCode >= 200 && $httpCode < 300 && !empty($resp['url'])) {
            return ['sucesso' => true, 'url' => $resp['url']];
        }

        return ['sucesso' => false, 'erro' => 'Não foi possível gerar o link de pagamento. Tente novamente.'];
    }

    public function verificarPagamento(string $orderNsu, string $invoiceSlug = '', string $txNsu = ''): bool
    {
        $body = ['handle' => $this->handle, 'order_nsu' => $orderNsu];
        if ($invoiceSlug !== '') $body['invoice_slug']    = $invoiceSlug;
        if ($txNsu      !== '') $body['transaction_nsu']  = $txNsu;

        $ch = curl_init(self::PAYMENT_CHECK_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($body),
        ]);

        $resp     = json_decode(curl_exec($ch), true);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Accept paid=true OR paid_amount > 0 (InfinitePay uses either depending on endpoint version)
        return $httpCode === 200
            && (!empty($resp['paid']) || (isset($resp['paid_amount']) && (int)$resp['paid_amount'] > 0));
    }

    public static function migrarColunas(): void
    {
        try {
            $db = getDB();
            $cols = $db->query("SHOW COLUMNS FROM inscricoes LIKE 'payment_id'")->fetchColumn();
            if (!$cols) {
                $db->exec("ALTER TABLE inscricoes ADD COLUMN payment_id VARCHAR(200) NULL AFTER observacoes");
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
