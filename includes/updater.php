<?php
// Sistema de atualização via GitHub — compatível com cPanel (ZIP, sem git)

const UPD_PRESERVE = [
    'config/database.php',
    'config/.installed',
    'config/config.php',
    'config/.version',
    'uploads',
    'logs',
];

function upd_versionFile(): string {
    return ROOT_PATH . '/config/.version';
}

function upd_readVersion(): array {
    $file = upd_versionFile();
    if (file_exists($file)) {
        $data = json_decode((string)file_get_contents($file), true);
        if (is_array($data) && isset($data['commit'])) return $data;
    }
    return upd_initVersion();
}

function upd_writeVersion(array $data): void {
    @file_put_contents(upd_versionFile(), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function upd_initVersion(): array {
    $data = [
        'commit'           => 'desconhecido',
        'branch'           => 'claude/eager-hawking-fdE84',
        'repo'             => 'heliodm/sistemadecursos26',
        'updated_at'       => date('Y-m-d H:i:s'),
        'checked_at'       => null,
        'latest_sha'       => null,
        'latest_commit'    => null,
        'latest_message'   => null,
        'latest_date'      => null,
        'latest_author'    => null,
        'update_available' => false,
        'api_error'        => null,
    ];
    upd_writeVersion($data);
    return $data;
}

// ── Download para chamadas de API (JSON) ─────────────────────────────────────

function upd_download(string $url, array $headers = [], int $timeout = 30): string|false {
    // cURL preferido: melhor tratamento de redirecionamentos e códigos HTTP
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERAGENT      => 'SistemaCursos-Updater/1.0',
            CURLOPT_ENCODING       => '',
        ]);
        $result = curl_exec($ch);
        $code   = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($result !== false && $code === 200) return (string)$result;
        // Se houve resposta HTTP (mesmo que erro), não tenta file_get_contents
        if ($code > 0) return false;
    }

    // Fallback: file_get_contents
    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'header'          => implode("\r\n", $headers),
                'timeout'         => $timeout,
                'follow_location' => true,
                'max_redirects'   => 10,
                'ignore_errors'   => true,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $result = @file_get_contents($url, false, $ctx);
        if ($result !== false && strlen($result) > 0) {
            // Verifica o último código HTTP na cadeia de redirecionamentos
            $lastStatus = 0;
            foreach ((array)($http_response_header ?? []) as $h) {
                if (preg_match('/^HTTP\/\S+\s+(\d+)/', $h, $m)) {
                    $lastStatus = (int)$m[1];
                }
            }
            if ($lastStatus === 200 || $lastStatus === 0) return $result;
        }
    }

    return false;
}

// ── Download de arquivo binário (ZIP) via streaming ──────────────────────────

function upd_downloadFile(string $url, string $destPath, array $headers = [], int $timeout = 180): array {
    // cURL streaming: sem carregar o ZIP inteiro na memória
    if (function_exists('curl_init')) {
        $fp = @fopen($destPath, 'wb');
        if (!$fp) {
            return ['ok' => false, 'error' => 'Sem permissão para criar arquivo temporário: ' . $destPath];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERAGENT      => 'SistemaCursos-Updater/1.0',
            // Por padrão, cURL NÃO reenvia Authorization em redirecionamentos cross-domain
            CURLOPT_UNRESTRICTED_AUTH => false,
        ]);
        $ok   = (bool)curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $size = (int)curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
        $err  = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($ok && $code === 200 && $size > 100) {
            // Valida bytes mágicos do ZIP (PK)
            $fh    = @fopen($destPath, 'rb');
            $magic = $fh ? fread($fh, 2) : '';
            if ($fh) fclose($fh);
            if ($magic === 'PK') {
                return ['ok' => true, 'error' => '', 'size' => $size];
            }
            @unlink($destPath);
            return ['ok' => false, 'error' => "Arquivo baixado não é um ZIP válido (HTTP {$code}, {$size} bytes). URL: {$url}"];
        }
        @unlink($destPath);
        $msg = "HTTP {$code}";
        if ($err) $msg .= " — {$err}";
        return ['ok' => false, 'error' => "Falha no download via cURL ({$msg}). URL: {$url}"];
    }

    // Fallback: file_get_contents (carrega na memória)
    if (ini_get('allow_url_fopen')) {
        // Remove Authorization para evitar conflito em redirecionamentos (S3 / codeload)
        $safeHeaders = array_values(array_filter($headers, fn($h) => stripos($h, 'authorization:') === false));
        $ctx = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'header'          => implode("\r\n", $safeHeaders),
                'timeout'         => $timeout,
                'follow_location' => true,
                'max_redirects'   => 10,
                'ignore_errors'   => true,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $content = @file_get_contents($url, false, $ctx);
        if ($content !== false && strlen($content) > 100 && substr($content, 0, 2) === 'PK') {
            if (@file_put_contents($destPath, $content)) {
                return ['ok' => true, 'error' => '', 'size' => strlen($content)];
            }
            return ['ok' => false, 'error' => 'Sem permissão para gravar arquivo temporário: ' . $destPath];
        }
        return ['ok' => false, 'error' => 'Download via allow_url_fopen falhou ou retornou conteúdo inválido. Dica: habilite a extensão cURL no cPanel para melhor compatibilidade.'];
    }

    return ['ok' => false, 'error' => 'Nenhum método de download disponível (cURL ou allow_url_fopen).'];
}

