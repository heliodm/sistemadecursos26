<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

initSession();

if (isLoggedIn()) {
    redirect('/admin/index.php');
}

$erro  = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarCSRF($_POST['csrf_token'] ?? '')) {
        $erro = 'Token de segurança inválido. Recarregue a página.';
    } else {
        $email = sanitize($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';

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
  <title>Login — <?= h($siteName) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root {
      --primary:   #1B3A6B;
      --primary-d: #122850;
      --secondary: #C9A227;
      --accent:    #E63946;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: Tahoma, Geneva, Verdana, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px 16px;
      background: #0d1f3c;
      overflow: hidden;
    }

    /* ── decorative background ── */
    .bg-canvas {
      position: fixed;
      inset: 0;
      background:
        radial-gradient(ellipse 80% 60% at 20% 10%, rgba(42,82,152,.55) 0%, transparent 70%),
        radial-gradient(ellipse 60% 50% at 80% 90%, rgba(201,162,39,.18) 0%, transparent 65%),
        linear-gradient(160deg, #0d1f3c 0%, #1b3a6b 55%, #0d1f3c 100%);
      z-index: 0;
    }
    .bg-canvas::after {
      content: '';
      position: absolute;
      inset: 0;
      background-image:
        radial-gradient(circle, rgba(255,255,255,.04) 1px, transparent 1px);
      background-size: 32px 32px;
    }

    /* ── wrapper ── */
    .login-wrapper {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 420px;
      animation: fadeUp .45s cubic-bezier(.22,.61,.36,1) both;
    }
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(28px); }
      to   { opacity: 1; transform: translateY(0);    }
    }

    /* ── card ── */
    .login-card {
      background: #fff;
      border-radius: 18px;
      overflow: hidden;
      box-shadow: 0 24px 64px rgba(0,0,0,.45), 0 2px 8px rgba(0,0,0,.2);
    }
    .card-accent {
      height: 5px;
      background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
    }
    .card-body { padding: 36px 36px 32px; }

    /* ── logo area ── */
    .login-logo { text-align: center; margin-bottom: 28px; }
    .logo-icon {
      width: 68px; height: 68px;
      background: var(--primary);
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      color: #fff;
      box-shadow: 0 4px 16px rgba(27,58,107,.35);
      margin-bottom: 12px;
    }
    .login-logo img { max-height: 68px; max-width: 190px; object-fit: contain; display: block; margin: 0 auto 10px; }
    .login-logo h1 {
      font-size: 1.2rem;
      font-weight: bold;
      color: var(--primary);
      margin-bottom: 3px;
      line-height: 1.3;
    }
    .login-logo .subtitle {
      font-size: .78rem;
      color: #999;
      letter-spacing: .5px;
      text-transform: uppercase;
    }
    .logo-divider {
      width: 40px; height: 3px;
      background: var(--secondary);
      border-radius: 2px;
      margin: 10px auto 0;
    }

    /* ── alert ── */
    .alert-error {
      display: flex;
      align-items: center;
      gap: 9px;
      background: #fff5f5;
      border: 1px solid #fcc;
      border-left: 4px solid var(--accent);
      border-radius: 8px;
      padding: 11px 14px;
      font-size: .87rem;
      color: #c0392b;
      margin-bottom: 20px;
      animation: shake .45s cubic-bezier(.36,.07,.19,.97) both;
    }
    @keyframes shake {
      10%, 90% { transform: translateX(-2px); }
      20%, 80% { transform: translateX(3px);  }
      30%, 50%, 70% { transform: translateX(-4px); }
      40%, 60% { transform: translateX(4px);  }
    }

    /* ── form ── */
    .form-group { margin-bottom: 18px; }
    .form-label {
      display: block;
      font-size: .82rem;
      font-weight: 700;
      color: var(--primary);
      margin-bottom: 6px;
      letter-spacing: .2px;
    }
    .input-wrap {
      position: relative;
      display: flex;
      align-items: center;
    }
    .input-icon {
      position: absolute;
      left: 13px;
      color: #aab;
      font-size: 1rem;
      pointer-events: none;
      transition: color .2s;
    }
    .input-wrap:focus-within .input-icon { color: var(--primary); }
    .form-control {
      width: 100%;
      border: 1.5px solid #d4d9e8;
      border-radius: 9px;
      padding: 11px 14px 11px 40px;
      font-family: Tahoma, sans-serif;
      font-size: .92rem;
      color: #222;
      background: #fafbff;
      transition: border-color .2s, box-shadow .2s, background .2s;
      outline: none;
    }
    .form-control:focus {
      border-color: var(--primary);
      background: #fff;
      box-shadow: 0 0 0 3px rgba(27,58,107,.1);
    }
    .form-control.has-toggle { padding-right: 42px; }
    .pass-toggle {
      position: absolute;
      right: 12px;
      background: none;
      border: none;
      color: #aab;
      cursor: pointer;
      font-size: 1rem;
      padding: 0;
      line-height: 1;
      transition: color .2s;
    }
    .pass-toggle:hover { color: var(--primary); }

    /* ── submit ── */
    .btn-login {
      width: 100%;
      border: none;
      background: var(--primary);
      color: #fff;
      font-family: Tahoma, sans-serif;
      font-size: .97rem;
      font-weight: 700;
      padding: 13px;
      border-radius: 9px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-top: 8px;
      transition: background .2s, transform .15s, box-shadow .2s;
      box-shadow: 0 4px 14px rgba(27,58,107,.25);
    }
    .btn-login:hover {
      background: var(--primary-d);
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(27,58,107,.35);
    }
    .btn-login:active { transform: translateY(0); }
    .btn-login:disabled { opacity: .7; cursor: not-allowed; transform: none; }
    .spinner {
      display: none;
      width: 16px; height: 16px;
      border: 2px solid rgba(255,255,255,.4);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin .6s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── back link ── */
    .back-link {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      margin-top: 18px;
      color: rgba(255,255,255,.6);
      font-size: .83rem;
      text-decoration: none;
      transition: color .2s;
    }
    .back-link:hover { color: var(--secondary); }

    /* ── secure badge ── */
    .secure-badge {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
      font-size: .73rem;
      color: #bcc;
      margin-top: 14px;
    }

    @media (max-width: 480px) {
      .card-body { padding: 28px 22px 24px; }
    }
  </style>
</head>
<body>
  <div class="bg-canvas"></div>

  <div class="login-wrapper">
    <div class="login-card">
      <div class="card-accent"></div>
      <div class="card-body">

        <div class="login-logo">
          <?php if ($siteLogo): ?>
          <img src="<?= h($siteLogo) ?>" alt="<?= h($siteName) ?>">
          <?php else: ?>
          <div class="logo-icon"><i class="bi bi-mortarboard-fill"></i></div>
          <?php endif; ?>
          <h1><?= h($siteName) ?></h1>
          <div class="subtitle">Área Administrativa</div>
          <div class="logo-divider"></div>
        </div>

        <?php if ($erro): ?>
        <div class="alert-error" role="alert">
          <i class="bi bi-exclamation-circle-fill" style="flex-shrink:0;"></i>
          <?= h($erro) ?>
        </div>
        <?php endif; ?>

        <form method="POST" novalidate id="loginForm">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

          <div class="form-group">
            <label class="form-label" for="email">E-mail</label>
            <div class="input-wrap">
              <i class="bi bi-envelope input-icon"></i>
              <input type="email" class="form-control" id="email" name="email"
                     value="<?= h($email) ?>" required autocomplete="username"
                     placeholder="seu@email.com" autofocus>
            </div>
          </div>

          <div class="form-group" style="margin-bottom:24px;">
            <label class="form-label" for="senha">Senha</label>
            <div class="input-wrap">
              <i class="bi bi-lock input-icon"></i>
              <input type="password" class="form-control has-toggle" id="senha" name="senha"
                     required autocomplete="current-password" placeholder="••••••••">
              <button type="button" class="pass-toggle" id="passToggle"
                      title="Mostrar/ocultar senha" aria-label="Mostrar ou ocultar senha">
                <i class="bi bi-eye" id="passIcon"></i>
              </button>
            </div>
          </div>

          <button type="submit" class="btn-login" id="btnLogin">
            <div class="spinner" id="btnSpinner"></div>
            <i class="bi bi-box-arrow-in-right" id="btnIcon"></i>
            <span id="btnText">Entrar</span>
          </button>
        </form>

      </div>
    </div>

    <a href="<?= BASE_PATH ?>/index.php" class="back-link">
      <i class="bi bi-arrow-left"></i> Voltar ao site
    </a>

    <div class="secure-badge">
      <i class="bi bi-shield-lock-fill"></i> Acesso protegido
    </div>
  </div>

  <script>
  // Toggle senha
  document.getElementById('passToggle').addEventListener('click', function() {
    const input = document.getElementById('senha');
    const icon  = document.getElementById('passIcon');
    const show  = input.type === 'password';
    input.type        = show ? 'text' : 'password';
    icon.className    = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    this.title        = show ? 'Ocultar senha' : 'Mostrar senha';
    input.focus();
  });

  // Loading state ao submeter
  document.getElementById('loginForm').addEventListener('submit', function() {
    const btn     = document.getElementById('btnLogin');
    const spinner = document.getElementById('btnSpinner');
    const icon    = document.getElementById('btnIcon');
    const text    = document.getElementById('btnText');
    btn.disabled       = true;
    spinner.style.display = 'block';
    icon.style.display    = 'none';
    text.textContent      = 'Entrando…';
  });
  </script>
</body>
</html>
