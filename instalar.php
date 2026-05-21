<?php
/**
 * Script de Instalação do Sistema de Cursos
 * Acesse: /instalar.php
 * IMPORTANTE: Delete este arquivo após a instalação!
 */

define('ROOT_PATH', __DIR__);
$step = (int)($_POST['step'] ?? $_GET['step'] ?? 1);
$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    $dbHost  = trim($_POST['db_host'] ?? 'localhost');
    $dbName  = trim($_POST['db_name'] ?? 'sistemadecursos');
    $dbUser  = trim($_POST['db_user'] ?? 'root');
    $dbPass  = $_POST['db_pass'] ?? '';

    // Testar conexão
    try {
        $pdo = new PDO("mysql:host=$dbHost;charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        // Criar banco se não existe
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbName`");

        // Executar SQL
        $sql = file_get_contents(__DIR__ . '/setup.sql');
        // Remover linhas de CREATE/USE do setup.sql pois já criamos
        $sql = preg_replace('/^CREATE DATABASE.*?;$/m', '', $sql);
        $sql = preg_replace('/^USE.*?;$/m', '', $sql);
        $pdo->exec($sql);

        // Atualizar config/database.php
        $configContent = "<?php
define('DB_HOST', '" . addslashes($dbHost) . "');
define('DB_NAME', '" . addslashes($dbName) . "');
define('DB_USER', '" . addslashes($dbUser) . "');
define('DB_PASS', '" . addslashes($dbPass) . "');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static \$pdo = null;
    if (\$pdo === null) {
        \$dsn = \"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=\" . DB_CHARSET;
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
";
        file_put_contents(__DIR__ . '/config/database.php', $configContent);

        $step     = 3;
        $mensagem = 'Banco de dados configurado com sucesso!';
    } catch (PDOException $e) {
        $erro = 'Erro de conexão: ' . $e->getMessage();
        $step = 1;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Instalar - Sistema de Cursos</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <style>
    body { font-family: Tahoma, sans-serif; background: linear-gradient(135deg, #0D1F3C, #1B3A6B); min-height: 100vh; display: flex; align-items: center; }
    .install-card { background: #fff; border-radius: 16px; padding: 40px; box-shadow: 0 16px 48px rgba(0,0,0,.3); max-width: 550px; width: 100%; }
    h1 { color: #1B3A6B; font-size: 1.5rem; font-weight: bold; }
    .btn-install { background: #1B3A6B; color: #fff; border: none; border-radius: 8px; padding: 12px 24px; font-weight: 700; font-family: Tahoma; }
    .step-badge { background: #C9A227; color: #fff; width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; margin-right: 8px; }
    code { background: #f0f2f8; padding: 2px 8px; border-radius: 4px; font-size: .85rem; }
  </style>
</head>
<body>
  <div class="container py-4">
    <div class="install-card mx-auto">
      <div class="text-center mb-4">
        <div style="font-size:3rem;">🎓</div>
        <h1>Sistema de Cursos</h1>
        <p style="color:#888;font-size:.9rem;">Assistente de Instalação</p>
      </div>

      <?php if ($erro): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
      <?php endif; ?>
      <?php if ($mensagem): ?>
      <div class="alert alert-success"><?= htmlspecialchars($mensagem) ?></div>
      <?php endif; ?>

      <?php if ($step === 1): ?>
      <h5><span class="step-badge">1</span>Configuração do Banco de Dados</h5>
      <p style="font-size:.88rem;color:#666;">Informe os dados de conexão com o MySQL:</p>
      <form method="POST">
        <input type="hidden" name="step" value="2">
        <div class="mb-3">
          <label class="form-label fw-bold" style="font-size:.88rem;">Host do MySQL</label>
          <input type="text" class="form-control" name="db_host" value="localhost" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold" style="font-size:.88rem;">Nome do Banco</label>
          <input type="text" class="form-control" name="db_name" value="sistemadecursos" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-bold" style="font-size:.88rem;">Usuário MySQL</label>
          <input type="text" class="form-control" name="db_user" value="root" required>
        </div>
        <div class="mb-4">
          <label class="form-label fw-bold" style="font-size:.88rem;">Senha MySQL</label>
          <input type="password" class="form-control" name="db_pass">
        </div>
        <button type="submit" class="btn-install w-100">Instalar Sistema →</button>
      </form>

      <?php elseif ($step === 3): ?>
      <div class="text-center">
        <div style="font-size:3rem;color:#28a745;">✅</div>
        <h5 class="mt-2" style="color:#1B3A6B;">Instalação Concluída!</h5>
        <p style="font-size:.9rem;color:#555;">O sistema foi configurado com sucesso.</p>
        <div class="p-3 mb-3" style="background:#f0f2f8;border-radius:10px;text-align:left;font-size:.85rem;">
          <strong>Credenciais padrão de acesso:</strong><br>
          E-mail: <code>admin@sistema.com</code><br>
          Senha: <code>Admin@123</code><br><br>
          <strong style="color:#E63946;">⚠️ Altere a senha imediatamente após o primeiro acesso!</strong>
        </div>
        <div class="alert alert-warning" style="font-size:.85rem;">
          <strong>Importante:</strong> Delete o arquivo <code>instalar.php</code> do servidor após este passo por razões de segurança!
        </div>
        <a href="/admin/index.php" class="btn-install d-inline-block text-decoration-none">Acessar o Painel →</a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
