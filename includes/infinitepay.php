<?php
if (!function_exists('getDB')) exit(0);

class InfinitePay
{
    private string $clientId;
    private string $clientSecret;
    private string $ambiente;
    private string $oauthUrl;
    private string $transactionsUrl;
    private string $ipayJsUrl;

    const TAX = [
        1.0000, 1.3390, 1.5041, 1.5992, 1.6630,
        1.7057, 2.3454, 2.3053, 2.2755, 2.2490, 2.2306, 2.2111,
    ];

    public function __construct()
    {
        $this->clientId     = getConfig('infinitepay_client_id');
        $this->clientSecret = getConfig('infinitepay_client_secret');
        $this->ambiente     = getConfig('infinitepay_ambiente', 'producao');

        if ($this->ambiente === 'sandbox') {
            $this->oauthUrl        = 'https://api-staging.infinitepay.io/v2/oauth/token';
            $this->transactionsUrl = 'https://authorizer-staging.infinitepay.io/v2/transactions';
            $this->ipayJsUrl       = 'https://ipayjs.infinitepay.io/development/ipay-latest.min.js';
        } else {
            $this->oauthUrl        = 'https://api.infinitepay.io/v2/oauth/token';
            $this->transactionsUrl = 'https://api.infinitepay.io/v2/transactions';
            $this->ipayJsUrl       = 'https://ipayjs.infinitepay.io/production/ipay-latest.min.js';
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret);
    }

    public function getIpayJsUrl(): string
    {
        return $this->ipayJsUrl;
    }

    public function obterTokenTokenizacao(): ?string
    {
        return $this->getAccessToken('card_tokenization');
    }

    public function calcularParcelas(float $total): array
    {
        $maxParcelas = max(1, min(12, (int)getConfig('infinitepay_max_parcelas', '12')));
        $result = [];
        for ($i = 1; $i <= $maxParcelas; $i++) {
            $tax   = self::TAX[$i - 1];
            $total_com_taxa = round($total * $tax, 2);
            $por_parcela    = round($total_com_taxa / $i, 2);
            $result[] = [
                'n'           => $i,
                'por_parcela' => $por_parcela,
                'total'       => $total_com_taxa,
                'tem_juros'   => $tax > 1.0001,
            ];
        }
        return $result;
    }

    public function criarTransacao(array $dados): array
    {
        $token = $this->getAccessToken('transactions');
        if (!$token) {
            return ['sucesso' => false, 'erro' => 'Falha na autenticação com o gateway de pagamento.'];
        }

        $n      = max(1, min(12, (int)$dados['parcelas']));
        $tax    = self::TAX[$n - 1];
        $totalComTaxa = round($dados['valor'] * $tax, 2);
        $porParcela   = round($totalComTaxa / $n, 2);
        $finalVal     = ($n === 1) ? (int)($dados['valor'] * 100) : (int)($n * $porParcela * 100);
        $nsu    = $this->gerarNSU();

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $token,
        ];
        if ($this->ambiente === 'sandbox') {
            $headers[] = 'Env: mock';
        }

        $body = [
            'origin'  => 'ecommerce',
            'payment' => [
                'amount'         => $finalVal,
                'installments'   => $n,
                'capture_method' => 'ecommerce',
                'origin'         => 'ecommerce',
                'payment_method' => 'credit',
                'nsu'            => $nsu,
            ],
            'card' => [
                'cvv'              => $dados['cvv'],
                'token'            => $dados['token'],
                'card_holder_name' => strtoupper($dados['nome_cartao']),
            ],
            'order' => [
                'id'     => (string)$dados['inscricao_ref'],
                'amount' => $finalVal,
                'items'  => [[
                    'id'          => (string)$dados['curso_id'],
                    'description' => mb_substr($dados['curso_nome'], 0, 100),
                    'amount'      => $finalVal,
                    'quantity'    => 1,
                ]],
                'delivery_details' => [
                    'email'        => $dados['email'],
                    'name'         => $dados['nome'],
                    'phone_number' => $this->formatarTelefone($dados['telefone']),
                    'address'      => [
                        'line1'   => $dados['endereco'] ?: '',
                        'line2'   => '',
                        'city'    => '',
                        'state'   => '',
                        'zip'     => '',
                        'country' => 'BR',
                    ],
                ],
            ],
            'customer' => [
                'document_number' => preg_replace('/\D/', '', $dados['cpf']),
                'email'           => $dados['email'],
                'first_name'      => $this->primeiroNome($dados['nome']),
                'last_name'       => $this->sobrenome($dados['nome']),
                'phone_number'    => $this->formatarTelefone($dados['telefone']),
                'address'         => $dados['endereco'] ?: '',
                'complement'      => '',
                'city'            => '',
                'state'           => '',
                'zip'             => '',
                'country'         => 'BR',
            ],
            'billing_details' => [
                'address' => [
                    'line1'   => $dados['endereco'] ?: '',
                    'line2'   => '',
                    'city'    => '',
                    'state'   => '',
                    'zip'     => '',
                    'country' => 'BR',
                ],
            ],
            'metadata' => [
                'origin'         => 'ecommerce',
                'platform'       => 'custom',
                'plugin_version' => '1.0.0',
                'store_url'      => $_SERVER['HTTP_HOST'] ?? '',
                'payment_method' => 'credit',
                'risk'           => [
                    'session_id' => $dados['session_id'] ?? '',
                    'payer_ip'   => $this->ipPagador(),
                ],
            ],
        ];

