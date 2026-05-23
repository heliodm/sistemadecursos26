<?php
$pageTitle = 'Atualizações do Sistema';
require_once __DIR__ . '/includes/header.php';
requireAdmin();

require_once ROOT_PATH . '/includes/updater.php';

$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarCSRF($_POST['csrf_token'] ?? '')) {
        redirect('/admin/atualizacoes.php', 'Token inválido.', 'danger');
    }

    $acao = sanitize($_POST['acao'] ?? '');

    if ($acao === 'verificar') {
        upd_checkGithub(true);
        redirect('/admin/atualizacoes.php', 'Verificação concluída.', 'success');
    }

    if ($acao === 'atualizar') {
        $resultado = upd_executeUpdate();
        if ($resultado['sucesso']) {
            redirect('/admin/atualizacoes.php', 'Sistema atualizado com sucesso! Commit: ' . h($resultado['commit']), 'success');
        }
        // Mantém $resultado para exibir erro com output
    }

    if ($acao === 'salvar_config') {
        $token  = trim($_POST['github_token'] ?? '');
        $branch = sanitize(trim($_POST['branch'] ?? ''));

        $db = getDB();
        // Salvar token (pode ser vazio para repos públicos)
        $db->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('github_token', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)")
           ->execute([$token]);

        if ($branch) {
            $info = upd_readVersion();
            $info['branch'] = $branch;
            upd_writeVersion($info);
        }

        redirect('/admin/atualizacoes.php', 'Configurações salvas.', 'success');
    }
}

// Carrega info (cache, sem chamada de rede)
$info  = upd_readVersion();
$token = getConfig('github_token', '');

$canGit    = upd_canGit();
$isGitRepo = upd_isGitRepo();
$canUpdate = $canGit && $isGitRepo;
?>

