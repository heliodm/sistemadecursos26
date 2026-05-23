<?php ob_start();
$pageTitle = 'Configurações do Sistema';
require_once __DIR__ . '/includes/header.php';
requireAdmin();

$db    = getDB();
$erros = [];
$aba   = sanitize($_GET['aba'] ?? 'geral');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarCSRF($_POST['csrf_token'] ?? '')) {
        redirect('/admin/configuracoes.php', 'Token inválido.', 'danger');
    }
    $postAba = sanitize($_POST['aba'] ?? '');

    if ($postAba === 'geral') {
        setConfig('site_nome', sanitize($_POST['site_nome'] ?? ''));
        setConfig('site_descricao', sanitize($_POST['site_descricao'] ?? ''));
        setConfig('email_contato', sanitize($_POST['email_contato'] ?? ''));
        setConfig('rodape_texto', sanitize($_POST['rodape_texto'] ?? ''));

        // Upload logo
        if (!empty($_FILES['site_logo']['name'])) {
            $up = uploadImagem($_FILES['site_logo'], 'logos');
            if ($up['sucesso']) {
                $oldLogo = getConfig('site_logo');
                if ($oldLogo) deletarUpload($oldLogo);
                setConfig('site_logo', $up['arquivo']);
            } else {
                $erros[] = 'Logo: ' . $up['erro'];
            }
        }
        if (empty($erros)) redirect('/admin/configuracoes.php?aba=geral', 'Configurações gerais salvas.', 'success');
    }

    if ($postAba === 'menu') {
        for ($i = 1; $i <= 3; $i++) {
            setConfig("menu_item_{$i}_label", sanitize($_POST["menu_item_{$i}_label"] ?? ''));
            setConfig("menu_item_{$i}_url", sanitize($_POST["menu_item_{$i}_url"] ?? ''));
            setConfig("menu_item_{$i}_ativo", isset($_POST["menu_item_{$i}_ativo"]) ? '1' : '0');
        }
        redirect('/admin/configuracoes.php?aba=menu', 'Menu atualizado com sucesso.', 'success');
    }

    if ($postAba === 'pagamentos') {
        $formas = ['pix', 'transferencia', 'deposito', 'cartao'];
        foreach ($formas as $f) {
            setConfig("{$f}_ativo", isset($_POST["{$f}_ativo"]) ? '1' : '0');
            setConfig("{$f}_instrucoes", sanitize($_POST["{$f}_instrucoes"] ?? ''));
        }
        // Dados bancários e chave PIX
        setConfig('pix_chave', sanitize($_POST['pix_chave'] ?? ''));
        setConfig('transferencia_banco', sanitize($_POST['transferencia_banco'] ?? ''));
        setConfig('transferencia_agencia', sanitize($_POST['transferencia_agencia'] ?? ''));
        setConfig('transferencia_conta', sanitize($_POST['transferencia_conta'] ?? ''));
        setConfig('transferencia_titular', sanitize($_POST['transferencia_titular'] ?? ''));
        setConfig('deposito_banco', sanitize($_POST['deposito_banco'] ?? ''));
        setConfig('deposito_agencia', sanitize($_POST['deposito_agencia'] ?? ''));
        setConfig('deposito_conta', sanitize($_POST['deposito_conta'] ?? ''));
        setConfig('deposito_titular', sanitize($_POST['deposito_titular'] ?? ''));
        // InfinitePay
        setConfig('infinitepay_client_id', sanitize($_POST['infinitepay_client_id'] ?? ''));
        if (!empty($_POST['infinitepay_client_secret'])) {
            setConfig('infinitepay_client_secret', sanitize($_POST['infinitepay_client_secret'] ?? ''));
        }
        if (!empty($_POST['infinitepay_webhook_secret'])) {
            setConfig('infinitepay_webhook_secret', sanitize($_POST['infinitepay_webhook_secret'] ?? ''));
        }
        $ambiente = in_array($_POST['infinitepay_ambiente'] ?? '', ['producao', 'sandbox']) ? $_POST['infinitepay_ambiente'] : 'producao';
        setConfig('infinitepay_ambiente', $ambiente);
        $maxParc = max(1, min(12, (int)($_POST['infinitepay_max_parcelas'] ?? 12)));
        setConfig('infinitepay_max_parcelas', (string)$maxParc);
        redirect('/admin/configuracoes.php?aba=pagamentos', 'Opções de pagamento atualizadas.', 'success');
    }

    if ($postAba === 'certificado') {
        setConfig('cert_titulo', sanitize($_POST['cert_titulo'] ?? ''));
        setConfig('cert_texto', sanitize($_POST['cert_texto'] ?? ''));
        setConfig('cert_assinatura_nome', sanitize($_POST['cert_assinatura_nome'] ?? ''));
        setConfig('cert_assinatura_cargo', sanitize($_POST['cert_assinatura_cargo'] ?? ''));
        setConfig('cert_cidade', sanitize($_POST['cert_cidade'] ?? ''));
        setConfig('cert_validade_texto', sanitize($_POST['cert_validade_texto'] ?? ''));

        // Upload background
        if (!empty($_FILES['cert_background']['name'])) {
            $up = uploadImagem($_FILES['cert_background'], 'certificados/backgrounds');
            if ($up['sucesso']) {
                $old = getConfig('cert_background');
                if ($old) deletarUpload($old);
                setConfig('cert_background', $up['arquivo']);
            } else $erros[] = 'Background: ' . $up['erro'];
        }
        // Upload logo certificado
        if (!empty($_FILES['cert_logo']['name'])) {
            $up = uploadImagem($_FILES['cert_logo'], 'logos');
            if ($up['sucesso']) {
                $old = getConfig('cert_logo');
                if ($old) deletarUpload($old);
                setConfig('cert_logo', $up['arquivo']);
            } else $erros[] = 'Logo certificado: ' . $up['erro'];
        }
        // Upload assinatura
        if (!empty($_FILES['cert_assinatura_imagem']['name'])) {
            $up = uploadImagem($_FILES['cert_assinatura_imagem'], 'logos');
            if ($up['sucesso']) {
                $old = getConfig('cert_assinatura_imagem');
                if ($old) deletarUpload($old);
                setConfig('cert_assinatura_imagem', $up['arquivo']);
            } else $erros[] = 'Assinatura: ' . $up['erro'];
        }
        if (empty($erros)) redirect('/admin/configuracoes.php?aba=certificado', 'Configurações do certificado salvas.', 'success');
    }
}