// ── Capacidades do servidor ──────────────────────────────────────────────────

function upd_canDownload(): bool {
    return function_exists('curl_init') || (bool)ini_get('allow_url_fopen');
}

function upd_canExtract(): bool {
    return class_exists('ZipArchive');
}

function upd_canWrite(): bool {
    return is_writable(ROOT_PATH) && is_writable(ROOT_PATH . '/config');
}

function upd_canUpdate(): bool {
    return upd_canDownload() && upd_canExtract() && upd_canWrite();
}

// ── Verificação no GitHub ────────────────────────────────────────────────────

function upd_checkGithub(bool $force = false): array {
    $info = upd_readVersion();

    // Cache de 1 hora
    if (!$force && !empty($info['checked_at'])) {
        if ((time() - (int)strtotime($info['checked_at'])) < 3600) {
            return $info;
        }
    }

    $repo   = $info['repo']   ?? 'heliodm/sistemadecursos26';
    $branch = $info['branch'] ?? 'claude/eager-hawking-fdE84';
    $token  = function_exists('getConfig') ? getConfig('github_token', '') : '';

    $headers = [
        'User-Agent: SistemaCursos-Updater/1.0',
        'Accept: application/vnd.github.v3+json',
    ];
    if ($token) $headers[] = 'Authorization: Bearer ' . $token;

    $url = "https://api.github.com/repos/{$repo}/commits/{$branch}";
    $raw = upd_download($url, $headers, 15);

    $info['checked_at'] = date('Y-m-d H:i:s');
    $info['api_error']  = null;

    if ($raw === false) {
        $info['api_error'] = 'Não foi possível conectar à API do GitHub. Verifique a conectividade do servidor.';
    } else {
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $info['api_error'] = 'Resposta inválida da API do GitHub (não é JSON). Verifique o token e o nome do branch.';
        } elseif (isset($data['sha'])) {
            $short = substr($data['sha'], 0, 7);

            // Auto-inicializa a versão instalada na primeira verificação
            if (($info['commit'] ?? 'desconhecido') === 'desconhecido') {
                $info['commit'] = $short;
            }

            $info['latest_sha']       = $data['sha'];
            $info['latest_commit']    = $short;
            $info['latest_message']   = trim($data['commit']['message'] ?? '');
            $info['latest_date']      = $data['commit']['committer']['date'] ?? '';
            $info['latest_author']    = $data['commit']['author']['name'] ?? '';
            $info['update_available'] = ($short !== $info['commit']);
        } elseif (!empty($data['message'])) {
            $info['api_error'] = 'GitHub API: ' . $data['message'];
        } else {
            $info['api_error'] = 'Resposta inesperada da API do GitHub.';
        }
    }

    upd_writeVersion($info);
    return $info;
}

// Leitura rápida do cache — sem rede (usado no cabeçalho de cada página)
function upd_hasUpdate(): bool {
    try {
        $info = upd_readVersion();
        return !empty($info['update_available']);
    } catch (\Throwable $e) {
        return false;
    }
}

// ── Aplicação da atualização via ZIP ────────────────────────────────────────