        $ch = curl_init($this->transactionsUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_HTTPHEADER     => $headers,
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

        if ($httpCode >= 500) {
            return ['sucesso' => false, 'erro' => 'Erro interno no gateway. Tente novamente em instantes.'];
        }

        $authCode   = $resp['data']['attributes']['authorization_code'] ?? '';
        $authReason = strtolower($resp['data']['attributes']['authorization_reason'] ?? '');
        $txId       = $resp['data']['id'] ?? '';

        if ($authCode === '00' || $authReason === 'approved') {
            return [
                'sucesso'    => true,
                'payment_id' => $txId ?: $nsu,
                'parcelas'   => $n,
            ];
        }

        return [
            'sucesso' => false,
            'erro'    => $this->mensagemErro($authCode ?: (string)$httpCode),
            'codigo'  => $authCode,
        ];
    }

    public static function migrarColunas(): void
    {
        try {
            $db = getDB();
            $cols = $db->query("SHOW COLUMNS FROM inscricoes LIKE 'payment_id'")->fetchColumn();
            if (!$cols) {
                $db->exec("ALTER TABLE inscricoes ADD COLUMN payment_id VARCHAR(100) NULL AFTER observacoes");
            }
            $cols2 = $db->query("SHOW COLUMNS FROM inscricoes LIKE 'payment_installments'")->fetchColumn();
            if (!$cols2) {
                $db->exec("ALTER TABLE inscricoes ADD COLUMN payment_installments TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER payment_id");
            }
        } catch (\Throwable $e) {
            // ignore — columns may already exist
        }
    }

    private function getAccessToken(string $scope): ?string
    {
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($this->ambiente === 'sandbox') {
            $headers[] = 'Env: mock';
        }

        $ch = curl_init($this->oauthUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => json_encode([
                'grant_type'    => 'client_credentials',
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope'         => $scope,
            ]),
        ]);

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $code !== 200) {
            return null;
        }
        $data = json_decode($body, true);
        return $data['access_token'] ?? null;
    }

    private function mensagemErro(string $code): string
    {
        $erros = [
            '4'  => 'Cartão não autorizado. Contate o banco emissor ou tente com outro cartão.',
            '5'  => 'Cartão não autorizado. Contate o banco emissor ou tente com outro cartão.',
            '6'  => 'Ocorreu um erro inesperado. Verifique os dados do cartão.',
            '7'  => 'Cartão não autorizado. Contate o banco emissor.',
            '12' => 'Transação inválida. Verifique os dados do cartão.',
            '13' => 'Valor inválido. Verifique o total do pagamento.',
            '14' => 'Número de cartão inválido.',
            '15' => 'Não foi possível completar o pagamento com este cartão.',
            '30' => 'Número do cartão inválido.',
            '41' => 'Cartão bloqueado. Contate o banco emissor.',
            '46' => 'Cartão não autorizado.',
            '51' => 'Saldo insuficiente. Verifique o limite disponível.',
            '54' => 'Cartão expirado. Verifique a data de validade.',
            '57' => 'Transação não permitida para este cartão.',
            '58' => 'Cartão inválido para esta operação.',
            '59' => 'Pagamento não autorizado. Tente outro cartão.',
            '61' => 'Limite excedido. Verifique o limite de compras.',
            '62' => 'Cartão não autorizado para esta transação.',
            '63' => 'Código de segurança (CVV) inválido.',
            '78' => 'Cartão não autorizado.',
            '83' => 'Transação não autorizada. Confirme os dados e tente novamente.',
        ];
        return $erros[$code] ?? 'Pagamento não autorizado. Verifique os dados do cartão e tente novamente.';
    }

    private function gerarNSU(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function ipPagador(): string
    {
        return $_SERVER['HTTP_CLIENT_IP']
            ?? $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '';
    }

    private function primeiroNome(string $nome): string
    {
        $partes = explode(' ', trim($nome));
        return $partes[0] ?? $nome;
    }

    private function sobrenome(string $nome): string
    {
        $partes = explode(' ', trim($nome), 2);
        return $partes[1] ?? '';
    }

    private function formatarTelefone(string $tel): string
    {
        $num = preg_replace('/\D/', '', $tel);
        return strlen($num) >= 10 ? '+55' . $num : ($num ?: '');
    }
}
