<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

// Obter configuração do sistema
function getConfig(string $chave, string $default = ''): string {
    static $cache = [];
    if (isset($cache[$chave])) return $cache[$chave];
    $db = getDB();
    $stmt = $db->prepare("SELECT valor FROM configuracoes WHERE chave = ?");
    $stmt->execute([$chave]);
    $row = $stmt->fetch();
    $cache[$chave] = $row ? ($row['valor'] ?? $default) : $default;
    return $cache[$chave];
}

// Obter todas as configurações
function getAllConfigs(): array {
    $db = getDB();
    $stmt = $db->query("SELECT chave, valor FROM configuracoes");
    $result = [];
    while ($row = $stmt->fetch()) {
        $result[$row['chave']] = $row['valor'];
    }
    return $result;
}

// Salvar configuração
function setConfig(string $chave, string $valor): void {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
    $stmt->execute([$chave, $valor, $valor]);
}

// Gerar CSRF token
function gerarCSRF(): string {
    initSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verificar CSRF token
function verificarCSRF(string $token): bool {
    initSession();
    if (empty($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Sanitizar saída HTML
function h(mixed $valor): string {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Gerar slug
function gerarSlug(string $texto): string {
    $texto = mb_strtolower($texto, 'UTF-8');
    $mapa = [
        'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'ç'=>'c','ñ'=>'n',
    ];
    $texto = strtr($texto, $mapa);
    $texto = preg_replace('/[^a-z0-9\s-]/', '', $texto);
    $texto = preg_replace('/[\s-]+/', '-', trim($texto));
    return $texto;
}

// Gerar slug único para curso
function gerarSlugUnico(string $nome, ?int $ignorarId = null): string {
    $db = getDB();
    $slug = gerarSlug($nome);
    $base = $slug;
    $i = 1;
    while (true) {
        $query = "SELECT id FROM cursos WHERE slug = ?";
        $params = [$slug];
        if ($ignorarId !== null) {
            $query .= " AND id != ?";
            $params[] = $ignorarId;
        }
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        if (!$stmt->fetch()) break;
        $slug = $base . '-' . $i++;
    }
    return $slug;
}

// Upload de imagem com segurança
function uploadImagem(array $arquivo, string $pasta): array {
    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        $erros = [
            UPLOAD_ERR_INI_SIZE   => 'Arquivo muito grande (limite do servidor).',
            UPLOAD_ERR_FORM_SIZE  => 'Arquivo muito grande.',
            UPLOAD_ERR_PARTIAL    => 'Upload incompleto.',
            UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo enviado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada.',
            UPLOAD_ERR_CANT_WRITE => 'Erro ao salvar arquivo.',
        ];
        return ['sucesso' => false, 'erro' => $erros[$arquivo['error']] ?? 'Erro desconhecido no upload.'];
    }

    if ($arquivo['size'] > MAX_FILE_SIZE) {
        return ['sucesso' => false, 'erro' => 'Arquivo excede 5MB.'];
    }

    // Verificar tipo real via finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $tipoReal = $finfo->file($arquivo['tmp_name']);
    if (!in_array($tipoReal, ALLOWED_IMAGE_TYPES, true)) {
        return ['sucesso' => false, 'erro' => 'Tipo de arquivo não permitido. Use JPG, PNG, WEBP ou GIF.'];
    }

    // Extensão segura
    $mimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
    $ext = $mimeToExt[$tipoReal];

    $nomeArquivo = bin2hex(random_bytes(16)) . '.' . $ext;
    $destino = UPLOAD_PATH . '/' . $pasta;

    if (!is_dir($destino)) {
        mkdir($destino, 0755, true);
    }

    $caminhoFinal = $destino . '/' . $nomeArquivo;
    if (!move_uploaded_file($arquivo['tmp_name'], $caminhoFinal)) {
        return ['sucesso' => false, 'erro' => 'Erro ao mover arquivo.'];
    }

    return ['sucesso' => true, 'arquivo' => $pasta . '/' . $nomeArquivo];
}

// Deletar arquivo de upload
function deletarUpload(string $caminho): void {
    if (empty($caminho)) return;
    $fullPath = UPLOAD_PATH . '/' . ltrim($caminho, '/');
    if (file_exists($fullPath)) {
        unlink($fullPath);
    }
}

// Formatar data em português
function formatarData(?string $data, string $formato = 'd/m/Y'): string {
    if (empty($data)) return '-';
    return date($formato, strtotime($data));
}

// Formatar data e hora
function formatarDataHora(?string $data): string {
    if (empty($data)) return '-';
    return date('d/m/Y \à\s H:i', strtotime($data));
}

// Formatar valor monetário
function formatarMoeda(float $valor): string {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

// Mascarar CPF para exibição parcial
function mascaraCPF(string $cpf): string {
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) !== 11) return $cpf;
    return substr($cpf, 0, 3) . '.***.***-' . substr($cpf, 9, 2);
}

// Validar CPF
function validarCPF(string $cpf): bool {
    $cpf = preg_replace('/\D/', '', $cpf);
    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
    for ($t = 9; $t < 11; $t++) {
        $d = 0;
        for ($c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) return false;
    }
    return true;
}

// Gerar código único para certificado
function gerarCodigoCertificado(): string {
    $db = getDB();
    do {
        $codigo = strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(4)));
        $stmt = $db->prepare("SELECT id FROM certificados WHERE codigo_unico = ?");
        $stmt->execute([$codigo]);
    } while ($stmt->fetch());
    return $codigo;
}

// Redirecionar com mensagem flash
function redirect(string $url, ?string $mensagem = null, string $tipo = 'success'): void {
    if ($mensagem !== null) {
        initSession();
        $_SESSION['flash'] = ['mensagem' => $mensagem, 'tipo' => $tipo];
    }
    // Prefixa BASE_PATH em URLs relativas (começam com / mas não com //)
    if (isset($url[0]) && $url[0] === '/' && (!isset($url[1]) || $url[1] !== '/')) {
        $url = rtrim(BASE_PATH, '/') . $url;
    }
    // Descarta qualquer saída em buffer (HTML do header já renderizado)
    // para garantir que o Location: chegue limpo ao cliente
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . $url);
    exit;
}

