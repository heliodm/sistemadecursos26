<?php
/**
 * Instalador do Sistema de Cursos
 * Acesse: /install.php
 * IMPORTANTE: Exclua este arquivo do servidor após a instalação!
 */

// Bloquear acesso após instalação bem-sucedida
if (file_exists(__DIR__ . '/config/.installed')) {
    http_response_code(403);
    die('Sistema já instalado. Exclua o arquivo install.php do servidor.');
}

define('ROOT_PATH', __DIR__);

// ──────────────── Helpers ────────────────
function checkReq(string $ext): bool {
    return extension_loaded($ext);
}
function phpOk(): bool {
    return version_compare(PHP_VERSION, '8.0.0', '>=');
}
function dirWritable(string $path): bool {
    return is_dir($path) && is_writable($path);
}
function escHtml(mixed $v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ──────────────── Requisitos ────────────────
$requirements = [
    ['label' => 'PHP 8.0+',            'ok' => phpOk(),           'info' => 'Versão atual: ' . PHP_VERSION],
    ['label' => 'Extensão PDO',        'ok' => checkReq('pdo'),   'info' => ''],
    ['label' => 'Extensão PDO MySQL',  'ok' => checkReq('pdo_mysql'), 'info' => ''],
    ['label' => 'Extensão GD / Imagem','ok' => checkReq('gd'),    'info' => 'Necessário para uploads'],
    ['label' => 'Extensão mbstring',   'ok' => checkReq('mbstring'), 'info' => ''],
    ['label' => 'Extensão fileinfo',   'ok' => checkReq('fileinfo'), 'info' => 'Validação de uploads'],
    ['label' => 'Pasta /uploads/ gravável', 'ok' => dirWritable(ROOT_PATH . '/uploads'),    'info' => ''],
    ['label' => 'Pasta /config/ gravável',  'ok' => dirWritable(ROOT_PATH . '/config'),     'info' => ''],
    ['label' => 'Pasta /logs/ gravável',    'ok' => dirWritable(ROOT_PATH . '/logs'),       'info' => 'Crie e dê permissão 755 se necessário'],
];
$reqOk = array_reduce($requirements, fn($carry, $r) => $carry && $r['ok'], true);

// ──────────────── Passo atual ────────────────
$step    = max(1, min(4, (int)($_POST['step'] ?? $_GET['step'] ?? 1)));
$erros   = [];
$success = false;

// ──────────────── Processamento ────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 3) {
    // Dados do banco
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbPort = trim($_POST['db_port'] ?? '3306');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = $_POST['db_pass'] ?? '';

    // Dados do admin
    $adminNome  = trim($_POST['admin_nome'] ?? '');
    $adminEmail = strtolower(trim($_POST['admin_email'] ?? ''));
    $adminSenha = $_POST['admin_senha'] ?? '';
    $adminConf  = $_POST['admin_confirmar'] ?? '';

    // Validações
    if (empty($dbName))  $erros[] = 'Informe o nome do banco de dados.';
    if (empty($dbUser))  $erros[] = 'Informe o usuário do MySQL.';
    if (empty($adminNome))  $erros[] = 'Informe o nome do administrador.';
    if (empty($adminEmail) || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $erros[] = 'E-mail do administrador inválido.';
    if (strlen($adminSenha) < 8) $erros[] = 'Senha do administrador deve ter ao menos 8 caracteres.';
    if ($adminSenha !== $adminConf) $erros[] = 'As senhas não coincidem.';

    if (empty($erros)) {
        // Testar conexão
        try {
            $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // Criar banco
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '', $dbName) . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `" . str_replace('`', '', $dbName) . "`");

            // Executar SQL de estrutura
            $sql = file_get_contents(ROOT_PATH . '/setup.sql');
            // Remover diretivas de banco (já executadas acima)
            $sql = preg_replace('/^(CREATE DATABASE|USE)\s.*?;$/im', '', $sql);
            $sql = preg_replace('/^SET\s+AUTOCOMMIT.*?;$/im', '', $sql);
            $sql = preg_replace('/^START\s+TRANSACTION.*?;$/im', '', $sql);
            $sql = preg_replace('/^COMMIT.*?;$/im', '', $sql);

            // Executar por statement
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
                if (!empty($statement)) {
                    $pdo->exec($statement);
                }
            }

            // Criar ou atualizar usuário admin com credenciais fornecidas
            $senhaHash = password_hash($adminSenha, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmtAdmin = $pdo->prepare("
                INSERT INTO usuarios (nome, email, senha, tipo, ativo) VALUES (?, ?, ?, 'admin', 1)
                ON DUPLICATE KEY UPDATE nome = VALUES(nome), senha = VALUES(senha), tipo = 'admin', ativo = 1
            ");
            $stmtAdmin->execute([$adminNome, $adminEmail, $senhaHash]);

            // Atualizar config/database.php
            $dbPassEsc   = addslashes($dbPass);
            $dbUserEsc   = addslashes($dbUser);
            $dbNameEsc   = addslashes($dbName);
            $dbHostEsc   = addslashes($dbHost);
            $dbPortFinal = (int)$dbPort ?: 3306;

            $configContent = <<<PHP
<?php
define('DB_HOST', '{$dbHostEsc}');
define('DB_PORT', {$dbPortFinal});
define('DB_NAME', '{$dbNameEsc}');
define('DB_USER', '{$dbUserEsc}');
define('DB_PASS', '{$dbPassEsc}');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static \$pdo = null;
    if (\$pdo === null) {
        \$dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        \$options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, \$options);
        } catch (PDOException \$e) {
            error_log('DB Connection failed: ' . \$e->getMessage());
            die('Erro de conexão com o banco de dados. Tente novamente mais tarde.');
        }
    }
    return \$pdo;
}
PHP;
            file_put_contents(ROOT_PATH . '/config/database.php', $configContent);

            // Marcar como instalado (remove marker antigo se existir)
            file_put_contents(ROOT_PATH . '/config/.installed', date('Y-m-d H:i:s'));

            // Salvar dados para exibir na tela final
            $_SESSION['install_admin_email'] = $adminEmail;
            $_SESSION['install_admin_nome']  = $adminNome;

            $step    = 4;
            $success = true;

        } catch (PDOException $e) {
            $erros[] = 'Erro de conexão com o banco: ' . $e->getMessage();
            $step    = 2;
        } catch (Throwable $e) {
            $erros[] = 'Erro durante instalação: ' . $e->getMessage();
            $step    = 2;
        }
    } else {
        $step = 2;
    }
}

