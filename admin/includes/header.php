<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/updater.php';

requireLogin('/login.php');

$usuario  = getUsuarioLogado();
$siteName = getConfig('site_nome', 'Sistema de Cursos');
$siteLogo = urlImagem(getConfig('site_logo'), '');
$pageTitle = $pageTitle ?? 'Painel Admin';
$csrf      = gerarCSRF();

// Estatísticas rápidas para sidebar
$db = getDB();
$stmtPend = $db->query("SELECT COUNT(*) FROM inscricoes WHERE status_pagamento = 'pendente'");
$pendentes = (int)$stmtPend->fetchColumn();

// Verificação de atualização (somente admin, lê cache — sem chamada de rede)
$temAtualizacao = false;
if (isAdmin()) {
    $temAtualizacao = upd_hasUpdate();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= h($csrf) ?>">
  <title><?= h($pageTitle) ?> - <?= h($siteName) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/admin.css">
</head>
<body class="admin-body">

<!-- Overlay Mobile -->
<div class="admin-sidebar-overlay" id="sidebar-overlay"></div>

<!-- Sidebar -->
<aside class="admin-sidebar" id="admin-sidebar">
  <a class="sidebar-brand" href="<?= BASE_PATH ?>/admin/index.php">
    <?php if ($siteLogo): ?>
    <img src="<?= h($siteLogo) ?>" alt="<?= h($siteName) ?>">
    <?php else: ?>
    <div style="width:40px;height:40px;background:var(--secondary);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="bi bi-mortarboard-fill" style="color:#fff;font-size:1.2rem;"></i>
    </div>
    <?php endif; ?>
    <div>
      <span class="brand-name"><?= h($siteName) ?></span>
      <span class="brand-sub">Painel Admin</span>
    </div>
  </a>

  <nav class="sidebar-nav">
    <?php
    $current = basename($_SERVER['PHP_SELF'], '.php');
    $currentDir = basename(dirname($_SERVER['PHP_SELF']));
    function isActive(string $page, string $current): string {
      return $current === $page ? 'active' : '';
    }
    ?>

    <div class="sidebar-section-label">Principal</div>
    <a href="<?= BASE_PATH ?>/admin/index.php" class="sidebar-link <?= isActive('index', $current) ?>">
      <i class="bi bi-grid-fill"></i> Dashboard
    </a>

    <div class="sidebar-section-label">Conteúdo</div>
    <a href="<?= BASE_PATH ?>/admin/cursos.php" class="sidebar-link <?= isActive('cursos', $current) ?>">
      <i class="bi bi-mortarboard-fill"></i> Cursos
    </a>
    <a href="<?= BASE_PATH ?>/admin/cursos.php?acao=criar" class="sidebar-link <?= isActive('curso-criar', $current) ?>">
      <i class="bi bi-plus-circle"></i> Novo Curso
    </a>

    <div class="sidebar-section-label">Gestão</div>
    <a href="<?= BASE_PATH ?>/admin/inscricoes.php" class="sidebar-link <?= isActive('inscricoes', $current) ?>">
      <i class="bi bi-people-fill"></i> Inscrições
      <?php if ($pendentes > 0): ?>
      <span class="badge bg-warning text-dark"><?= $pendentes ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= BASE_PATH ?>/admin/pagamentos.php" class="sidebar-link <?= isActive('pagamentos', $current) ?>">
      <i class="bi bi-cash-coin"></i> Pagamentos
    </a>
    <a href="<?= BASE_PATH ?>/admin/certificados.php" class="sidebar-link <?= isActive('certificados', $current) ?>">
      <i class="bi bi-patch-check-fill"></i> Certificados
    </a>

    <?php if (isAdmin()): ?>
    <div class="sidebar-section-label">Sistema</div>
    <a href="<?= BASE_PATH ?>/admin/usuarios.php" class="sidebar-link <?= isActive('usuarios', $current) ?>">
      <i class="bi bi-person-gear"></i> Usuários
    </a>
    <a href="<?= BASE_PATH ?>/admin/configuracoes.php" class="sidebar-link <?= isActive('configuracoes', $current) ?>">
      <i class="bi bi-gear-fill"></i> Configurações
    </a>
    <a href="<?= BASE_PATH ?>/admin/atualizacoes.php" class="sidebar-link <?= isActive('atualizacoes', $current) ?>" style="position:relative;">
      <i class="bi bi-cloud-arrow-up-fill"></i> Atualizações
      <?php if ($temAtualizacao): ?>
      <span class="badge bg-danger ms-auto" style="animation:pulse-badge 1.5s infinite;">!</span>
      <?php endif; ?>
    </a>
    <?php endif; ?>

    <div class="sidebar-section-label">Acesso Rápido</div>
    <a href="<?= BASE_PATH ?>/index.php" target="_blank" class="sidebar-link">
      <i class="bi bi-box-arrow-up-right"></i> Ver Site Público
    </a>
  </nav>

  <div class="sidebar-user">
    <div class="user-avatar"><?= mb_substr($usuario['nome'], 0, 1) ?></div>
    <div class="user-info">
      <div class="name"><?= h($usuario['nome']) ?></div>
      <div class="role"><?= $usuario['tipo'] === 'admin' ? 'Administrador' : 'Usuário' ?></div>
    </div>
    <form method="POST" action="<?= BASE_PATH ?>/logout.php" style="display:contents;">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <button type="submit" class="logout-btn" title="Sair" style="background:none;border:none;padding:0;cursor:pointer;">
        <i class="bi bi-box-arrow-right"></i>
      </button>
    </form>
  </div>
</aside>

<!-- Topbar -->
<header class="admin-topbar">
  <button class="topbar-menu-toggle" id="sidebar-toggle">
    <i class="bi bi-list"></i>
  </button>
  <div class="topbar-title"><?= h($pageTitle) ?></div>
  <div class="topbar-actions">
    <?php if ($temAtualizacao): ?>
    <a href="<?= BASE_PATH ?>/admin/atualizacoes.php" class="topbar-btn" style="color:var(--secondary);font-weight:700;">
      <i class="bi bi-cloud-arrow-up-fill"></i>
      <span class="d-none d-sm-inline">Atualização disponível</span>
    </a>
    <?php endif; ?>
    <?php if ($pendentes > 0): ?>
    <a href="<?= BASE_PATH ?>/admin/inscricoes.php?status=pendente" class="topbar-btn" style="color:var(--accent);">
      <i class="bi bi-bell-fill"></i>
      <span class="d-none d-sm-inline"><?= $pendentes ?> pendente(s)</span>
    </a>
    <?php endif; ?>
    <a href="<?= BASE_PATH ?>/index.php" target="_blank" class="topbar-btn">
      <i class="bi bi-box-arrow-up-right"></i>
      <span class="d-none d-md-inline">Ver Site</span>
    </a>
    <form method="POST" action="<?= BASE_PATH ?>/logout.php" style="display:contents;"
          onsubmit="return confirm('Deseja sair do sistema?')">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <button type="submit" class="topbar-btn" style="background:none;border:none;cursor:pointer;">
        <i class="bi bi-box-arrow-right"></i>
        <span class="d-none d-sm-inline">Sair</span>
      </button>
    </form>
  </div>
</header>

<!-- Flash Messages -->
<?php $flash = getFlash(); if ($flash): ?>
<div style="position:fixed;top:74px;right:16px;z-index:9998;min-width:280px;max-width:400px;">
  <div class="alert alert-<?= h($flash['tipo']) ?> alert-dismissible shadow" role="alert">
    <i class="bi <?= $flash['tipo'] === 'success' ? 'bi-check-circle' : ($flash['tipo'] === 'danger' ? 'bi-x-circle' : 'bi-info-circle') ?> me-2"></i>
    <?= h($flash['mensagem']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
</div>
<?php endif; ?>

<?php if (isAdmin() && !$temAtualizacao): ?>
<script>
// Verificação assíncrona de atualização (não bloqueia a página)
setTimeout(function() {
  fetch('<?= BASE_PATH ?>/admin/ajax/update_check.php', {
    credentials: 'same-origin',
    headers: {'X-Requested-With': 'XMLHttpRequest'}
  })
  .then(function(r) { return r.ok ? r.json() : null; })
  .then(function(data) {
    if (!data || !data.update_available) return;
    var t = document.createElement('div');
    t.id = 'upd-toast';
    t.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;background:var(--secondary,#c9a227);color:#fff;padding:13px 18px;border-radius:10px;font-size:.87rem;font-weight:700;box-shadow:0 4px 24px rgba(0,0,0,.18);display:flex;align-items:center;gap:10px;animation:upd-slide-in .3s ease;';
    t.innerHTML = '<i class="bi bi-cloud-arrow-up-fill"></i>'
      + '<span>Nova atualização disponível!</span>'
      + '<a href="<?= BASE_PATH ?>/admin/atualizacoes.php" style="color:#fff;text-decoration:underline;white-space:nowrap;">Ver agora</a>'
      + '<button onclick="this.parentNode.remove()" style="background:none;border:none;color:#fff;cursor:pointer;padding:0 0 0 4px;font-size:1rem;line-height:1;">✕</button>';
    document.body.appendChild(t);
    setTimeout(function() { var el = document.getElementById('upd-toast'); if (el) el.remove(); }, 10000);
  })
  .catch(function() {});
}, 4000);
</script>
<style>@keyframes upd-slide-in{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}</style>
<?php endif; ?>

<!-- Main -->
<main class="admin-main">
