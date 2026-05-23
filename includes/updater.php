<?php
// Sistema de atualização automática via GitHub

function upd_versionFile(): string {
    return ROOT_PATH . '/config/.version';
}

function upd_readVersion(): array {
    $file = upd_versionFile();
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data) && !empty($data['commit'])) return $data;
    }
    return upd_initVersion();
}

function upd_writeVersion(array $data): void {
    @file_put_contents(upd_versionFile(), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function upd_initVersion(): array {
    $hash = upd_localHash();
    $data = [
        'commit'           => $hash,
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

function upd_localHash(): string {
    if (!upd_canExec()) return 'desconhecido';
    $out = [];
    @exec('git -C ' . escapeshellarg(ROOT_PATH) . ' rev-parse --short HEAD 2>/dev/null', $out, $code);
    return ($code === 0 && !empty($out[0])) ? trim($out[0]) : 'desconhecido';
}

function upd_canExec(): bool {
    if (!function_exists('exec')) return false;
    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    return !in_array('exec', $disabled, true);
}

function upd_canGit(): bool {
    if (!upd_canExec()) return false;
    $out = [];
    @exec('git --version 2>&1', $out, $code);
    return $code === 0;
}

function upd_isGitRepo(): bool {
    if (!upd_canExec()) return false;
    $out = [];
    @exec('git -C ' . escapeshellarg(ROOT_PATH) . ' rev-parse --git-dir 2>&1', $out, $code);
    return $code === 0;
}

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

    $ctx = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'header'        => implode("\r\n", $headers),
            'timeout'       => 10,
            'ignore_errors' => true,
        ],
    ]);

    $url = "https://api.github.com/repos/{$repo}/commits/{$branch}";
    $raw = @file_get_contents($url, false, $ctx);

    $info['checked_at'] = date('Y-m-d H:i:s');
    $info['api_error']  = null;

    if ($raw !== false) {
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
                $short          !== $info['commit']
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

// Leitura rápida do cache — sem chamada de rede (usado no header)
function upd_hasUpdate(): bool {
    try {
        $info = upd_readVersion();
        // Se nunca verificou ou cache expirou, agenda verificação mas não bloqueia
        if (empty($info['checked_at']) || (time() - (int)strtotime($info['checked_at'])) > 3600) {
            // Verificação em background: ignora erro se falhar
            @upd_checkGithub();
            $info = upd_readVersion();
        }
        return !empty($info['update_available']);
    } catch (\Throwable $e) {
        return false;
    }
}

function upd_executeUpdate(): array {
    if (!upd_canGit()) {
        return ['sucesso' => false, 'output' => 'Git não está disponível neste servidor. Faça a atualização manualmente via FTP/SSH.'];
    }

    if (!upd_isGitRepo()) {
        return ['sucesso' => false, 'output' => 'O diretório do sistema não é um repositório Git. Configure o Git no servidor para usar a atualização automática.'];
    }

    $root   = ROOT_PATH;
    $info   = upd_readVersion();
    $branch = escapeshellarg($info['branch'] ?? 'claude/eager-hawking-fdE84');
    $all    = [];

    @exec('git -C ' . escapeshellarg($root) . ' fetch --all 2>&1', $o1, $c1);
    $all = array_merge($all, $o1);

    @exec('git -C ' . escapeshellarg($root) . ' pull origin ' . $branch . ' 2>&1', $o2, $c2);
    $all = array_merge($all, $o2);

    if ($c2 === 0) {
        $newHash = upd_localHash();
        $info['commit']           = $newHash;
        $info['update_available'] = false;
        $info['updated_at']       = date('Y-m-d H:i:s');
        upd_writeVersion($info);
        return ['sucesso' => true, 'output' => implode("\n", $all), 'commit' => $newHash];
    }

    return ['sucesso' => false, 'output' => implode("\n", $all)];
}

function upd_formatDate(string $iso): string {
    try {
        $dt = new DateTime($iso);
        $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
        return $dt->format('d/m/Y H:i');
    } catch (\Throwable $e) {
        return $iso;
    }
}
