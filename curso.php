<?php ob_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/infinitepay.php';

$slug = sanitize($_GET['slug'] ?? '');
if (!$slug) {
    redirect('/index.php', 'Curso não encontrado.', 'warning');
}

$db   = getDB();
$stmt = $db->prepare("SELECT * FROM cursos WHERE slug = ? AND ativo = 1 LIMIT 1");
$stmt->execute([$slug]);
$curso = $stmt->fetch();

if (!$curso) {
    redirect('/index.php', 'Curso não encontrado.', 'warning');
}

// Contar inscritos confirmados
$stmtI = $db->prepare("SELECT COUNT(*) FROM inscricoes WHERE curso_id = ? AND status_pagamento != 'cancelado'");
$stmtI->execute([$curso['id']]);
$inscritos = (int)$stmtI->fetchColumn();
$vagasRestantes = $curso['vagas'] !== null ? max(0, $curso['vagas'] - $inscritos) : null;

// Verificar disponibilidade do InfinitePay
$ipay = new InfinitePay();
$ipayDisponivel = getConfig('cartao_ativo') === '1' && $ipay->isConfigured();
$ipayTokenizacaoToken = null;
if ($ipayDisponivel) {
    InfinitePay::migrarColunas();
    $ipayTokenizacaoToken = $ipay->obterTokenTokenizacao();
    if (!$ipayTokenizacaoToken) {
        $ipayDisponivel = false;
    }
}
$parcelasDisponiveis = $ipayDisponivel ? $ipay->calcularParcelas((float)$curso['valor']) : [];

