<?php
// Processa POST antes de qualquer saída HTML (evita headers-already-sent)
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/updater.php';

requireLogin('/login.php');
requireAdmin();

$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarCSRF($_POST['csrf_token'] ?? '')) {
        redirect('/admin/atualizacoes.php', 'Token de segurança inválido. Tente novamente.', 'danger');
    }

    $acao = sanitize($_POST['acao'] ?? '');

    if ($acao === 'verificar') {
        $prev = upd_readVersion();
        $info = upd_checkGithub(true);
        if (!empty($info['api_error'])) {
            redirect('/admin/atualizacoes.php', 'Erro ao verificar: ' . $info['api_error'], 'danger');
        }
        $msg = 'Verificação concluída.';
        if (($prev['commit'] ?? '') === 'desconhecido' && !empty($info['latest_commit'])) {
            $msg = 'Versão atual inicializada como ' . $info['commit'] . '. Futuras atualizações serão detectadas automaticamente.';
        }
        redirect('/admin/atualizacoes.php', $msg, 'success');
    }

    if ($acao === 'atualizar') {
        $resultado = upd_executeUpdate();
        if ($resultado['sucesso']) {
            redirect('/admin/atualizacoes.php', 'Sistema atualizado! Versão: ' . $resultado['commit'], 'success');
        }
        // mantém $resultado para exibir o erro inline
    }

    if ($acao === 'salvar_config') {
        $token  = trim($_POST['github_token'] ?? '');
        $branch = preg_replace('/[^a-zA-Z0-9\/_\-\.]/', '', trim($_POST['branch'] ?? ''));

        $db = getDB();
        $db->prepare("INSERT INTO configuracoes (chave, valor) VALUES ('github_token',?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)")
           ->execute([$token]);

        if ($branch) {
            $v = upd_readVersion();
            $v['branch'] = $branch;
            upd_writeVersion($v);
        }
        redirect('/admin/atualizacoes.php', 'Configurações salvas.', 'success');
    }
}

// ── HTML output começa aqui ──────────────────────────────────────────────────
$pageTitle = 'Atualizações do Sistema';
require_once __DIR__ . '/includes/header.php';

$info  = upd_readVersion();
$token = getConfig('github_token', '');

$canDownload = upd_canDownload();
$canExtract  = upd_canExtract();
$canWrite    = upd_canWrite();
$canUpdate   = $canDownload && $canExtract && $canWrite;
?>