<div class="row g-4">

  <!-- Painel principal -->
  <div class="col-lg-8">

    <!-- Status da versão -->
    <div class="admin-card mb-4">
      <div class="admin-card-header">
        <h5><i class="bi bi-cloud-arrow-up-fill"></i> Status da Versão</h5>
        <form method="POST" style="display:inline;">
          <input type="hidden" name="csrf_token" value="<?= gerarCSRF() ?>">
          <input type="hidden" name="acao" value="verificar">
          <button type="submit" class="btn-action view">
            <i class="bi bi-arrow-repeat"></i> Verificar Agora
          </button>
        </form>
      </div>
      <div class="admin-card-body">

        <?php if ($resultado && !$resultado['sucesso']): ?>
        <div class="alert alert-danger mb-4">
          <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Erro na atualização:</strong>
          <pre style="margin:8px 0 0;font-size:.8rem;white-space:pre-wrap;background:rgba(0,0,0,.05);padding:10px;border-radius:6px;"><?= h($resultado['output']) ?></pre>
        </div>
        <?php endif; ?>

        <!-- Versão atual vs latest -->
        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <div style="background:rgba(27,58,107,.05);border-radius:10px;padding:18px;">
              <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#888;margin-bottom:6px;">
                <i class="bi bi-hdd me-1"></i>Versão Instalada
              </div>
              <code style="font-size:1.1rem;font-weight:700;color:var(--primary);"><?= h($info['commit']) ?></code>
              <?php if (!empty($info['updated_at'])): ?>
              <div style="font-size:.78rem;color:#888;margin-top:6px;">
                <i class="bi bi-clock me-1"></i>Instalado em <?= upd_formatDate($info['updated_at']) ?>
              </div>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-md-6">
            <div style="background:<?= !empty($info['update_available']) ? 'rgba(201,162,39,.08)' : 'rgba(40,167,69,.06)' ?>;border-radius:10px;padding:18px;border:1px solid <?= !empty($info['update_available']) ? 'rgba(201,162,39,.25)' : 'rgba(40,167,69,.2)' ?>;">
              <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#888;margin-bottom:6px;">
                <i class="bi bi-github me-1"></i>Versão no GitHub
              </div>
              <?php if (!empty($info['latest_commit'])): ?>
                <code style="font-size:1.1rem;font-weight:700;color:<?= !empty($info['update_available']) ? 'var(--secondary)' : '#28a745' ?>;"><?= h($info['latest_commit']) ?></code>
                <div style="font-size:.78rem;color:#888;margin-top:6px;">
                  <?php if (!empty($info['latest_date'])): ?>
                  <i class="bi bi-clock me-1"></i><?= upd_formatDate($info['latest_date']) ?>
                  <?php endif; ?>
                </div>
              <?php elseif (!empty($info['api_error'])): ?>
                <span style="font-size:.85rem;color:var(--accent);"><i class="bi bi-exclamation-circle me-1"></i><?= h($info['api_error']) ?></span>
              <?php else: ?>
                <span style="font-size:.85rem;color:#888;">Não verificado ainda</span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Status geral + botão de atualizar -->
        <?php if (!empty($info['update_available'])): ?>
        <div style="background:linear-gradient(135deg,rgba(201,162,39,.12),rgba(201,162,39,.06));border:1px solid rgba(201,162,39,.3);border-radius:12px;padding:20px;" class="mb-3">
          <div class="d-flex align-items-start gap-3 flex-wrap">
            <div style="flex:1;">
              <div style="font-weight:700;color:var(--secondary);font-size:1rem;margin-bottom:4px;">
                <i class="bi bi-arrow-up-circle-fill me-1"></i>Nova atualização disponível!
              </div>
              <?php if (!empty($info['latest_message'])): ?>
              <div style="font-size:.85rem;color:#555;margin-bottom:8px;">
                <i class="bi bi-chat-left-text me-1 text-muted"></i>
                <?= h(explode("\n", $info['latest_message'])[0]) ?>
              </div>
              <?php endif; ?>
              <?php if (!empty($info['latest_author'])): ?>
              <div style="font-size:.78rem;color:#888;">
                <i class="bi bi-person me-1"></i><?= h($info['latest_author']) ?>
              </div>
              <?php endif; ?>
            </div>
            <?php if ($canUpdate): ?>
            <form method="POST" onsubmit="return confirmarUpdate()">
              <input type="hidden" name="csrf_token" value="<?= gerarCSRF() ?>">
              <input type="hidden" name="acao" value="atualizar">
              <button type="submit" class="btn" id="btn-update"
                      style="background:var(--secondary);color:#fff;font-weight:700;border-radius:10px;padding:12px 24px;font-size:.95rem;border:none;">
                <i class="bi bi-cloud-download-fill me-2"></i>Atualizar Agora
              </button>
            </form>
            <?php else: ?>
            <div class="alert alert-warning mb-0 py-2 px-3" style="font-size:.82rem;">
              <i class="bi bi-exclamation-triangle me-1"></i>
              Atualização manual necessária
              <a href="#manual" style="color:var(--primary);font-weight:700;"> Ver instruções</a>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php else: ?>
        <div style="background:rgba(40,167,69,.07);border:1px solid rgba(40,167,69,.2);border-radius:10px;padding:16px;font-size:.88rem;color:#166534;">
          <i class="bi bi-check-circle-fill me-2" style="color:#28a745;"></i>
          <?= !empty($info['latest_commit']) ? 'Sistema atualizado! Você está na versão mais recente.' : 'Clique em "Verificar Agora" para checar atualizações.' ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($info['checked_at'])): ?>
        <div style="font-size:.75rem;color:#aaa;margin-top:10px;text-align:right;">
          Última verificação: <?= upd_formatDate($info['checked_at']) ?>
        </div>
        <?php endif; ?>

      </div>
    </div>

    <!-- Instruções manuais (fallback) -->
    <?php if (!$canUpdate): ?>
    <div class="admin-card mb-4" id="manual">
      <div class="admin-card-header">
        <h5><i class="bi bi-terminal"></i> Atualização Manual</h5>
      </div>
      <div class="admin-card-body">
        <p style="font-size:.88rem;color:#666;">
          <?php if (!$canGit): ?>
          Git ou <code>exec()</code> não está disponível neste servidor. Atualize os arquivos manualmente:
          <?php else: ?>
          O diretório não é um repositório Git. Faça a atualização manualmente:
          <?php endif; ?>
        </p>
        <ol style="font-size:.85rem;color:#555;line-height:1.9;">
          <li>Acesse o servidor via SSH ou FTP</li>
          <li>Navegue até a pasta do sistema: <code><?= h(ROOT_PATH) ?></code></li>
          <li>Execute: <code>git pull origin <?= h($info['branch'] ?? 'claude/eager-hawking-fdE84') ?></code></li>
          <li>Ou baixe os arquivos do GitHub e faça upload via FTP preservando a pasta <code>config/</code></li>
        </ol>
        <?php if (!empty($info['repo'])): ?>
        <a href="https://github.com/<?= h($info['repo']) ?>/archive/<?= h($info['branch'] ?? 'main') ?>.zip"
           class="btn btn-sm"
           style="background:var(--primary);color:#fff;border-radius:7px;font-size:.83rem;"
           target="_blank">
          <i class="bi bi-download me-1"></i>Baixar ZIP do GitHub
        </a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>

  <!-- Painel lateral -->
  <div class="col-lg-4">

    <!-- Info do servidor -->
    <div class="admin-card mb-4">
      <div class="admin-card-header"><h5><i class="bi bi-server"></i> Servidor</h5></div>
      <div class="admin-card-body">
        <?php
        $checks = [
            ['label' => 'PHP', 'ok' => true, 'valor' => PHP_VERSION],
            ['label' => 'exec() disponível', 'ok' => upd_canExec(), 'valor' => ''],
            ['label' => 'Git disponível', 'ok' => $canGit, 'valor' => ''],
            ['label' => 'Repositório Git', 'ok' => $isGitRepo, 'valor' => ''],
            ['label' => 'config/ gravável', 'ok' => is_writable(ROOT_PATH . '/config'), 'valor' => ''],
        ];
        foreach ($checks as $c):
        ?>
        <div style="display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid #f0f0f0;font-size:.84rem;">
          <i class="bi <?= $c['ok'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"
             style="color:<?= $c['ok'] ? '#28a745' : 'var(--accent)' ?>;flex-shrink:0;"></i>
          <span style="flex:1;color:#444;"><?= h($c['label']) ?></span>
          <?php if ($c['valor']): ?>
          <code style="font-size:.78rem;"><?= h($c['valor']) ?></code>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <div style="font-size:.75rem;color:#888;margin-top:10px;">
          Branch: <code><?= h($info['branch'] ?? '—') ?></code>
        </div>
      </div>
    </div>

    <!-- Configurações -->
    <div class="admin-card">
      <div class="admin-card-header"><h5><i class="bi bi-gear-fill"></i> Configurações</h5></div>
      <div class="admin-card-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= gerarCSRF() ?>">
          <input type="hidden" name="acao" value="salvar_config">

          <div class="admin-form-group">
            <label style="font-size:.83rem;font-weight:700;color:var(--primary);">Branch do GitHub</label>
            <input type="text" class="form-control form-control-sm" name="branch"
                   value="<?= h($info['branch'] ?? 'claude/eager-hawking-fdE84') ?>"
                   placeholder="main">
            <div style="font-size:.73rem;color:#888;margin-top:4px;">Branch a monitorar para atualizações</div>
          </div>

          <div class="admin-form-group">
            <label style="font-size:.83rem;font-weight:700;color:var(--primary);">GitHub Token <span style="font-weight:400;color:#999;">(opcional)</span></label>
            <input type="password" class="form-control form-control-sm" name="github_token"
                   value="<?= h($token) ?>"
                   placeholder="ghp_xxxxxxxxxxxx">
            <div style="font-size:.73rem;color:#888;margin-top:4px;">
              Necessário para repositórios privados ou para evitar limite de 60 req/hora da API.
              <a href="https://github.com/settings/tokens" target="_blank" style="color:var(--primary);">Criar token</a>
            </div>
          </div>

          <button type="submit" class="btn w-100"
                  style="background:var(--primary);color:#fff;font-weight:700;border-radius:8px;padding:9px;font-size:.88rem;">
            <i class="bi bi-check2 me-1"></i>Salvar Configurações
          </button>
        </form>
      </div>
    </div>

  </div>
</div>

<script>
function confirmarUpdate() {
  const btn = document.getElementById('btn-update');
  if (!confirm('Confirma a atualização do sistema?\n\nO sistema será atualizado com o código mais recente do GitHub. Recomenda-se fazer backup antes.')) {
    return false;
  }
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Atualizando...';
  }
  return true;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