// Processar inscrição
$erros    = [];
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarCSRF($_POST['csrf_token'] ?? '')) {
        $erros[] = 'Token de segurança inválido. Recarregue a página.';
    } elseif (!$curso['inscricoes_abertas']) {
        $erros[] = 'As inscrições para este curso estão encerradas.';
    } elseif ($vagasRestantes !== null && $vagasRestantes <= 0) {
        $erros[] = 'Não há vagas disponíveis para este curso.';
    } else {
        $formData = [
            'nome_completo'   => sanitize($_POST['nome_completo'] ?? ''),
            'profissao'       => sanitize($_POST['profissao'] ?? ''),
            'email'           => filter_var(sanitize($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL),
            'endereco'        => sanitize($_POST['endereco'] ?? ''),
            'cpf'             => preg_replace('/\D/', '', sanitize($_POST['cpf'] ?? '')),
            'rg'              => sanitize($_POST['rg'] ?? ''),
            'formacao'        => sanitize($_POST['formacao'] ?? ''),
            'telefone'        => sanitize($_POST['telefone'] ?? ''),
            'forma_pagamento' => sanitize($_POST['forma_pagamento'] ?? ''),
        ];

        // Validações
        if (empty($formData['nome_completo'])) $erros[] = 'Nome completo é obrigatório.';
        if (empty($formData['email']) || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) $erros[] = 'E-mail inválido.';
        if (empty($formData['cpf'])) $erros[] = 'CPF é obrigatório.';
        elseif (!validarCPF($formData['cpf'])) $erros[] = 'CPF inválido.';
        if (empty($formData['telefone'])) $erros[] = 'Telefone é obrigatório.';
        if (empty($formData['forma_pagamento'])) $erros[] = 'Selecione uma forma de pagamento.';
        else {
            $formasValidas = ['pix', 'transferencia', 'deposito', 'cartao'];
            if (!in_array($formData['forma_pagamento'], $formasValidas, true)) $erros[] = 'Forma de pagamento inválida.';
        }

        // Verificar duplicata por CPF+curso
        if (empty($erros)) {
            $stmtDup = $db->prepare("SELECT id FROM inscricoes WHERE curso_id = ? AND cpf = ? LIMIT 1");
            $stmtDup->execute([$curso['id'], $formData['cpf']]);
            if ($stmtDup->fetch()) {
                $erros[] = 'Já existe uma inscrição com este CPF neste curso.';
            }
        }

        // Processamento de pagamento
        $statusPagamento     = 'pendente';
        $paymentId           = null;
        $paymentInstallments = 1;
        $erroCartao          = false;

        if (empty($erros) && $formData['forma_pagamento'] === 'cartao') {
            if (!$ipayDisponivel) {
                $erros[] = 'Pagamento por cartão não está disponível no momento.';
            } elseif ((float)$curso['valor'] <= 0) {
                // Curso gratuito — não processa cartão
                $statusPagamento = 'confirmado';
            } else {
                $ipToken     = $_POST['ip_token'] ?? '';
                $ipSessionId = $_POST['ip_session_id'] ?? '';
                $cvv         = preg_replace('/\D/', '', $_POST['cartao_cvv'] ?? '');
                $nomeCartao  = sanitize($_POST['cartao_nome_titular'] ?? $formData['nome_completo']);
                $parcelas    = max(1, min(12, (int)($_POST['parcelas'] ?? 1)));

                if (empty($ipToken)) {
                    $erros[] = 'Falha na tokenização do cartão. Tente novamente.';
                    $erroCartao = true;
                } elseif (empty($cvv)) {
                    $erros[] = 'CVV do cartão é obrigatório.';
                } else {
                    $resultado = $ipay->criarTransacao([
                        'valor'         => (float)$curso['valor'],
                        'parcelas'      => $parcelas,
                        'token'         => $ipToken,
                        'session_id'    => $ipSessionId,
                        'nome_cartao'   => $nomeCartao,
                        'cvv'           => $cvv,
                        'cpf'           => $formData['cpf'],
                        'nome'          => $formData['nome_completo'],
                        'email'         => $formData['email'],
                        'telefone'      => $formData['telefone'],
                        'endereco'      => $formData['endereco'],
                        'curso_id'      => $curso['id'],
                        'curso_nome'    => $curso['nome'],
                        'inscricao_ref' => 'C' . $curso['id'] . '-' . time(),
                    ]);

                    if ($resultado['sucesso']) {
                        $statusPagamento     = 'confirmado';
                        $paymentId           = $resultado['payment_id'];
                        $paymentInstallments = $resultado['parcelas'];
                    } else {
                        $erros[]    = $resultado['erro'];
                        $erroCartao = true;
                    }
                }
            }
        }

        if (empty($erros)) {
            $cols = 'curso_id, nome_completo, profissao, email, endereco, cpf, rg, formacao, telefone, forma_pagamento, status_pagamento';
            $vals = '?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?';
            $params = [
                $curso['id'], $formData['nome_completo'], $formData['profissao'],
                $formData['email'], $formData['endereco'], $formData['cpf'],
                $formData['rg'], $formData['formacao'], $formData['telefone'],
                $formData['forma_pagamento'], $statusPagamento,
            ];
            // Include payment columns if they exist (migrarColunas already ran for cartao)
            if ($paymentId !== null) {
                $cols   .= ', payment_id, payment_installments';
                $vals   .= ', ?, ?';
                $params[] = $paymentId;
                $params[] = $paymentInstallments;
            }
            $db->prepare("INSERT INTO inscricoes ($cols) VALUES ($vals)")->execute($params);

            if ($statusPagamento === 'confirmado') {
                redirect('/curso.php?slug=' . $slug . '&inscrito=1', 'Inscrição e pagamento confirmados com sucesso!', 'success');
            } else {
                redirect('/curso.php?slug=' . $slug . '&inscrito=1', 'Inscrição realizada com sucesso! Aguarde a confirmação do pagamento.', 'success');
            }
        }
    }
}

$inscrito = isset($_GET['inscrito']);
$pageTitle = $curso['nome'] . ' - ' . getConfig('site_nome', 'Sistema de Cursos');
$pageDesc  = $curso['descricao_curta'] ?: mb_substr(strip_tags($curso['descricao'] ?? ''), 0, 160);

