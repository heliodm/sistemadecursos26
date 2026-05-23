<?php
// Sistema de atualização via GitHub — compatível com cPanel (ZIP, sem git)

// Arquivos/pastas nunca sobrescritos durante atualização
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
        if (is_array($data) && !empty($data['commit'])) return $data;
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

// ── Download via file_get_contents ou cURL ──────────────────────────────────

function upd_download(string $url, array $headers = [], int $timeout = 30): string|false {
    // Tenta file_get_contents (allow_url_fopen)
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
        if ($result !== false && strlen($result) > 0) return $result;
    }

    // Fallback: cURL
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
    }

    return false;
}

// ── Capacidades do servidor ──────────────────────────────────────────────────

function upd_canDownload(): bool {
    return (bool)ini_get('allow_url_fopen') || function_exists('curl_init');
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
    $raw = upd_download($url, $headers, 10);

    $info['checked_at'] = date('Y-m-d H:i:s');
    $info['api_error']  = null;

    if ($raw !== false && strlen($raw) > 10) {
        $data = json_decode($raw, true);
        if (isset($data['sha'])) {
            $short = substr($data['sha'], 0, 7);
            $info['latest_sha']       = $data['sha'];
            $info['latest_commit']    = $short;
            $info['latest_message']   = trim($data['commit']['message'] ?? '');
            $info['latest_date']      = $data['commit']['committer']['date'] ?? '';
            $info['latest_author']    = $data['commit']['author']['name'] ?? '';
            $info['update_available'] = (
                $info['commit'] !== 'desconhecido' &&
                $short !== $info['commit']
            );
        } elseif (!empty($data['message'])) {
            $info['api_error'] = $data['message'];
        } else {
            $info['api_error'] = 'Resposta inválida da API do GitHub.';
        }
    } else {
        $info['api_error'] = 'Não foi possível conectar à API do GitHub.';
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
        return ['sucesso' => false, 'output' => 'Nenhum método de download disponível. Habilite allow_url_fopen ou a extensão cURL no PHP (cPanel → Selecionar Versão do PHP → Extensões).'];
    }
    if (!upd_canExtract()) {
        return ['sucesso' => false, 'output' => 'A extensão ZipArchive não está disponível. Ative-a em: cPanel → Selecionar Versão do PHP → Extensões → zip.'];
    }
    if (!upd_canWrite()) {
        return ['sucesso' => false, 'output' => 'Sem permissão de escrita no diretório do sistema. Ajuste as permissões via cPanel → Gerenciador de Arquivos.'];
    }

    @set_time_limit(300);
    @ini_set('memory_limit', '256M');

    $info   = upd_readVersion();
    $repo   = $info['repo']   ?? 'heliodm/sistemadecursos26';
    $branch = $info['branch'] ?? 'claude/eager-hawking-fdE84';
    $sha    = $info['latest_sha'] ?? null;
    $token  = function_exists('getConfig') ? getConfig('github_token', '') : '';

    // Montar URL e headers do download
    $headers = ['User-Agent: SistemaCursos-Updater/1.0'];
    if ($token) {
        // Via API — funciona para repos públicos e privados
        $ref     = $sha ?? $branch;
        $zipUrl  = "https://api.github.com/repos/{$repo}/zipball/{$ref}";
        $headers[] = 'Authorization: Bearer ' . $token;
        $headers[] = 'Accept: application/vnd.github+json';
    } else {
        // URL pública do GitHub (apenas repos públicos)
        $ref    = $sha ?? "refs/heads/{$branch}";
        $zipUrl = "https://github.com/{$repo}/archive/{$ref}.zip";
    }

    // 1. Baixar ZIP
    $zipContent = upd_download($zipUrl, $headers, 180);
    if ($zipContent === false || strlen($zipContent) < 200) {
        $dica = !$token ? ' Dica: configure um GitHub Token se o repositório for privado.' : '';
        return ['sucesso' => false, 'output' => "Falha ao baixar a atualização.{$dica}\nURL: {$zipUrl}"];
    }

    // 2. Salvar ZIP temporariamente dentro de config/ (garantidamente gravável)
    $tmpTag = time() . '_' . substr(md5(mt_rand()), 0, 6);
    $tmpZip = ROOT_PATH . '/config/.upd_' . $tmpTag . '.zip';
    $tmpDir = ROOT_PATH . '/config/.upd_' . $tmpTag;

    if (!@file_put_contents($tmpZip, $zipContent)) {
        return ['sucesso' => false, 'output' => 'Sem permissão para gravar arquivo temporário em ' . ROOT_PATH . '/config/'];
    }
    unset($zipContent); // libera memória

    // 3. Extrair ZIP
    $zip = new ZipArchive();
    $res = $zip->open($tmpZip);
    if ($res !== true) {
        @unlink($tmpZip);
        return ['sucesso' => false, 'output' => 'Falha ao abrir o arquivo ZIP (código ZipArchive: ' . $res . ')'];
    }

    @mkdir($tmpDir, 0755, true);
    $extracted = $zip->extractTo($tmpDir);
    $zip->close();
    @unlink($tmpZip);

    if (!$extracted) {
        upd_rrmdir($tmpDir);
        return ['sucesso' => false, 'output' => 'Falha ao extrair o arquivo ZIP.'];
    }

    // 4. Encontrar subdiretório gerado pelo GitHub (ex: repo-abc1234/)
    $subdirs = array_filter((array)glob($tmpDir . '/*'), 'is_dir');
    if (empty($subdirs)) {
        upd_rrmdir($tmpDir);
        return ['sucesso' => false, 'output' => 'Estrutura inesperada no arquivo ZIP extraído.'];
    }
    $srcDir = (string)reset($subdirs);

    // 5. Copiar arquivos, respeitando lista de preservação
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

    // 6. Limpar temporários
    upd_rrmdir($tmpDir);

    if (!empty($errors)) {
        $errsText = implode(', ', array_slice($errors, 0, 5)) . (count($errors) > 5 ? '…' : '');
        return ['sucesso' => false, 'output' => "Copiados {$copied} arquivo(s). Erro em " . count($errors) . " arquivo(s): {$errsText}"];
    }

    // 7. Gravar nova versão
    $newCommit = $sha ? substr($sha, 0, 7) : ($info['latest_commit'] ?? 'unknown');
    $info['commit']           = $newCommit;
    $info['update_available'] = false;
    $info['updated_at']       = date('Y-m-d H:i:s');
    upd_writeVersion($info);

    return ['sucesso' => true, 'output' => "{$copied} arquivo(s) atualizado(s) com sucesso.", 'commit' => $newCommit];
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