$configs = getAllConfigs();

function cfg(string $key, array $c, string $d = ''): string {
    return h($c[$key] ?? $d);
}
?>

<?php if (!empty($erros)): ?>
<div class="alert alert-danger mb-4">
  <ul class="mb-0"><?php foreach ($erros as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<ul class="nav nav-tabs mb-4">
  <?php
  $tabs = ['geral' => ['icon'=>'bi-gear','label'=>'Geral'], 'menu'=>['icon'=>'bi-list','label'=>'Menu'], 'pagamentos'=>['icon'=>'bi-cash-coin','label'=>'Pagamentos'], 'certificado'=>['icon'=>'bi-patch-check','label'=>'Certificado']];
  foreach ($tabs as $key => $t):
  ?>
  <li class="nav-item">
    <a class="nav-link <?= $aba === $key ? 'active fw-bold' : '' ?>"
       href="?aba=<?= $key ?>"
       style="<?= $aba === $key ? 'color:var(--primary);border-bottom:3px solid var(--primary);' : 'color:#888;' ?>">
      <i class="bi <?= $t['icon'] ?> me-1"></i><?= $t['label'] ?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<!-- ABA GERAL -->
<?php if ($aba === 'geral'): ?>
<form method="POST" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?= gerarCSRF() ?>">
  <input type="hidden" name="aba" value="geral">
  <div class="row g-4">
    <div class="col-lg-8">
      <div class="admin-card">
        <div class="admin-card-header"><h5><i class="bi bi-info-circle-fill"></i> Informações do Site</h5></div>
        <div class="admin-card-body">
          <div class="admin-form-group">
            <label>Nome do Site <span class="required">*</span></label>
            <input type="text" class="form-control" name="site_nome" value="<?= cfg('site_nome', $configs) ?>" maxlength="100" required>
          </div>
          <div class="admin-form-group">
            <label>Descrição do Site</label>
            <textarea class="form-control" name="site_descricao" rows="2" maxlength="300"><?= cfg('site_descricao', $configs) ?></textarea>
          </div>
          <div class="admin-form-group">
            <label>E-mail de Contato</label>
            <input type="email" class="form-control" name="email_contato" value="<?= cfg('email_contato', $configs) ?>" maxlength="150">
          </div>
          <div class="admin-form-group">
            <label>Texto do Rodapé</label>
            <input type="text" class="form-control" name="rodape_texto" value="<?= cfg('rodape_texto', $configs) ?>" maxlength="200">
          </div>
          <button type="submit" class="btn" style="background:var(--primary);color:#fff;font-weight:700;border-radius:8px;padding:11px 24px;">
            <i class="bi bi-check2 me-1"></i>Salvar Configurações Gerais
          </button>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="admin-card">
        <div class="admin-card-header"><h5><i class="bi bi-image"></i> Logo do Site</h5></div>
        <div class="admin-card-body">
          <?php $logoAtual = urlImagem($configs['site_logo'] ?? ''); ?>
          <?php if ($logoAtual): ?>
          <img src="<?= h($logoAtual) ?>" alt="Logo atual" style="max-height:80px;max-width:100%;object-fit:contain;margin-bottom:12px;display:block;">
          <?php endif; ?>
          <div class="upload-zone" onclick="document.getElementById('site_logo').click()">
            <i class="bi bi-cloud-upload" style="font-size:2rem;color:#ccc;"></i>
            <p style="font-size:.82rem;color:#888;margin:4px 0 0;">Clique para upload da logo</p>
            <p style="font-size:.72rem;color:#bbb;margin:0;">PNG, WebP recomendado · Máx 5MB</p>
          </div>
          <input type="file" class="upload-input d-none" id="site_logo" name="site_logo"
                 data-preview="preview-logo" accept="image/*">
          <img id="preview-logo" class="upload-preview" alt="Preview">
        </div>
      </div>
    </div>
  </div>
</form>

<!-- ABA MENU -->
<?php elseif ($aba === 'menu'): ?>
<form method="POST">
  <input type="hidden" name="csrf_token" value="<?= gerarCSRF() ?>">
  <input type="hidden" name="aba" value="menu">
  <div class="admin-card">
    <div class="admin-card-header"><h5><i class="bi bi-list"></i> Itens do Menu</h5></div>
    <div class="admin-card-body">
      <p style="font-size:.88rem;color:#666;margin-bottom:20px;">Configure os 3 botões do menu de navegação do site público.</p>
      <?php for ($i = 1; $i <= 3; $i++): ?>
      <div class="p-3 mb-3" style="background:var(--gray-light);border-radius:10px;border:1px solid var(--gray-mid);">
        <div class="d-flex align-items-center gap-2 mb-3">
          <span class="badge" style="background:var(--primary);width:28px;height:28px;display:flex;align-items:center;justify-content:center;border-radius:50%;font-size:.85rem;"><?= $i ?></span>
          <strong style="color:var(--primary);">Item <?= $i ?></strong>
          <div class="form-check form-switch ms-auto mb-0">
            <input class="form-check-input" type="checkbox" name="menu_item_<?= $i ?>_ativo"
                   id="menu_ativo_<?= $i ?>" style="width:40px;height:20px;"
                   <?= ($configs["menu_item_{$i}_ativo"] ?? '1') === '1' ? 'checked' : '' ?>>
            <label class="form-check-label fw-bold" for="menu_ativo_<?= $i ?>" style="font-size:.82rem;">Ativo</label>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-md-4">
            <div class="admin-form-group mb-0">
              <label>Label (Texto)</label>
              <input type="text" class="form-control" name="menu_item_<?= $i ?>_label"
                     value="<?= cfg("menu_item_{$i}_label", $configs) ?>" maxlength="50">
            </div>
          </div>
          <div class="col-md-8">
            <div class="admin-form-group mb-0">
              <label>URL (Link)</label>
              <input type="text" class="form-control" name="menu_item_<?= $i ?>_url"
                     value="<?= cfg("menu_item_{$i}_url", $configs) ?>" maxlength="300"
                     placeholder="Ex: /index.php, /certificados.php, https://...">
            </div>
          </div>
        </div>
      </div>
      <?php endfor; ?>
      <button type="submit" class="btn" style="background:var(--primary);color:#fff;font-weight:700;border-radius:8px;padding:11px 24px;">
        <i class="bi bi-check2 me-1"></i>Salvar Menu
      </button>
    </div>
  </div>
</form>

<!-- ABA PAGAMENTOS -->
<?php elseif ($aba === 'pagamentos'): ?>
<form method="POST">
  <input type="hidden" name="csrf_token" value="<?= gerarCSRF() ?>">
  <input type="hidden" name="aba" value="pagamentos">
  <div class="row g-4">

    <!-- PIX -->
    <div class="col-lg-6">
      <div class="admin-card">
        <div class="admin-card-header">
          <h5><i class="bi bi-qr-code-scan"></i> PIX</h5>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" name="pix_ativo" id="pix_ativo" style="width:40px;height:20px;" <?= ($configs['pix_ativo'] ?? '0') === '1' ? 'checked' : '' ?>>
            <label class="form-check-label fw-bold" for="pix_ativo" style="font-size:.82rem;">Habilitado</label>
          </div>
        </div>
        <div class="admin-card-body">
          <div class="admin-form-group">
            <label>Chave PIX</label>
            <input type="text" class="form-control" name="pix_chave" value="<?= cfg('pix_chave', $configs) ?>" maxlength="150" placeholder="CPF, e-mail, telefone ou chave aleatória">
          </div>
          <div class="admin-form-group mb-0">
            <label>Instruções ao Inscrito</label>
            <textarea class="form-control" name="pix_instrucoes" rows="3"><?= cfg('pix_instrucoes', $configs) ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- Transferência -->
    <div class="col-lg-6">
      <div class="admin-card">
        <div class="admin-card-header">
          <h5><i class="bi bi-bank2"></i> Transferência Bancária</h5>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" name="transferencia_ativo" id="transf_ativo" style="width:40px;height:20px;" <?= ($configs['transferencia_ativo'] ?? '0') === '1' ? 'checked' : '' ?>>
            <label class="form-check-label fw-bold" for="transf_ativo" style="font-size:.82rem;">Habilitado</label>
          </div>
        </div>
        <div class="admin-card-body">
          <div class="row g-2">
            <div class="col-12"><div class="admin-form-group mb-2"><label>Banco</label><input type="text" class="form-control" name="transferencia_banco" value="<?= cfg('transferencia_banco', $configs) ?>" maxlength="100"></div></div>
            <div class="col-6"><div class="admin-form-group mb-2"><label>Agência</label><input type="text" class="form-control" name="transferencia_agencia" value="<?= cfg('transferencia_agencia', $configs) ?>" maxlength="20"></div></div>
            <div class="col-6"><div class="admin-form-group mb-2"><label>Conta</label><input type="text" class="form-control" name="transferencia_conta" value="<?= cfg('transferencia_conta', $configs) ?>" maxlength="30"></div></div>
            <div class="col-12"><div class="admin-form-group mb-2"><label>Titular</label><input type="text" class="form-control" name="transferencia_titular" value="<?= cfg('transferencia_titular', $configs) ?>" maxlength="150"></div></div>
          </div>
          <div class="admin-form-group mb-0">
            <label>Instruções ao Inscrito</label>
            <textarea class="form-control" name="transferencia_instrucoes" rows="2"><?= cfg('transferencia_instrucoes', $configs) ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- Depósito -->
    <div class="col-lg-6">
      <div class="admin-card">
        <div class="admin-card-header">
          <h5><i class="bi bi-cash-stack"></i> Depósito Identificado</h5>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" name="deposito_ativo" id="dep_ativo" style="width:40px;height:20px;" <?= ($configs['deposito_ativo'] ?? '0') === '1' ? 'checked' : '' ?>>
            <label class="form-check-label fw-bold" for="dep_ativo" style="font-size:.82rem;">Habilitado</label>
          </div>
        </div>
        <div class="admin-card-body">
          <div class="row g-2">
            <div class="col-12"><div class="admin-form-group mb-2"><label>Banco</label><input type="text" class="form-control" name="deposito_banco" value="<?= cfg('deposito_banco', $configs) ?>" maxlength="100"></div></div>
            <div class="col-6"><div class="admin-form-group mb-2"><label>Agência</label><input type="text" class="form-control" name="deposito_agencia" value="<?= cfg('deposito_agencia', $configs) ?>" maxlength="20"></div></div>
            <div class="col-6"><div class="admin-form-group mb-2"><label>Conta</label><input type="text" class="form-control" name="deposito_conta" value="<?= cfg('deposito_conta', $configs) ?>" maxlength="30"></div></div>
            <div class="col-12"><div class="admin-form-group mb-2"><label>Titular</label><input type="text" class="form-control" name="deposito_titular" value="<?= cfg('deposito_titular', $configs) ?>" maxlength="150"></div></div>
          </div>
          <div class="admin-form-group mb-0">
            <label>Instruções ao Inscrito</label>
            <textarea class="form-control" name="deposito_instrucoes" rows="2"><?= cfg('deposito_instrucoes', $configs) ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- Cartão / InfinitePay -->
    <div class="col-12">
      <div class="admin-card">
        <div class="admin-card-header">
          <h5><i class="bi bi-credit-card"></i> Cartão de Crédito — InfinitePay</h5>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" name="cartao_ativo" id="cartao_ativo" style="width:40px;height:20px;" <?= ($configs['cartao_ativo'] ?? '0') === '1' ? 'checked' : '' ?>>
            <label class="form-check-label fw-bold" for="cartao_ativo" style="font-size:.82rem;">Habilitado</label>
          </div>
        </div>
        <div class="admin-card-body">
          <?php
          $ipOk = !empty($configs['infinitepay_client_id'] ?? '') && !empty($configs['infinitepay_client_secret'] ?? '');
          if ($ipOk): ?>
          <div class="alert alert-success mb-3" style="border-radius:8px;font-size:.85rem;">
            <i class="bi bi-check-circle-fill me-1"></i>
            Credenciais configuradas. A opção de cartão está integrada com a InfinitePay.
          </div>
          <?php else: ?>
          <div class="alert alert-warning mb-3" style="border-radius:8px;font-size:.85rem;">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            Preencha o <strong>Client ID</strong> e <strong>Client Secret</strong> da InfinitePay para ativar o pagamento por cartão.
            Obtenha suas credenciais em <a href="https://money.infinitepay.io/settings/credentials" target="_blank">money.infinitepay.io/settings/credentials</a>.
          </div>
          <?php endif; ?>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="admin-form-group mb-0">
                <label>Ambiente</label>
                <select class="form-select" name="infinitepay_ambiente">
                  <option value="producao" <?= ($configs['infinitepay_ambiente'] ?? 'producao') === 'producao' ? 'selected' : '' ?>>Produção</option>
                  <option value="sandbox" <?= ($configs['infinitepay_ambiente'] ?? 'producao') === 'sandbox' ? 'selected' : '' ?>>Sandbox (Testes)</option>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="admin-form-group mb-0">
                <label>Máximo de Parcelas</label>
                <select class="form-select" name="infinitepay_max_parcelas">
                  <?php for ($p = 1; $p <= 12; $p++): ?>
                  <option value="<?= $p ?>" <?= (int)($configs['infinitepay_max_parcelas'] ?? 12) === $p ? 'selected' : '' ?>><?= $p ?>x</option>
                  <?php endfor; ?>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="admin-form-group mb-0">
                <label>Client ID</label>
                <input type="text" class="form-control" name="infinitepay_client_id"
                       value="<?= cfg('infinitepay_client_id', $configs) ?>" maxlength="200"
                       placeholder="Ex: abc123">
              </div>
            </div>
            <div class="col-md-6">
              <div class="admin-form-group mb-0">
                <label>Client Secret <small class="text-muted">(deixe em branco para manter)</small></label>
                <input type="password" class="form-control" name="infinitepay_client_secret"
                       placeholder="<?= !empty($configs['infinitepay_client_secret'] ?? '') ? '••••••••••••' : 'Sua chave secreta' ?>">
              </div>
            </div>
            <div class="col-md-6">
              <div class="admin-form-group mb-0">
                <label>Webhook Secret <small class="text-muted">(opcional — deixe em branco para manter)</small></label>
                <input type="password" class="form-control" name="infinitepay_webhook_secret"
                       placeholder="<?= !empty($configs['infinitepay_webhook_secret'] ?? '') ? '••••••••••••' : 'Chave de validação do webhook' ?>">
                <div class="form-text">URL do webhook: <code><?= h(BASE_PATH) ?>/api/infinitepay-webhook.php</code></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12">
      <button type="submit" class="btn" style="background:var(--primary);color:#fff;font-weight:700;border-radius:8px;padding:11px 28px;">
        <i class="bi bi-check2 me-1"></i>Salvar Configurações de Pagamento
      </button>
    </div>
  </div>
</form>

<!-- ABA CERTIFICADO -->
<?php elseif ($aba === 'certificado'): ?>
<form method="POST" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?= gerarCSRF() ?>">
  <input type="hidden" name="aba" value="certificado">
  <div class="row g-4">
    <div class="col-lg-7">
      <div class="admin-card">
        <div class="admin-card-header"><h5><i class="bi bi-patch-check-fill"></i> Conteúdo do Certificado</h5></div>
        <div class="admin-card-body">
          <div class="admin-form-group">
            <label>Título do Certificado</label>
            <input type="text" class="form-control" name="cert_titulo" value="<?= cfg('cert_titulo', $configs) ?>" maxlength="100">
          </div>
          <div class="admin-form-group">
            <label>Texto Principal</label>
            <textarea class="form-control" name="cert_texto" rows="4"><?= cfg('cert_texto', $configs) ?></textarea>
            <div class="form-text">
              Variáveis disponíveis: <code>{NOME}</code> <code>{CURSO}</code> <code>{DATA}</code> <code>{CARGA_HORARIA}</code> <code>{CODIGO}</code> <code>{CIDADE}</code> <code>{DATA_EMISSAO}</code>
            </div>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="admin-form-group">
                <label>Nome do Assinante</label>
                <input type="text" class="form-control" name="cert_assinatura_nome" value="<?= cfg('cert_assinatura_nome', $configs) ?>" maxlength="150">
              </div>
            </div>
            <div class="col-md-6">
              <div class="admin-form-group">
                <label>Cargo/Função</label>
                <input type="text" class="form-control" name="cert_assinatura_cargo" value="<?= cfg('cert_assinatura_cargo', $configs) ?>" maxlength="100">
              </div>
            </div>
            <div class="col-md-6">
              <div class="admin-form-group">
                <label>Cidade</label>
                <input type="text" class="form-control" name="cert_cidade" value="<?= cfg('cert_cidade', $configs) ?>" maxlength="100">
              </div>
            </div>
          </div>
          <div class="admin-form-group">
            <label>Texto de Validação</label>
            <input type="text" class="form-control" name="cert_validade_texto" value="<?= cfg('cert_validade_texto', $configs) ?>" maxlength="300">
            <div class="form-text">Use <code>{CODIGO}</code> para o código único do certificado</div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="admin-card mb-4">
        <div class="admin-card-header"><h5><i class="bi bi-image"></i> Background do Certificado</h5></div>
        <div class="admin-card-body">
          <?php $bgAtual = urlImagem($configs['cert_background'] ?? ''); ?>
          <?php if ($bgAtual): ?>
          <img src="<?= h($bgAtual) ?>" alt="Background atual" style="width:100%;height:120px;object-fit:cover;border-radius:8px;margin-bottom:10px;">
          <?php endif; ?>
          <div class="upload-zone" onclick="document.getElementById('cert_background').click()">
            <i class="bi bi-image" style="font-size:1.8rem;color:#ccc;"></i>
            <p style="font-size:.8rem;color:#888;margin:4px 0 0;">Upload do background A4 paisagem</p>
            <p style="font-size:.72rem;color:#bbb;">Recomendado: 1122×794px · JPG/PNG · Máx 5MB</p>
          </div>
          <input type="file" class="upload-input d-none" id="cert_background" name="cert_background" data-preview="prev-bg" accept="image/*">
          <img id="prev-bg" class="upload-preview w-100" style="max-height:120px;object-fit:cover;" alt="Preview">
        </div>
      </div>

      <div class="admin-card mb-4">
        <div class="admin-card-header"><h5><i class="bi bi-image-fill"></i> Logo no Certificado</h5></div>
        <div class="admin-card-body">
          <?php $certLogoAtual = urlImagem($configs['cert_logo'] ?? ''); ?>
          <?php if ($certLogoAtual): ?>
          <img src="<?= h($certLogoAtual) ?>" alt="Logo atual" style="max-height:60px;object-fit:contain;margin-bottom:10px;display:block;">
          <?php endif; ?>
          <div class="upload-zone" onclick="document.getElementById('cert_logo').click()">
            <i class="bi bi-cloud-upload" style="font-size:1.5rem;color:#ccc;"></i>
            <p style="font-size:.8rem;color:#888;margin:2px 0 0;">PNG com transparência recomendado</p>
          </div>
          <input type="file" class="upload-input d-none" id="cert_logo" name="cert_logo" data-preview="prev-cert-logo" accept="image/*">
          <img id="prev-cert-logo" class="upload-preview" style="max-height:80px;" alt="Preview">
        </div>
      </div>

      <div class="admin-card">
        <div class="admin-card-header"><h5><i class="bi bi-pen"></i> Imagem de Assinatura</h5></div>
        <div class="admin-card-body">
          <?php $assAtual = urlImagem($configs['cert_assinatura_imagem'] ?? ''); ?>
          <?php if ($assAtual): ?>
          <img src="<?= h($assAtual) ?>" alt="Assinatura atual" style="max-height:60px;object-fit:contain;margin-bottom:10px;display:block;">
          <?php endif; ?>
          <div class="upload-zone" onclick="document.getElementById('cert_assinatura_imagem').click()">
            <i class="bi bi-pen" style="font-size:1.5rem;color:#ccc;"></i>
            <p style="font-size:.8rem;color:#888;margin:2px 0 0;">PNG com fundo transparente</p>
          </div>
          <input type="file" class="upload-input d-none" id="cert_assinatura_imagem" name="cert_assinatura_imagem" data-preview="prev-ass" accept="image/*">
          <img id="prev-ass" class="upload-preview" style="max-height:70px;" alt="Preview">
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="d-flex gap-3 align-items-center flex-wrap">
        <button type="submit" class="btn" style="background:var(--primary);color:#fff;font-weight:700;border-radius:8px;padding:11px 28px;">
          <i class="bi bi-check2 me-1"></i>Salvar Configurações do Certificado
        </button>
        <?php
        $primeiroCert = $db->query("SELECT codigo_unico FROM certificados WHERE valido=1 LIMIT 1")->fetchColumn();
        if ($primeiroCert):
        ?>
        <a href="<?= BASE_PATH ?>/certificado-visualizar.php?codigo=<?= h($primeiroCert) ?>" target="_blank"
           class="btn btn-outline-secondary" style="border-radius:8px;font-weight:700;">
          <i class="bi bi-eye me-1"></i>Pré-visualizar Certificado
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</form>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