function upd_executeUpdate(): array {
    if (!upd_canDownload()) {
        return ['sucesso' => false, 'output' => 'Nenhum método de download disponível. Ative allow_url_fopen ou a extensão cURL (cPanel → Selecionar Versão do PHP → Extensões).'];
    }
    if (!upd_canExtract()) {
        return ['sucesso' => false, 'output' => 'Extensão ZipArchive indisponível. Ative em: cPanel → Selecionar Versão do PHP → Extensões → zip.'];
    }
    if (!upd_canWrite()) {
        return ['sucesso' => false, 'output' => 'Sem permissão de escrita no diretório do sistema. Ajuste via cPanel → Gerenciador de Arquivos (permissão 755).'];
    }

    @set_time_limit(300);
    @ini_set('memory_limit', '256M');

    $info   = upd_readVersion();
    $repo   = $info['repo']   ?? 'heliodm/sistemadecursos26';
    $branch = $info['branch'] ?? 'claude/eager-hawking-fdE84';
    $sha    = $info['latest_sha'] ?? null;
    $token  = function_exists('getConfig') ? getConfig('github_token', '') : '';

    // Monta URL e headers
    $headers = ['User-Agent: SistemaCursos-Updater/1.0'];
    if ($token) {
        $ref    = $sha ?? $branch;
        $zipUrl = "https://api.github.com/repos/{$repo}/zipball/{$ref}";
        $headers[] = 'Authorization: Bearer ' . $token;
        $headers[] = 'Accept: application/vnd.github+json';
    } else {
        $ref    = $sha ?? 'refs/heads/' . $branch;
        $zipUrl = "https://github.com/{$repo}/archive/{$ref}.zip";
    }

    // 1. Baixar ZIP direto para arquivo temporário
    $tmpTag = time() . '_' . substr(md5(mt_rand()), 0, 6);
    $tmpZip = ROOT_PATH . '/config/.upd_' . $tmpTag . '.zip';
    $tmpDir = ROOT_PATH . '/config/.upd_' . $tmpTag;

    $dl = upd_downloadFile($zipUrl, $tmpZip, $headers, 180);
    if (!$dl['ok']) {
        $dica = '';
        if (!$token) {
            $dica = "\nDica: configure um GitHub Token nas configurações se o repositório for privado.";
        }
        if (!function_exists('curl_init')) {
            $dica .= "\nDica: habilite a extensão cURL no cPanel para melhor compatibilidade com redirecionamentos do GitHub.";
        }
        return ['sucesso' => false, 'output' => $dl['error'] . $dica];
    }

    $sizeKb = round(($dl['size'] ?? @filesize($tmpZip)) / 1024);

    // 2. Extrair ZIP
    $zip = new ZipArchive();
    $res = $zip->open($tmpZip);
    if ($res !== true) {
        @unlink($tmpZip);
        return ['sucesso' => false, 'output' => "Falha ao abrir o arquivo ZIP (código ZipArchive: {$res}, tamanho: {$sizeKb} KB)."];
    }

    @mkdir($tmpDir, 0755, true);
    $extracted = $zip->extractTo($tmpDir);
    $total     = $zip->count();
    $zip->close();
    @unlink($tmpZip);

    if (!$extracted) {
        upd_rrmdir($tmpDir);
        return ['sucesso' => false, 'output' => "Falha ao extrair o arquivo ZIP ({$total} entradas, {$sizeKb} KB)."];
    }

    // 3. Encontrar subdiretório gerado pelo GitHub (ex: repo-abc1234/)
    $subdirs = array_filter((array)glob($tmpDir . '/*'), 'is_dir');
    if (empty($subdirs)) {
        upd_rrmdir($tmpDir);
        return ['sucesso' => false, 'output' => 'Estrutura inesperada no arquivo ZIP extraído.'];
    }
    $srcDir = (string)reset($subdirs);

    // 4. Copiar arquivos, preservando config/uploads/logs
    $copied = 0;
    $errors = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $rel  = str_replace('\\', '/', substr($item->getPathname(), strlen($srcDir) + 1));
        $dest = ROOT_PATH . '/' . $rel;

        foreach (UPD_PRESERVE as $p) {
            if ($rel === $p || str_starts_with($rel, rtrim($p, '/') . '/')) {
                continue 2;
            }
        }

        if ($item->isDir()) {
            if (!is_dir($dest)) @mkdir($dest, 0755, true);
        } else {
            if (@copy($item->getPathname(), $dest)) {
                $copied++;
            } else {
                $errors[] = $rel;
            }
        }
    }

    // 5. Limpar temporários
    upd_rrmdir($tmpDir);

    if (!empty($errors)) {
        $errsText = implode(', ', array_slice($errors, 0, 5)) . (count($errors) > 5 ? '…' : '');
        return ['sucesso' => false, 'output' => "Copiados {$copied} arquivo(s). Erro em " . count($errors) . " arquivo(s): {$errsText}"];
    }

    // 6. Grava nova versão
    $newCommit = $sha ? substr($sha, 0, 7) : ($info['latest_commit'] ?? 'unknown');
    $info['commit']           = $newCommit;
    $info['update_available'] = false;
    $info['updated_at']       = date('Y-m-d H:i:s');
    upd_writeVersion($info);

    return [
        'sucesso' => true,
        'output'  => "{$copied} arquivo(s) atualizado(s) com sucesso. ({$sizeKb} KB baixados)",
        'commit'  => $newCommit,
    ];
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function upd_rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}

function upd_formatDate(string $iso): string {
    if (empty($iso)) return '—';
    try {
        $dt = new DateTime($iso);
        $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
        return $dt->format('d/m/Y H:i');
    } catch (\Throwable $e) {
        return $iso;
    }
}