<div class="row g-4">

  <!-- ── Coluna principal ── -->
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
          <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Falha na atualização</strong>
          <pre class="mb-0 mt-2" style="font-size:.8rem;white-space:pre-wrap;background:rgba(0,0,0,.05);padding:10px;border-radius:6px;"><?= h($resultado['output']) ?></pre>
        </div>
        <?php endif; ?>

        <!-- Versão atual vs GitHub -->
        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <div style="background:rgba(27,58,107,.05);border-radius:10px;padding:18px;">
              <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#888;margin-bottom:6px;">
                <i class="bi bi-hdd me-1"></i>Versão Instalada
              </div>
              <code style="font-size:1.1rem;font-weight:700;color:var(--primary);"><?= h($info['commit']) ?></code>
              <?php if (!empty($info['updated_at'])): ?>
              <div style="font-size:.75rem;color:#888;margin-top:6px;">
                <i class="bi bi-clock me-1"></i>Atualizado em <?= upd_formatDate($info['updated_at']) ?>
              </div>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-md-6">
            <?php
            $hasNew  = !empty($info['update_available']);
            $bgColor = $hasNew ? 'rgba(201,162,39,.08)' : 'rgba(40,167,69,.06)';
            $border  = $hasNew ? 'rgba(201,162,39,.3)' : 'rgba(40,167,69,.2)';
            $txtColor= $hasNew ? 'var(--secondary)' : '#28a745';
            ?>
            <div style="background:<?= $bgColor ?>;border:1px solid <?= $border ?>;border-radius:10px;padding:18px;">
              <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#888;margin-bottom:6px;">
                <i class="bi bi-github me-1"></i>Versão no GitHub
              </div>
              <?php if (!empty($info['latest_commit'])): ?>
                <code style="font-size:1.1rem;font-weight:700;color:<?= $txtColor ?>;"><?= h($info['latest_commit']) ?></code>
                <?php if (!empty($info['latest_date'])): ?>
                <div style="font-size:.75rem;color:#888;margin-top:6px;">
                  <i class="bi bi-clock me-1"></i><?= upd_formatDate($info['latest_date']) ?>
                </div>
                <?php endif; ?>
              <?php elseif (!empty($info['api_error'])): ?>
                <span style="font-size:.82rem;color:var(--accent);"><i class="bi bi-exclamation-circle me-1"></i><?= h($info['api_error']) ?></span>
              <?php else: ?>
                <span style="font-size:.85rem;color:#aaa;">Nunca verificado — clique em "Verificar Agora"</span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Bloco de atualização disponível -->
        <?php if ($hasNew): ?>
        <div style="background:linear-gradient(135deg,rgba(201,162,39,.1),rgba(201,162,39,.04));border:1px solid rgba(201,162,39,.3);border-radius:12px;padding:20px;" class="mb-3">
          <div class="d-flex align-items-start gap-3 flex-wrap">
            <div style="flex:1;min-width:0;">
              <div style="font-weight:700;color:var(--secondary);font-size:.95rem;margin-bottom:6px;">
                <i class="bi bi-arrow-up-circle-fill me-1"></i>Nova versão disponível!
              </div>
              <?php if (!empty($info['latest_message'])): ?>
              <div style="font-size:.84rem;color:#555;margin-bottom:5px;word-break:break-word;">
                <i class="bi bi-chat-left-text me-1 text-muted"></i><?= h(explode("\n", $info['latest_message'])[0]) ?>
              </div>
              <?php endif; ?>
              <?php if (!empty($info['latest_author'])): ?>
              <div style="font-size:.76rem;color:#999;">
                <i class="bi bi-person me-1"></i><?= h($info['latest_author']) ?>
              </div>
              <?php endif; ?>
            </div>

            <?php if ($canUpdate): ?>
            <form method="POST" onsubmit="return confirmarUpdate(this);">
              <input type="hidden" name="csrf_token" value="<?= gerarCSRF() ?>">
              <input type="hidden" name="acao" value="atualizar">
              <button type="submit" class="btn" id="btn-atualizar"
                      style="background:var(--secondary);color:#fff;font-weight:700;border-radius:10px;padding:11px 22px;font-size:.9rem;border:none;white-space:nowrap;">
                <i class="bi bi-cloud-download-fill me-1"></i>Atualizar Agora
              </button>
            </form>
            <?php else: ?>
            <div class="alert alert-warning mb-0 py-2 px-3" style="font-size:.82rem;">
              <i class="bi bi-exclamation-triangle me-1"></i>Atualização automática indisponível
              <a href="#prereqs" style="color:var(--primary);font-weight:700;margin-left:4px;">Ver requisitos</a>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <?php elseif (!empty($info['latest_commit'])): ?>
        <div style="background:rgba(40,167,69,.07);border:1px solid rgba(40,167,69,.2);border-radius:10px;padding:14px;font-size:.88rem;color:#166534;">
          <i class="bi bi-check-circle-fill me-2" style="color:#28a745;"></i>
          Sistema atualizado! Você já possui a versão mais recente.
        </div>
        <?php endif; ?>

        <?php if (!empty($info['checked_at'])): ?>
        <div style="font-size:.74rem;color:#bbb;margin-top:10px;text-align:right;">
          Última verificação: <?= upd_formatDate($info['checked_at']) ?> (cache de 1h)
        </div>
        <?php endif; ?>

      </div>
    </div>

    <!-- Como funciona -->
    <div class="admin-card mb-4">
      <div class="admin-card-header"><h5><i class="bi bi-info-circle"></i> Como Funciona</h5></div>
      <div class="admin-card-body">
        <ol style="font-size:.85rem;color:#555;line-height:2;padding-left:18px;margin:0;">
          <li>O sistema consulta a API do GitHub a cada 1 hora e armazena o resultado em cache.</li>
          <li>Quando há nova versão, um aviso aparece no menu lateral e na barra superior.</li>
          <li>Ao clicar em <strong>"Atualizar Agora"</strong>, o sistema baixa o arquivo <code>.zip</code> do GitHub e extrai os arquivos diretamente no servidor — sem precisar de SSH ou FTP.</li>
          <li>Os arquivos de configuração são <strong>sempre preservados</strong>: <code>config/database.php</code>, <code>config/config.php</code>, <code>config/.installed</code>, pasta <code>uploads/</code> e <code>logs/</code>.</li>
        </ol>
        <?php if (!$token): ?>
        <div class="alert alert-info mt-3 mb-0 py-2" style="font-size:.82rem;">
          <i class="bi bi-github me-1"></i>
          Se o repositório for <strong>privado</strong>, configure um <strong>GitHub Token</strong> no painel de configurações ao lado.
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <!-- ── Coluna lateral ── -->
  <div class="col-lg-4">

    <!-- Requisitos do servidor -->
    <div class="admin-card mb-4" id="prereqs">
      <div class="admin-card-header"><h5><i class="bi bi-server"></i> Requisitos do Servidor</h5></div>
      <div class="admin-card-body" style="padding-top:12px;">
        <?php
        $checks = [
            [
                'label' => 'Download via cURL',
                'ok'    => function_exists('curl_init'),
                'valor' => function_exists('curl_init') ? 'disponível (recomendado)' : '',
                'fix'   => 'cPanel → Selecionar Versão do PHP → Extensões → curl',
            ],
            [
                'label' => 'Download via allow_url_fopen',
                'ok'    => (bool)ini_get('allow_url_fopen'),
                'valor' => ini_get('allow_url_fopen') ? 'ativo (fallback)' : '',
                'fix'   => 'cPanel → PHP → allow_url_fopen = On',
            ],
            [
                'label' => 'Extração ZIP (ZipArchive)',
                'ok'    => class_exists('ZipArchive'),
                'valor' => class_exists('ZipArchive') ? 'disponível' : '',
                'fix'   => 'cPanel → Selecionar Versão do PHP → zip',
            ],
            [
                'label' => 'Permissão de escrita',
                'ok'    => $canWrite,
                'valor' => $canWrite ? 'ok' : '',
                'fix'   => 'cPanel → Gerenciador de Arquivos → Permissões 755',
            ],
        ];
        foreach ($checks as $c):
            $ok = $c['ok'];
        ?>
        <div style="display:flex;align-items:flex-start;gap:8px;padding:9px 0;border-bottom:1px solid #f0f0f0;">
          <i class="bi <?= $ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?> mt-1"
             style="color:<?= $ok ? '#28a745' : 'var(--accent)' ?>;flex-shrink:0;font-size:.9rem;"></i>
          <div style="flex:1;">
            <div style="font-size:.83rem;font-weight:600;color:#333;"><?= h($c['label']) ?></div>
            <?php if ($ok && $c['valor']): ?>
            <div style="font-size:.74rem;color:#28a745;"><?= h($c['valor']) ?></div>
            <?php elseif (!$ok): ?>
            <div style="font-size:.74rem;color:var(--accent);"><?= h($c['fix']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>

        <div style="margin-top:12px;padding:10px;background:<?= $canUpdate ? 'rgba(40,167,69,.07)' : 'rgba(230,57,70,.06)' ?>;border-radius:8px;font-size:.82rem;text-align:center;font-weight:700;color:<?= $canUpdate ? '#166534' : 'var(--accent)' ?>;">
          <?php if ($canUpdate): ?>
          <i class="bi bi-check-circle-fill me-1"></i>Servidor pronto para atualização automática
          <?php else: ?>
          <i class="bi bi-x-circle-fill me-1"></i>Corrija os requisitos acima para habilitar a atualização automática
          <?php endif; ?>
        </div>

        <div style="font-size:.74rem;color:#aaa;margin-top:8px;text-align:center;">
          PHP <?= PHP_VERSION ?>
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
                   placeholder="main" maxlength="100">
            <div style="font-size:.73rem;color:#888;margin-top:3px;">Branch a monitorar para atualizações</div>
          </div>

          <div class="admin-form-group">
            <label style="font-size:.83rem;font-weight:700;color:var(--primary);">
              GitHub Token
              <span style="font-weight:400;color:#999;font-size:.75rem;">(repositórios privados)</span>
            </label>
            <div class="input-group input-group-sm">
              <input type="password" class="form-control" name="github_token" id="inp-token"
                     value="<?= h($token) ?>" placeholder="ghp_xxxxxxxxxxxx" maxlength="100">
              <button type="button" class="btn btn-outline-secondary"
                      onclick="toggleToken()" title="Mostrar/ocultar">
                <i class="bi bi-eye" id="ico-token"></i>
              </button>
            </div>
            <div style="font-size:.73rem;color:#888;margin-top:3px;">
              Necessário para repos privados e para evitar o limite de 60 req/hora da API pública.
              <a href="https://github.com/settings/tokens/new?scopes=repo&description=SistemaCursos" target="_blank" style="color:var(--primary);">Gerar token →</a>
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
function confirmarUpdate(form) {
  if (!confirm('Confirmar atualização do sistema?\n\nOs arquivos de configuração e uploads serão preservados.\nOs arquivos de código serão substituídos pela versão mais recente do GitHub.')) {
    return false;
  }
  const btn = document.getElementById('btn-atualizar');
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Baixando e instalando...';
  }
  return true;
}

function toggleToken() {
  const inp = document.getElementById('inp-token');
  const ico = document.getElementById('ico-token');
  if (inp.type === 'password') {
    inp.type = 'text';
    ico.className = 'bi bi-eye-slash';
  } else {
    inp.type = 'password';
    ico.className = 'bi bi-eye';
  }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