// Exibir e limpar mensagem flash
function getFlash(): ?array {
    initSession();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Verificar se URL de imagem é válida para exibição
function urlImagem(?string $caminho, string $default = ''): string {
    if (empty($caminho)) return $default;
    $fullPath = UPLOAD_PATH . '/' . ltrim($caminho, '/');
    if (file_exists($fullPath)) {
        return UPLOAD_URL . '/' . ltrim($caminho, '/');
    }
    return $default;
}

// Formatar tipo do curso
function formatarTipoCurso(string $tipo): string {
    return $tipo === 'online' ? 'Online' : 'Presencial';
}

// Formatar status pagamento
function formatarStatusPagamento(string $status): array {
    $mapa = [
        'pendente'   => ['label' => 'Pendente',   'class' => 'pendente'],
        'confirmado' => ['label' => 'Confirmado', 'class' => 'confirmado'],
        'cancelado'  => ['label' => 'Cancelado',  'class' => 'cancelado'],
    ];
    return $mapa[$status] ?? ['label' => $status, 'class' => 'inativo'];
}

// Formatar forma de pagamento
function formatarFormaPagamento(?string $forma): string {
    $mapa = [
        'pix'          => 'PIX',
        'transferencia' => 'Transferência',
        'deposito'     => 'Depósito Identificado',
        'cartao'       => 'Cartão',
    ];
    return $mapa[$forma] ?? ($forma ?? '-');
}

// Sanitizar entrada do usuário
function sanitize(mixed $valor): string {
    return trim(strip_tags((string)($valor ?? '')));
}

// Paginação simples
function paginar(int $total, int $pagina, int $porPagina = 20): array {
    $totalPaginas = max(1, (int)ceil($total / $porPagina));
    $pagina = max(1, min($pagina, $totalPaginas));
    return [
        'total'        => $total,
        'pagina'       => $pagina,
        'por_pagina'   => $porPagina,
        'total_paginas' => $totalPaginas,
        'offset'       => ($pagina - 1) * $porPagina,
    ];
}