// Sessão simples para dados de feedback
if (session_status() === PHP_SESSION_NONE) session_start();

$adminEmailFinal = $_SESSION['install_admin_email'] ?? '';
$adminNomeFinal  = $_SESSION['install_admin_nome']  ?? '';

// POST values para repopular
$postValues = $_POST ?: [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Instalação — Sistema de Cursos</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root {
      --primary: #1B3A6B;
      --secondary: #C9A227;
      --accent: #E63946;
      --footer: #0D1F3C;
    }
    * { box-sizing: border-box; }
    body {
      font-family: Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, var(--footer) 0%, var(--primary) 60%, #2a5298 100%);
      min-height: 100vh;
      padding: 30px 16px 60px;
    }
    .install-wrap { max-width: 720px; margin: 0 auto; }

    /* Card principal */
    .install-card {
      background: #fff;
      border-radius: 18px;
      box-shadow: 0 20px 60px rgba(0,0,0,.35);
      overflow: hidden;
    }
    .install-header {
      background: var(--primary);
      padding: 28px 36px 24px;
      color: #fff;
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .install-header .logo-icon {
      width: 54px; height: 54px;
      background: var(--secondary);
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.6rem; flex-shrink: 0;
    }
    .install-header h1 { font-size: 1.3rem; font-weight: bold; margin: 0; }
    .install-header p { font-size: .82rem; opacity: .7; margin: 2px 0 0; }
    .install-body { padding: 32px 36px 36px; }

    /* Progresso */
    .progress-steps {
      display: flex; gap: 0;
      margin-bottom: 30px;
    }
    .progress-steps .ps-item {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      position: relative;
    }
    .progress-steps .ps-item:not(:last-child)::after {
      content: '';
      position: absolute;
      top: 18px;
      left: calc(50% + 18px);
      right: calc(-50% + 18px);
      height: 2px;
      background: #dee2e6;
      z-index: 0;
    }
    .progress-steps .ps-item.done::after,
    .progress-steps .ps-item.active::after { background: var(--primary); }
    .ps-circle {
      width: 38px; height: 38px;
      border-radius: 50%;
      border: 2px solid #dee2e6;
      background: #f8f9fa;
      display: flex; align-items: center; justify-content: center;
      font-weight: 700; font-size: .88rem;
      color: #aaa;
      position: relative; z-index: 1;
      transition: all .3s;
    }
    .ps-item.done .ps-circle  { background: var(--primary); border-color: var(--primary); color: #fff; }
    .ps-item.active .ps-circle { background: var(--secondary); border-color: var(--secondary); color: #fff; }
    .ps-label { font-size: .72rem; margin-top: 5px; color: #999; text-align: center; }
    .ps-item.done .ps-label, .ps-item.active .ps-label { color: var(--primary); font-weight: 700; }

    /* Formulário */
    .form-label { font-weight: 700; font-size: .87rem; color: var(--primary); }
    .form-control, .form-select {
      border: 1.5px solid #d0d6e8;
      border-radius: 8px;
      padding: 10px 14px;
      font-family: Tahoma, sans-serif;
      font-size: .9rem;
      transition: border-color .2s, box-shadow .2s;
    }
    .form-control:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(27,58,107,.12);
      outline: none;
    }
    .form-text { font-size: .78rem; color: #888; }

    /* Botão principal */
    .btn-install {
      background: var(--primary);
      color: #fff;
      border: none;
      border-radius: 10px;
      padding: 12px 28px;
      font-weight: 700;
      font-size: .95rem;
      font-family: Tahoma, sans-serif;
      transition: all .2s;
      cursor: pointer;
    }
    .btn-install:hover { background: #2a5298; transform: translateY(-1px); color: #fff; }
    .btn-install.secondary { background: var(--secondary); }
    .btn-install.secondary:hover { background: #ddb52e; }

    /* Requisitos */
    .req-item {
      display: flex; align-items: center; gap: 10px;
      padding: 8px 12px;
      border-radius: 8px;
      margin-bottom: 6px;
      font-size: .88rem;
    }
    .req-item.ok    { background: rgba(40,167,69,.08); color: #166534; }
    .req-item.fail  { background: rgba(230,57,70,.08); color: #991b1b; }
    .req-icon { font-size: 1.1rem; flex-shrink: 0; }

    /* Separador de seção */
    .section-label {
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--secondary);
      margin: 20px 0 10px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .section-label::after {
      content: '';
      flex: 1;
      height: 1px;
      background: #e0e4ef;
    }

    /* Sucesso */
    .success-icon {
      width: 80px; height: 80px;
      background: linear-gradient(135deg, #28a745, #20c997);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 2.5rem;
      color: #fff;
      margin: 0 auto 20px;
    }
    .cred-box {
      background: var(--footer);
      border-radius: 10px;
      padding: 16px 20px;
      color: #fff;
      font-size: .88rem;
      margin: 16px 0;
    }
    .cred-box code {
      background: rgba(255,255,255,.1);
      padding: 3px 10px;
      border-radius: 5px;
      font-size: .85rem;
      color: var(--secondary);
    }
    .warn-box {
      background: rgba(230,57,70,.08);
      border: 1px solid rgba(230,57,70,.2);
      border-radius: 10px;
      padding: 14px 18px;
      font-size: .85rem;
      color: #991b1b;
    }
    .alert { border-radius: 10px; font-size: .88rem; }

    @media (max-width: 576px) {
      .install-body { padding: 20px 16px 24px; }
      .install-header { padding: 20px; flex-direction: column; text-align: center; }
      .ps-label { display: none; }
    }
  </style>
</head>
<body>
<div class="install-wrap">

  <!-- Header -->
  <div class="install-card">
    <div class="install-header">
      <div class="logo-icon"><i class="bi bi-mortarboard-fill"></i></div>
      <div>
        <h1>Sistema de Cursos</h1>
        <p>Assistente de Instalação &amp; Configuração</p>
      </div>
    </div>

    <div class="install-body">

      <!-- Progresso -->
      <div class="progress-steps mb-4">
        <?php
        $steps = ['Requisitos', 'Configuração', 'Instalação', 'Concluído'];
        foreach ($steps as $i => $label):
          $n = $i + 1;
          $cls = $n < $step ? 'done' : ($n === $step ? 'active' : '');
        ?>
        <div class="ps-item <?= $cls ?>">
          <div class="ps-circle">
            <?= $n < $step ? '<i class="bi bi-check-lg"></i>' : $n ?>
          </div>
          <div class="ps-label"><?= $label ?></div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Erros -->
      <?php if (!empty($erros)): ?>
      <div class="alert alert-danger mb-4">
        <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Corrija os erros abaixo:</strong>
        <ul class="mb-0 mt-2">
          <?php foreach ($erros as $e): ?>
          <li><?= escHtml($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <!-- ══════ PASSO 1: REQUISITOS ══════ -->
      <?php if ($step === 1): ?>
      <h5 style="color:var(--primary);font-weight:bold;" class="mb-3">
        <i class="bi bi-clipboard-check me-2" style="color:var(--secondary);"></i>Verificação de Requisitos
      </h5>
      <p style="font-size:.88rem;color:#666;">Verificando se seu servidor atende aos requisitos mínimos:</p>

      <div class="mb-4">
        <?php foreach ($requirements as $req): ?>
        <div class="req-item <?= $req['ok'] ? 'ok' : 'fail' ?>">
          <i class="req-icon bi <?= $req['ok'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
          <div>
            <strong><?= escHtml($req['label']) ?></strong>
            <?php if ($req['info']): ?>
            <span style="font-size:.78rem;opacity:.7;"> — <?= escHtml($req['info']) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if (!$reqOk): ?>
      <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Alguns requisitos não foram atendidos. Corrija-os antes de continuar.
        Verifique as permissões das pastas e as extensões PHP instaladas.
      </div>
      <?php endif; ?>

      <div class="d-flex justify-content-end mt-3">
        <a href="?step=2" class="btn-install <?= !$reqOk ? 'secondary' : '' ?>">
          Próximo <i class="bi bi-arrow-right ms-1"></i>
        </a>
      </div>

      <!-- ══════ PASSO 2: CONFIGURAÇÃO ══════ -->
      <?php elseif ($step === 2 || ($step === 3 && !empty($erros))): ?>
      <h5 style="color:var(--primary);font-weight:bold;" class="mb-1">
        <i class="bi bi-database-gear me-2" style="color:var(--secondary);"></i>Configuração do Sistema
      </h5>
      <p style="font-size:.88rem;color:#666;margin-bottom:20px;">Preencha os dados de conexão com o banco de dados e crie o usuário administrador.</p>

      <form method="POST" novalidate>
        <input type="hidden" name="step" value="3">

        <div class="section-label">Banco de Dados MySQL</div>

        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label">Host do MySQL <span style="color:var(--accent)">*</span></label>
            <input type="text" class="form-control" name="db_host"
                   value="<?= escHtml($postValues['db_host'] ?? 'localhost') ?>"
                   placeholder="localhost" required>
            <div class="form-text">Geralmente <code>localhost</code> ou <code>127.0.0.1</code></div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Porta</label>
            <input type="number" class="form-control" name="db_port"
                   value="<?= escHtml($postValues['db_port'] ?? '3306') ?>"
                   placeholder="3306" min="1" max="65535">
          </div>
          <div class="col-md-12">
            <label class="form-label">Nome do Banco de Dados <span style="color:var(--accent)">*</span></label>
            <input type="text" class="form-control" name="db_name"
                   value="<?= escHtml($postValues['db_name'] ?? 'sistemadecursos') ?>"
                   placeholder="sistemadecursos" required>
            <div class="form-text">Será criado automaticamente se não existir.</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Usuário MySQL <span style="color:var(--accent)">*</span></label>
            <input type="text" class="form-control" name="db_user"
                   value="<?= escHtml($postValues['db_user'] ?? 'root') ?>"
                   placeholder="root" required autocomplete="username">
          </div>
          <div class="col-md-6">
            <label class="form-label">Senha MySQL</label>
            <div class="input-group">
              <input type="password" class="form-control" name="db_pass"
                     id="db_pass" value="" autocomplete="current-password">
              <button type="button" class="btn btn-outline-secondary" onclick="togglePass('db_pass','icon-db')" style="border-radius:0 8px 8px 0;">
                <i class="bi bi-eye" id="icon-db"></i>
              </button>
            </div>
            <div class="form-text">Deixe em branco se não houver senha.</div>
          </div>
        </div>

        <div class="section-label mt-2">Usuário Administrador</div>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nome Completo <span style="color:var(--accent)">*</span></label>
            <input type="text" class="form-control" name="admin_nome"
                   value="<?= escHtml($postValues['admin_nome'] ?? '') ?>"
                   placeholder="Seu nome completo" required maxlength="150">
          </div>
          <div class="col-md-6">
            <label class="form-label">E-mail de Login <span style="color:var(--accent)">*</span></label>
            <input type="email" class="form-control" name="admin_email"
                   value="<?= escHtml($postValues['admin_email'] ?? '') ?>"
                   placeholder="admin@dominio.com" required maxlength="150">
          </div>
          <div class="col-md-6">
            <label class="form-label">Senha <span style="color:var(--accent)">*</span></label>
            <div class="input-group">
              <input type="password" class="form-control" name="admin_senha"
                     id="admin_pass" placeholder="Mínimo 8 caracteres" required minlength="8"
                     autocomplete="new-password">
              <button type="button" class="btn btn-outline-secondary" onclick="togglePass('admin_pass','icon-adm')" style="border-radius:0 8px 8px 0;">
                <i class="bi bi-eye" id="icon-adm"></i>
              </button>
            </div>
            <div class="form-text" id="pass-strength"></div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Confirmar Senha <span style="color:var(--accent)">*</span></label>
            <div class="input-group">
              <input type="password" class="form-control" name="admin_confirmar"
                     id="admin_conf" placeholder="Repita a senha" required
                     autocomplete="new-password">
              <button type="button" class="btn btn-outline-secondary" onclick="togglePass('admin_conf','icon-cf')" style="border-radius:0 8px 8px 0;">
                <i class="bi bi-eye" id="icon-cf"></i>
              </button>
            </div>
            <div class="form-text" id="pass-match"></div>
          </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
          <a href="?step=1" class="btn btn-outline-secondary" style="border-radius:8px;font-weight:700;padding:10px 20px;">
            <i class="bi bi-arrow-left me-1"></i>Voltar
          </a>
          <button type="submit" class="btn-install">
            <i class="bi bi-gear-fill me-1"></i>Instalar Sistema
          </button>
        </div>
      </form>

      <!-- ══════ PASSO 4: CONCLUÍDO ══════ -->
      <?php elseif ($step === 4): ?>
      <div class="text-center">
        <div class="success-icon"><i class="bi bi-check-lg"></i></div>
        <h4 style="color:var(--primary);font-weight:bold;">Instalação Concluída com Sucesso!</h4>
        <p style="color:#666;font-size:.9rem;">O sistema foi configurado e está pronto para uso.</p>
      </div>

      <div class="cred-box mt-3">
        <div class="mb-2" style="color:var(--secondary);font-weight:bold;font-size:.82rem;text-transform:uppercase;letter-spacing:.5px;">
          <i class="bi bi-key-fill me-1"></i>Dados de Acesso ao Painel
        </div>
        <div class="mb-1">
          <span style="opacity:.6;">URL do Painel:</span>
          <code><?= escHtml((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'seudominio.com') . '/admin/index.php') ?></code>
        </div>
        <div class="mb-1">
          <span style="opacity:.6;">E-mail:</span>
          <code><?= escHtml($adminEmailFinal) ?></code>
        </div>
        <div>
          <span style="opacity:.6;">Nome:</span>
          <code><?= escHtml($adminNomeFinal) ?></code>
        </div>
      </div>

      <div class="warn-box mb-4">
        <strong><i class="bi bi-shield-exclamation me-1"></i>Ação obrigatória de segurança:</strong><br>
        Exclua o arquivo <code style="color:#991b1b;background:rgba(153,27,27,.1);padding:2px 8px;border-radius:4px;">install.php</code>
        do servidor imediatamente após fechar esta página. Enquanto existir, qualquer pessoa pode reinstalar o sistema e apagar seus dados.
      </div>

      <div class="p-3 mb-4" style="background:#f0f4f8;border-radius:10px;font-size:.82rem;color:#555;">
        <strong style="color:var(--primary);">Próximos passos:</strong>
        <ol class="mb-0 mt-1 ps-3">
          <li>Acesse o painel administrativo e faça login</li>
          <li>Configure o nome do site e faça upload da logo em <strong>Configurações → Geral</strong></li>
          <li>Configure as opções de pagamento em <strong>Configurações → Pagamentos</strong></li>
          <li>Crie seu primeiro curso em <strong>Cursos → Novo Curso</strong></li>
        </ol>
      </div>

      <div class="d-flex gap-3 justify-content-center flex-wrap">
        <a href="/admin/index.php" class="btn-install">
          <i class="bi bi-speedometer2 me-1"></i>Acessar o Painel
        </a>
        <a href="/index.php" class="btn-install secondary">
          <i class="bi bi-globe me-1"></i>Ver Site Público
        </a>
      </div>

      <?php endif; ?>

    </div><!-- .install-body -->
  </div><!-- .install-card -->

  <p class="text-center mt-3" style="color:rgba(255,255,255,.4);font-size:.78rem;">
    Sistema de Cursos &mdash; Instalador v1.0
  </p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Mostrar/ocultar senha
function togglePass(inputId, iconId) {
  const input = document.getElementById(inputId);
  const icon  = document.getElementById(iconId);
  if (!input || !icon) return;
  if (input.type === 'password') {
    input.type = 'text';
    icon.className = 'bi bi-eye-slash';
  } else {
    input.type = 'password';
    icon.className = 'bi bi-eye';
  }
}

// Força da senha
const passInput = document.getElementById('admin_pass');
const confInput = document.getElementById('admin_conf');

if (passInput) {
  passInput.addEventListener('input', function() {
    const v = this.value;
    const el = document.getElementById('pass-strength');
    if (!el) return;
    let score = 0;
    if (v.length >= 8) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[a-z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const levels = ['', 'Muito fraca', 'Fraca', 'Regular', 'Boa', 'Forte'];
    const colors = ['', '#E63946', '#E63946', '#ffc107', '#2a9d8f', '#28a745'];
    el.innerHTML = v.length > 0 ? `Força: <strong style="color:${colors[score]}">${levels[score]}</strong>` : '';
    checkMatch();
  });
}

if (confInput) {
  confInput.addEventListener('input', checkMatch);
}

function checkMatch() {
  const el = document.getElementById('pass-match');
  if (!el || !passInput || !confInput) return;
  if (!confInput.value) { el.textContent = ''; return; }
  if (passInput.value === confInput.value) {
    el.innerHTML = '<span style="color:#28a745"><i class="bi bi-check-circle"></i> Senhas coincidem</span>';
  } else {
    el.innerHTML = '<span style="color:#E63946"><i class="bi bi-x-circle"></i> Senhas não coincidem</span>';
  }
}
</script>
</body>
</html>
