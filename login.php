<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

initSession();

// Já logado - redirecionar
if (isLoggedIn()) {
    redirect('/admin/index.php');
}

$erro   = '';
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarCSRF($_POST['csrf_token'] ?? '')) {
        $erro = 'Token de segurança inválido. Recarregue a página.';
    } else {
        $email  = sanitize($_POST['email'] ?? '');
        $senha  = $_POST['senha'] ?? '';

        if (empty($email) || empty($senha)) {
            $erro = 'Preencha e-mail e senha.';
        } else {
            $resultado = login($email, $senha);
            if ($resultado['sucesso']) {
                redirect('/admin/index.php');
            } else {
                $erro = $resultado['erro'];
            }
        }
    }
}

$siteName = getConfig('site_nome', 'Sistema de Cursos');
$siteLogo = urlImagem(getConfig('site_logo'), '');
$csrf     = gerarCSRF();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - <?= h($siteName) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root { --primary: #1B3A6B; --secondary: #C9A227; --accent: #E63946; --footer-bg: #0D1F3C; }
    * { box-sizing: border-box; }
    body {
      font-family: Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #0D1F3C 0%, #1B3A6B 50%, #2a5298 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .login-wrapper { width: 100%; max-width: 420px; }
    .login-card {
      background: #fff;
      border-radius: 16px;
      padding: 40px 36px;
      box-shadow: 0 16px 48px rgba(0,0,0,.3);
    }
    .login-logo {
      text-align: center;
      margin-bottom: 28px;
    }
    .login-logo img { max-height: 70px; max-width: 200px; object-fit: contain; }
    .login-logo .logo-icon {
      width: 70px;
      height: 70px;
      background: var(--primary);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 10px;
      font-size: 2rem;
      color: #fff;
    }
    .login-logo h1 { font-size: 1.3rem; font-weight: bold; color: var(--primary); margin: 6px 0 2px; }
    .login-logo p { font-size: .82rem; color: #888; }
    .form-label { font-weight: 700; font-size: .88rem; color: var(--primary); }
    .form-control {
      border: 1.5px solid #d0d6e8;
      border-radius: 8px;
      padding: 11px 14px;
      font-family: Tahoma, sans-serif;
      font-size: .92rem;
      transition: border-color .2s, box-shadow .2s;
    }
    .form-control:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(27,58,107,.12);
      outline: none;
    }
    .input-group-text {
      border: 1.5px solid #d0d6e8;
      border-right: none;
      background: #f5f6fa;
      color: #888;
    }
    .input-group .form-control { border-left: none; }
    .btn-login {
      background: var(--primary);
      border: none;
      color: #fff;
      font-weight: 700;
      font-size: 1rem;
      padding: 12px;
      border-radius: 8px;
      width: 100%;
      font-family: Tahoma, sans-serif;
      transition: all .2s;
      cursor: pointer;
    }
    .btn-login:hover { background: #2a5298; transform: translateY(-1px); }
    .back-link {
      display: block;
      text-align: center;
      margin-top: 16px;
      color: rgba(255,255,255,.7);
      font-size: .85rem;
      text-decoration: none;
    }
    .back-link:hover { color: var(--secondary); }
    .alert { border-radius: 8px; font-size: .88rem; }
    .pass-toggle { cursor: pointer; border: 1.5px solid #d0d6e8; border-left: none; background: #f5f6fa; }
  </style>
</head>
<body>
  <div class="login-wrapper">
    <div class="login-card">
      <div class="login-logo">
        <?php if ($siteLogo): ?>
        <img src="<?= h($siteLogo) ?>" alt="<?= h($siteName) ?>">
        <?php else: ?>
        <div class="logo-icon"><i class="bi bi-mortarboard-fill"></i></div>
        <?php endif; ?>
        <h1><?= h($siteName) ?></h1>
        <p>Área Restrita - Administração</p>
      </div>

      <?php if ($erro): ?>
      <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
        <i class="bi bi-exclamation-circle-fill"></i>
        <?= h($erro) ?>
      </div>
      <?php endif; ?>

      <form method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

        <div class="mb-3">
          <label class="form-label" for="email">E-mail</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" class="form-control" id="email" name="email"
                   value="<?= h($email) ?>" required autocomplete="username"
                   placeholder="seu@email.com">
          </div>
        </div>

        <div class="mb-4">
          <label class="form-label" for="senha">Senha</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" class="form-control" id="senha" name="senha"
                   required autocomplete="current-password" placeholder="••••••••">
            <button type="button" class="btn pass-toggle"
                    onclick="toggleSenha()" title="Mostrar/ocultar senha">
              <i class="bi bi-eye" id="pass-icon"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-login">
          <i class="bi bi-box-arrow-in-right me-2"></i>Entrar
        </button>
      </form>
    </div>

    <a href="/index.php" class="back-link">
      <i class="bi bi-arrow-left me-1"></i>Voltar ao site
    </a>
  </div>

  <script>
  function toggleSenha() {
    const input = document.getElementById('senha');
    const icon  = document.getElementById('pass-icon');
    if (input.type === 'password') {
      input.type = 'text';
      icon.className = 'bi bi-eye-slash';
    } else {
      input.type = 'password';
      icon.className = 'bi bi-eye';
    }
  }
  </script>
</body>
</html>
