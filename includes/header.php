<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
initSession();

$siteName     = getConfig('site_nome', 'Sistema de Cursos');
$siteLogo     = urlImagem(getConfig('site_logo'), '');
$pageTitle    = $pageTitle ?? $siteName;
$pageDesc     = $pageDesc ?? getConfig('site_descricao', '');

$menu1Label   = getConfig('menu_item_1_label', 'Início');
$menu1Url     = getConfig('menu_item_1_url', '/index.php');
$menu1Ativo   = getConfig('menu_item_1_ativo', '1');
$menu2Label   = getConfig('menu_item_2_label', 'Cursos');
$menu2Url     = getConfig('menu_item_2_url', '/index.php#cursos');
$menu2Ativo   = getConfig('menu_item_2_ativo', '1');
$menu3Label   = getConfig('menu_item_3_label', 'Certificados');
$menu3Url     = getConfig('menu_item_3_url', '/certificados.php');
$menu3Ativo   = getConfig('menu_item_3_ativo', '1');

$currentPath  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$csrf         = gerarCSRF();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= h($pageDesc) ?>">
  <meta name="csrf-token" content="<?= h($csrf) ?>">
  <title><?= h($pageTitle) ?></title>
  <!-- Bootstrap 5.3 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- Google Fonts - Tahoma não está no Google, mas carregamos como fallback -->
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
</head>
<body>

<!-- Spinner Overlay -->
<div class="spinner-overlay" id="spinner-overlay">
  <div class="text-center">
    <div class="spinner-border text-primary" role="status" style="width:3rem;height:3rem;"></div>
    <div class="mt-2 fw-bold text-primary">Aguarde...</div>
  </div>
</div>

<?php $flash = getFlash(); if ($flash): ?>
<div class="position-fixed" style="top:80px;right:16px;z-index:9999;min-width:280px;">
  <div class="alert alert-<?= h($flash['tipo']) ?> alert-dismissible shadow fade show">
    <?= h($flash['mensagem']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
</div>
<?php endif; ?>

<header class="site-header">
  <nav class="navbar navbar-expand-lg" style="padding:0 0;">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center gap-2" href="<?= BASE_PATH ?>/index.php">
        <?php if ($siteLogo): ?>
          <img src="<?= h($siteLogo) ?>" alt="<?= h($siteName) ?>">
        <?php else: ?>
          <span><i class="bi bi-mortarboard-fill" style="color:var(--secondary);font-size:1.8rem;"></i></span>
        <?php endif; ?>
        <span><?= h($siteName) ?></span>
      </a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav ms-auto">
          <?php
          // Prefixa BASE_PATH em URLs relativas do menu (não afeta URLs externas http/https)
          $mu = function(string $u): string { return preg_match('#^https?://#', $u) ? $u : BASE_PATH . $u; };
          ?>
          <?php if ($menu1Ativo == '1'): ?>
          <li class="nav-item">
            <a class="nav-link <?= ($currentPath === parse_url($menu1Url, PHP_URL_PATH)) ? 'active' : '' ?>"
               href="<?= h($mu($menu1Url)) ?>"><?= h($menu1Label) ?></a>
          </li>
          <?php endif; ?>
          <?php if ($menu2Ativo == '1'): ?>
          <li class="nav-item">
            <a class="nav-link" href="<?= h($mu($menu2Url)) ?>"><?= h($menu2Label) ?></a>
          </li>
          <?php endif; ?>
          <?php if ($menu3Ativo == '1'): ?>
          <li class="nav-item">
            <a class="nav-link <?= (strpos($currentPath, 'certificado') !== false) ? 'active' : '' ?>"
               href="<?= h($mu($menu3Url)) ?>"><?= h($menu3Label) ?></a>
          </li>
          <?php endif; ?>
          <li class="nav-item ms-2">
            <a class="nav-link" href="<?= BASE_PATH ?>/login.php" style="border:1px solid rgba(255,255,255,.3);border-radius:6px;">
              <i class="bi bi-lock-fill"></i> Admin
            </a>
          </li>
        </ul>
      </div>
    </div>
  </nav>
</header>