// Opções de pagamento habilitadas
$pagamentos = [];
if (getConfig('pix_ativo') === '1') $pagamentos['pix'] = ['label' => 'PIX', 'icon' => 'bi-qr-code-scan', 'instrucoes' => getConfig('pix_instrucoes'), 'dados' => getConfig('pix_chave')];
if (getConfig('transferencia_ativo') === '1') $pagamentos['transferencia'] = ['label' => 'Transferência', 'icon' => 'bi-bank2', 'instrucoes' => getConfig('transferencia_instrucoes'), 'dados' => implode(' | ', array_filter([getConfig('transferencia_banco'), 'Ag: ' . getConfig('transferencia_agencia'), 'CC: ' . getConfig('transferencia_conta'), getConfig('transferencia_titular')]))];
if (getConfig('deposito_ativo') === '1') $pagamentos['deposito'] = ['label' => 'Depósito', 'icon' => 'bi-cash-stack', 'instrucoes' => getConfig('deposito_instrucoes'), 'dados' => implode(' | ', array_filter([getConfig('deposito_banco'), 'Ag: ' . getConfig('deposito_agencia'), 'CC: ' . getConfig('deposito_conta'), getConfig('deposito_titular')]))];
if (getConfig('cartao_ativo') === '1') {
    if ($ipayDisponivel) {
        $pagamentos['cartao'] = ['label' => 'Cartão de Crédito', 'icon' => 'bi-credit-card', 'instrucoes' => '', 'ipay' => true];
    } else {
        $pagamentos['cartao'] = ['label' => 'Cartão', 'icon' => 'bi-credit-card', 'instrucoes' => getConfig('cartao_instrucoes'), 'em_breve' => true];
    }
} else {
    $pagamentos['cartao_breve'] = ['label' => 'Cartão', 'icon' => 'bi-credit-card', 'em_breve' => true];
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Header do Curso -->
<section class="curso-header">
  <div class="container">
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb" style="background:none;padding:0;font-size:.85rem;">
        <li class="breadcrumb-item"><a href="<?= BASE_PATH ?>/index.php" style="color:rgba(255,255,255,.7);">Início</a></li>
        <li class="breadcrumb-item active" style="color:rgba(255,255,255,.5);"><?= h($curso['nome']) ?></li>
      </ol>
    </nav>
    <div class="row align-items-center">
      <div class="col-lg-8">
        <div class="mb-2">
          <span class="badge me-2" style="background:var(--secondary);font-size:.82rem;padding:5px 12px;">
            <?= $curso['tipo'] === 'online' ? '<i class="bi bi-wifi"></i> Online' : '<i class="bi bi-geo-alt"></i> Presencial' ?>
          </span>
          <?php if ($vagasRestantes !== null): ?>
          <span class="badge" style="background:rgba(255,255,255,.15);font-size:.82rem;padding:5px 12px;">
            <i class="bi bi-people"></i> <?= $vagasRestantes ?> vaga(s)
          </span>
          <?php endif; ?>
        </div>
        <h1><?= h($curso['nome']) ?></h1>
        <?php if ($curso['descricao_curta']): ?>
        <p class="lead" style="opacity:.85;"><?= h($curso['descricao_curta']) ?></p>
        <?php endif; ?>
        <div class="d-flex flex-wrap gap-3 mt-2" style="font-size:.9rem;opacity:.8;">
          <?php if ($curso['data_hora']): ?>
          <span><i class="bi bi-calendar3"></i> <?= formatarDataHora($curso['data_hora']) ?></span>
          <?php endif; ?>
          <?php if ($curso['carga_horaria']): ?>
          <span><i class="bi bi-clock"></i> <?= h($curso['carga_horaria']) ?>h</span>
          <?php endif; ?>
          <?php if ($curso['local'] && $curso['tipo'] === 'presencial'): ?>
          <span><i class="bi bi-geo-alt-fill"></i> <?= h($curso['local']) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
        <?php if ((float)$curso['valor'] > 0): ?>
        <div style="font-size:2.2rem;font-weight:bold;color:var(--secondary);">
          <?= formatarMoeda((float)$curso['valor']) ?>
        </div>
        <?php else: ?>
        <div style="font-size:2rem;font-weight:bold;color:#6fff6f;">
          <i class="bi bi-gift"></i> Gratuito
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- Sucesso banner -->
<?php if ($inscrito): ?>
<div class="container mt-4">
  <div class="alert alert-success d-flex align-items-center gap-3" style="border-radius:12px;border:none;background:linear-gradient(135deg,#d4edda,#c3e6cb);">
    <i class="bi bi-check-circle-fill" style="font-size:2rem;color:#28a745;"></i>
    <div>
      <strong>Inscrição realizada com sucesso!</strong><br>
      <span style="font-size:.9rem;">Sua inscrição foi recebida. Realize o pagamento conforme as instruções e aguarde a confirmação.</span>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Conteúdo Principal -->
<div class="container py-5">
  <div class="row g-4">
    <!-- Coluna Esquerda - Informações -->
    <div class="col-lg-8">
      <!-- Capa do Curso -->
      <?php $capa = urlImagem($curso['capa_foto'] ?? ''); ?>
      <?php if ($capa): ?>
      <div class="curso-capa mb-4">
        <img src="<?= h($capa) ?>" alt="<?= h($curso['nome']) ?>">
      </div>
      <?php endif; ?>

      <!-- Descrição -->
      <?php if ($curso['descricao']): ?>
      <div class="curso-info-card">
        <h5><i class="bi bi-info-circle-fill me-2"></i>Sobre o Curso</h5>
        <div style="font-size:.95rem;line-height:1.8;color:#555;">
          <?= nl2br(h($curso['descricao'])) ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Ministrador -->
      <?php if ($curso['ministrador_nome']): ?>
      <div class="curso-info-card mt-4">
        <h5><i class="bi bi-person-badge-fill me-2"></i>Instrutor</h5>
        <div class="d-flex align-items-start gap-4">
          <?php $fotoMin = urlImagem($curso['ministrador_foto'] ?? ''); ?>
          <?php if ($fotoMin): ?>
          <img src="<?= h($fotoMin) ?>" alt="<?= h($curso['ministrador_nome']) ?>"
               style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:3px solid var(--secondary);flex-shrink:0;">
          <?php else: ?>
          <div style="width:100px;height:100px;border-radius:50%;background:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="bi bi-person-fill" style="font-size:2.5rem;color:rgba(255,255,255,.5);"></i>
          </div>
          <?php endif; ?>
          <div>
            <div class="ministrador-nome mb-1"><?= h($curso['ministrador_nome']) ?></div>
            <?php if ($curso['ministrador_bio']): ?>
            <p style="font-size:.9rem;color:#555;margin:0;"><?= nl2br(h($curso['ministrador_bio'])) ?></p>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Formulário de Inscrição -->
      <?php if ($curso['inscricoes_abertas'] && ($vagasRestantes === null || $vagasRestantes > 0)): ?>
      <section class="inscricao-section mt-4" id="inscricao" style="background:none;padding:0;">
        <div class="inscricao-card">
          <h3><i class="bi bi-person-plus-fill me-2"></i>Formulário de Inscrição</h3>

          <?php if (!empty($erros)): ?>
          <div class="alert alert-danger">
            <strong><i class="bi bi-exclamation-triangle"></i> Verifique os erros:</strong>
            <ul class="mb-0 mt-1">
              <?php foreach ($erros as $erro): ?>
              <li><?= h($erro) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <?php endif; ?>

          <form id="form-inscricao" method="POST" action="#inscricao" novalidate>
            <input type="hidden" name="csrf_token" value="<?= gerarCSRF() ?>">
            <input type="hidden" name="forma_pagamento" id="forma_pagamento" value="<?= h($formData['forma_pagamento'] ?? '') ?>">
            <?php if ($ipayDisponivel): ?>
            <input type="hidden" name="ip_token" id="ip_token" value="">
            <input type="hidden" name="ip_session_id" id="ip_session_id" value="">
            <input type="hidden" name="cartao_cvv" id="cartao_cvv_hidden" value="">
            <input type="hidden" name="cartao_nome_titular" id="cartao_nome_titular_hidden" value="">
            <input type="hidden" name="parcelas" id="parcelas_hidden" value="1">
            <?php endif; ?>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label" for="nome_completo">Nome Completo <span style="color:var(--accent)">*</span></label>
                <input type="text" class="form-control" id="nome_completo" name="nome_completo"
                       value="<?= h($formData['nome_completo'] ?? '') ?>" required maxlength="200"
                       placeholder="Seu nome completo">
              </div>
              <div class="col-md-6">
                <label class="form-label" for="profissao">Profissão</label>
                <input type="text" class="form-control" id="profissao" name="profissao"
                       value="<?= h($formData['profissao'] ?? '') ?>" maxlength="150"
                       placeholder="Sua profissão">
              </div>
              <div class="col-md-6">
                <label class="form-label" for="email">E-mail <span style="color:var(--accent)">*</span></label>
                <input type="email" class="form-control" id="email" name="email"
                       value="<?= h($formData['email'] ?? '') ?>" required maxlength="150"
                       placeholder="seu@email.com">
              </div>
              <div class="col-md-6">
                <label class="form-label" for="telefone">Telefone <span style="color:var(--accent)">*</span></label>
                <input type="tel" class="form-control" id="telefone" name="telefone"
                       value="<?= h($formData['telefone'] ?? '') ?>" required maxlength="30"
                       placeholder="(00) 00000-0000">
              </div>
              <div class="col-md-6">
                <label class="form-label" for="cpf">CPF <span style="color:var(--accent)">*</span></label>
                <input type="text" class="form-control" id="cpf" name="cpf"
                       value="<?= h($formData['cpf'] ?? '') ?>" required maxlength="14"
                       placeholder="000.000.000-00">
              </div>
              <div class="col-md-6">
                <label class="form-label" for="rg">RG</label>
                <input type="text" class="form-control" id="rg" name="rg"
                       value="<?= h($formData['rg'] ?? '') ?>" maxlength="30"
                       placeholder="Seu RG">
              </div>
              <div class="col-12">
                <label class="form-label" for="endereco">Endereço Residencial</label>
                <input type="text" class="form-control" id="endereco" name="endereco"
                       value="<?= h($formData['endereco'] ?? '') ?>" maxlength="300"
                       placeholder="Rua, número, bairro, cidade, estado">
              </div>
              <div class="col-12">
                <label class="form-label" for="formacao">Formação Profissional</label>
                <input type="text" class="form-control" id="formacao" name="formacao"
                       value="<?= h($formData['formacao'] ?? '') ?>" maxlength="200"
                       placeholder="Sua formação acadêmica ou profissional">
              </div>
            </div>

            <!-- Pagamento -->
            <div class="pagamento-section">
              <h4><i class="bi bi-credit-card-2-front me-2"></i>Forma de Pagamento</h4>
              <p style="font-size:.88rem;color:#666;">Selecione como deseja realizar o pagamento:</p>

              <div class="pagamento-opcoes">
                <?php foreach ($pagamentos as $forma => $p): ?>
                <?php
                $emBreve = $p['em_breve'] ?? false;
                $formaKey = $forma === 'cartao_breve' ? 'cartao' : $forma;
                ?>
                <div class="pagamento-opcao <?= $emBreve ? 'em-breve' : '' ?> <?= (($formData['forma_pagamento'] ?? '') === $formaKey) ? 'selecionado' : '' ?>"
                     data-forma="<?= h($formaKey) ?>">
                  <i class="bi <?= h($p['icon']) ?>"></i>
                  <span><?= h($p['label']) ?></span>
                  <?php if ($emBreve): ?>
                  <span class="badge-breve">Em breve</span>
                  <?php endif; ?>
                </div>
                <?php endforeach; ?>
              </div>

              <?php foreach ($pagamentos as $forma => $p): ?>
              <?php
              $emBreve  = $p['em_breve'] ?? false;
              $isIpay   = $p['ipay'] ?? false;
              $formaKey = $forma === 'cartao_breve' ? 'cartao' : $forma;
              $visivel  = ($formData['forma_pagamento'] ?? '') === $formaKey;
              ?>
              <?php if (!$emBreve): ?>
              <div class="pagamento-instrucoes <?= $visivel ? 'visivel' : '' ?>" id="instrucoes-<?= h($formaKey) ?>">
                <?php if ($isIpay): ?>
                <!-- Formulário de cartão InfinitePay -->
                <?php if ((float)$curso['valor'] <= 0): ?>
                <div class="alert alert-success" style="border-radius:8px;font-size:.9rem;margin-top:8px;">
                  <i class="bi bi-gift-fill me-2"></i> Este curso é gratuito. Confirme sua inscrição.
                </div>
                <?php else: ?>
                <div id="cartao-form" style="background:#f8f9fa;border-radius:10px;padding:20px;margin-top:8px;">
                  <div style="font-weight:700;color:var(--primary);margin-bottom:14px;font-size:.95rem;">
                    <i class="bi bi-lock-fill me-1"></i> Dados do Cartão de Crédito
                  </div>
                  <?php if ($ipay->isConfigured() && getConfig('infinitepay_ambiente') === 'sandbox'): ?>
                  <div class="alert alert-warning mb-3" style="font-size:.82rem;border-radius:8px;">
                    <i class="bi bi-exclamation-triangle me-1"></i> Modo de testes ativo. Nenhuma cobrança real será efetuada.
                  </div>
                  <?php endif; ?>
                  <div class="row g-2">
                    <div class="col-12">
                      <label class="form-label" style="font-size:.83rem;font-weight:600;color:#444;">Número do Cartão</label>
                      <input type="text" class="form-control" id="cartao_numero" data-ip="card_number"
                             maxlength="19" placeholder="0000 0000 0000 0000" autocomplete="cc-number"
                             style="letter-spacing:.08em;font-size:1rem;">
                    </div>
                    <div class="col-12">
                      <label class="form-label" style="font-size:.83rem;font-weight:600;color:#444;">Nome Impresso no Cartão</label>
                      <input type="text" class="form-control" id="cartao_nome" data-ip="card_holder_name"
                             maxlength="50" placeholder="NOME SOBRENOME" autocomplete="cc-name"
                             style="text-transform:uppercase;">
                    </div>
                    <div class="col-4">
                      <label class="form-label" style="font-size:.83rem;font-weight:600;color:#444;">Mês</label>
                      <input type="text" class="form-control" id="cartao_mes" data-ip="expiration_month"
                             maxlength="2" placeholder="MM" autocomplete="cc-exp-month">
                    </div>
                    <div class="col-4">
                      <label class="form-label" style="font-size:.83rem;font-weight:600;color:#444;">Ano</label>
                      <input type="text" class="form-control" id="cartao_ano" data-ip="expiration_year"
                             maxlength="2" placeholder="AA" autocomplete="cc-exp-year">
                    </div>
                    <div class="col-4">
                      <label class="form-label" style="font-size:.83rem;font-weight:600;color:#444;">CVV</label>
                      <input type="text" class="form-control" id="cartao_cvv" data-ip="cvv"
                             maxlength="4" placeholder="123" autocomplete="cc-csc">
                    </div>
                    <?php if (count($parcelasDisponiveis) > 1): ?>
                    <div class="col-12 mt-1">
                      <label class="form-label" style="font-size:.83rem;font-weight:600;color:#444;">Parcelamento</label>
                      <select class="form-select" id="parcelas_select">
                        <?php foreach ($parcelasDisponiveis as $parc): ?>
                        <option value="<?= $parc['n'] ?>">
                          <?= $parc['n'] ?>x de R$ <?= number_format($parc['por_parcela'], 2, ',', '.') ?>
                          <?= $parc['tem_juros'] ? ' (total R$ ' . number_format($parc['total'], 2, ',', '.') . ')' : ' (sem juros)' ?>
                        </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <?php endif; ?>
                  </div>
                  <div id="cartao-erro" class="alert alert-danger mt-3 d-none" style="border-radius:8px;font-size:.87rem;"></div>
                  <div id="cartao-loading" class="mt-3 d-none text-center" style="color:var(--primary);font-size:.9rem;">
                    <div class="spinner-border spinner-border-sm me-2"></div> Processando pagamento...
                  </div>
                  <div class="mt-3" style="font-size:.75rem;color:#999;">
                    <i class="bi bi-shield-lock-fill me-1"></i> Dados criptografados. Nenhum número de cartão é armazenado em nossos servidores.
                  </div>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <strong><i class="bi bi-info-circle"></i> Instruções de Pagamento - <?= h($p['label']) ?></strong>
                <?php if (!empty($p['dados'])): ?>
                <div class="mb-2" style="font-family:monospace;background:#fff;padding:8px;border-radius:6px;font-size:.88rem;">
                  <?= h($p['dados']) ?>
                </div>
                <?php endif; ?>
                <?= nl2br(h($p['instrucoes'])) ?>
                <?php endif; ?>
              </div>
              <?php endif; ?>
              <?php endforeach; ?>
            </div>

            <button type="submit" class="btn-submit">
              <i class="bi bi-check2-circle me-2"></i>Confirmar Inscrição
            </button>
            <p class="text-center mt-2" style="font-size:.78rem;color:#999;">
              <i class="bi bi-shield-lock"></i> Seus dados estão protegidos. Ao se inscrever, você confirma que as informações são verdadeiras.
            </p>
          </form>
        </div>
      </section>
      <?php elseif (!$curso['inscricoes_abertas']): ?>
      <div class="alert alert-warning mt-4">
        <i class="bi bi-x-circle me-2"></i>As inscrições para este curso estão encerradas.
      </div>
      <?php else: ?>
      <div class="alert alert-danger mt-4">
        <i class="bi bi-people-fill me-2"></i>Não há vagas disponíveis para este curso.
      </div>
      <?php endif; ?>
    </div>

    <!-- Coluna Direita - Info Card -->
    <div class="col-lg-4">
      <div class="sticky-top" style="top:80px;">
        <div class="curso-info-card">
          <h5><i class="bi bi-info-circle-fill me-2"></i>Informações do Curso</h5>
          <?php if ($curso['data_hora']): ?>
          <div class="info-item">
            <i class="bi bi-calendar-event-fill"></i>
            <div><strong>Data e Hora</strong><br><?= formatarDataHora($curso['data_hora']) ?></div>
          </div>
          <?php endif; ?>
          <?php if ($curso['data_fim']): ?>
          <div class="info-item">
            <i class="bi bi-calendar-check-fill"></i>
            <div><strong>Encerramento</strong><br><?= formatarDataHora($curso['data_fim']) ?></div>
          </div>
          <?php endif; ?>
          <div class="info-item">
            <i class="bi bi-display"></i>
            <div><strong>Modalidade</strong><br><?= formatarTipoCurso($curso['tipo']) ?></div>
          </div>
          <?php if ($curso['local'] && $curso['tipo'] === 'presencial'): ?>
          <div class="info-item">
            <i class="bi bi-geo-alt-fill"></i>
            <div><strong>Local</strong><br><?= h($curso['local']) ?></div>
          </div>
          <?php endif; ?>
          <?php if ($curso['carga_horaria']): ?>
          <div class="info-item">
            <i class="bi bi-clock-fill"></i>
            <div><strong>Carga Horária</strong><br><?= h($curso['carga_horaria']) ?> horas</div>
          </div>
          <?php endif; ?>
          <?php if ($curso['vagas'] !== null): ?>
          <div class="info-item">
            <i class="bi bi-people-fill"></i>
            <div>
              <strong>Vagas</strong><br>
              <?= $vagasRestantes ?> restante(s) de <?= $curso['vagas'] ?>
              <div class="mt-1">
                <div class="progress" style="height:6px;border-radius:3px;">
                  <div class="progress-bar" style="width:<?= $curso['vagas'] > 0 ? min(100, round($inscritos / $curso['vagas'] * 100)) : 0 ?>%;background:var(--secondary);"></div>
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>
          <div class="info-item">
            <i class="bi bi-currency-dollar"></i>
            <div>
              <strong>Investimento</strong><br>
              <?php if ((float)$curso['valor'] > 0): ?>
              <span style="color:var(--accent);font-weight:bold;font-size:1.2rem;"><?= formatarMoeda((float)$curso['valor']) ?></span>
              <?php else: ?>
              <span style="color:#28a745;font-weight:bold;">Gratuito</span>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($curso['inscricoes_abertas'] && ($vagasRestantes === null || $vagasRestantes > 0)): ?>
          <a href="#inscricao" class="btn btn-primary w-100 mt-3" style="background:var(--primary);border:none;border-radius:8px;font-weight:700;padding:12px;">
            <i class="bi bi-pencil-square me-1"></i>Fazer Inscrição
          </a>
          <?php endif; ?>
        </div>

        <!-- Inscritos Counter -->
        <div class="curso-info-card mt-3 text-center">
          <div style="font-size:2rem;font-weight:bold;color:var(--primary);"><?= $inscritos ?></div>
          <div style="font-size:.85rem;color:#666;">pessoa(s) já inscrita(s)</div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($ipayDisponivel && (float)$curso['valor'] > 0): ?>
<script>
(function() {
  var IPAY_ACCESS_TOKEN = <?= json_encode($ipayTokenizacaoToken) ?>;
  var ipayInstance = null;
  var ipayReady    = false;
  var submitPending = false;

  function initIpay() {
    if (!window.IPay || ipayReady) return;
    ipayInstance = new IPay({ access_token: IPAY_ACCESS_TOKEN });
    ipayInstance.listeners = {
      'result:success': function() {
        var tokenField = document.querySelector("input[name='ip[token]']");
        var sessField  = document.querySelector("input[name='ip[session_id]']");
        if (tokenField) document.getElementById('ip_token').value = tokenField.value;
        if (sessField)  document.getElementById('ip_session_id').value = sessField.value;
        if (submitPending && document.getElementById('ip_token').value) {
          submitPending = false;
          document.getElementById('form-inscricao').submit();
        }
      },
      'result:error': function(errors) {
        submitPending = false;
        mostrarErroCartao('Falha ao validar os dados do cartão. Verifique e tente novamente.');
        ocultarLoading();
      }
    };
    ipayReady = true;
  }

  function mostrarErroCartao(msg) {
    var el = document.getElementById('cartao-erro');
    if (el) { el.textContent = msg; el.classList.remove('d-none'); }
  }

  function ocultarErroCartao() {
    var el = document.getElementById('cartao-erro');
    if (el) el.classList.add('d-none');
  }

  function mostrarLoading() {
    var el = document.getElementById('cartao-loading');
    if (el) el.classList.remove('d-none');
  }

  function ocultarLoading() {
    var el = document.getElementById('cartao-loading');
    if (el) el.classList.add('d-none');
  }

  function validarCamposCartao() {
    var num  = (document.getElementById('cartao_numero')?.value || '').replace(/\D/g,'');
    var nome = (document.getElementById('cartao_nome')?.value || '').trim();
    var mes  = (document.getElementById('cartao_mes')?.value || '').trim();
    var ano  = (document.getElementById('cartao_ano')?.value || '').trim();
    var cvv  = (document.getElementById('cartao_cvv')?.value || '').replace(/\D/g,'');
    if (num.length < 13)  { mostrarErroCartao('Número do cartão inválido.'); return false; }
    if (!nome)            { mostrarErroCartao('Informe o nome impresso no cartão.'); return false; }
    if (!mes || mes < 1 || mes > 12) { mostrarErroCartao('Mês de validade inválido.'); return false; }
    if (!ano || ano.length < 2)      { mostrarErroCartao('Ano de validade inválido.'); return false; }
    if (cvv.length < 3)  { mostrarErroCartao('CVV inválido.'); return false; }
    return true;
  }

  document.getElementById('form-inscricao').addEventListener('submit', function(e) {
    var forma = document.getElementById('forma_pagamento').value;
    if (forma !== 'cartao') return; // let non-card submit proceed normally

    e.preventDefault();
    ocultarErroCartao();

    if (!validarCamposCartao()) return;

    // Copy CVV and name to hidden fields
    var cvv  = (document.getElementById('cartao_cvv')?.value || '').replace(/\D/g,'');
    var nome = (document.getElementById('cartao_nome')?.value || '').trim().toUpperCase();
    var parc = document.getElementById('parcelas_select')?.value || '1';
    document.getElementById('cartao_cvv_hidden').value = cvv;
    document.getElementById('cartao_nome_titular_hidden').value = nome;
    document.getElementById('parcelas_hidden').value = parc;

    mostrarLoading();

    if (!ipayReady) {
      mostrarErroCartao('Gateway de pagamento não inicializado. Recarregue a página.');
      ocultarLoading();
      return;
    }

    submitPending = true;
    var form = document.getElementById('form-inscricao');
    ipayInstance.generate(form);
  });

  // Format card number input
  var numInput = document.getElementById('cartao_numero');
  if (numInput) {
    numInput.addEventListener('input', function() {
      var v = this.value.replace(/\D/g,'').substring(0,16);
      this.value = v.replace(/(.{4})/g,'$1 ').trim();
    });
  }

  // Load ipay.js script
  var script = document.createElement('script');
  script.src = <?= json_encode($ipay->getIpayJsUrl()) ?>;
  script.onload = initIpay;
  document.head.appendChild(script);
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
