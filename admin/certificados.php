<?php
$pageTitle = 'Certificados';
require_once __DIR__ . '/includes/header.php';

$db = getDB();

// Processar emissão
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarCSRF($_POST['csrf_token'] ?? '')) {
        redirect('/admin/certificados.php', 'Token inválido.', 'danger');
    }
    $postAcao = sanitize($_POST['acao'] ?? '');
    $inscricaoId = (int)($_POST['inscricao_id'] ?? 0);

    if ($postAcao === 'emitir' && $inscricaoId > 0) {
        $sIns = $db->prepare("SELECT i.*, c.id AS curso_id FROM inscricoes i JOIN cursos c ON c.id = i.curso_id WHERE i.id = ? AND i.status_pagamento = 'confirmado'");
        $sIns->execute([$inscricaoId]);
        $ins = $sIns->fetch();
        if (!$ins) {
            redirect('/admin/certificados.php', 'Inscrição não encontrada ou pagamento não confirmado.', 'danger');
        }

        // Inserção atômica: UNIQUE KEY em inscricao_id previne duplicatas por race condition
        try {
            $codigo = gerarCodigoCertificado();
            $db->prepare("INSERT INTO certificados (inscricao_id, curso_id, codigo_unico, nome_completo) VALUES (?,?,?,?)")
                ->execute([$inscricaoId, $ins['curso_id'], $codigo, $ins['nome_completo']]);
            $db->prepare("UPDATE inscricoes SET certificado_emitido = 1 WHERE id = ?")->execute([$inscricaoId]);
            redirect('/admin/certificados.php', 'Certificado emitido! Código: ' . $codigo, 'success');
        } catch (PDOException $e) {
            // Violação de UNIQUE = certificado já existe
            redirect('/admin/certificados.php', 'Certificado já emitido para esta inscrição.', 'warning');
        }
    }

    if ($postAcao === 'invalidar') {
        requireAdmin();
        $certId = (int)$_POST['cert_id'];
        $db->prepare("UPDATE certificados SET valido = 0 WHERE id = ?")->execute([$certId]);
        redirect('/admin/certificados.php', 'Certificado invalidado.', 'warning');
    }

    if ($postAcao === 'revalidar') {
        requireAdmin();
        $certId = (int)$_POST['cert_id'];
        $db->prepare("UPDATE certificados SET valido = 1 WHERE id = ?")->execute([$certId]);
        redirect('/admin/certificados.php', 'Certificado revalidado.', 'success');
    }

    if ($postAcao === 'emitir_lote') {
        $cursoId = (int)($_POST['curso_id'] ?? 0);
        $inscritos = $db->prepare("
            SELECT i.id FROM inscricoes i
            LEFT JOIN certificados cert ON cert.inscricao_id = i.id
            WHERE i.curso_id = ? AND i.status_pagamento = 'confirmado' AND cert.id IS NULL
        ");
        $inscritos->execute([$cursoId]);
        $lista = $inscritos->fetchAll();
        $count = 0;
        foreach ($lista as $ins) {
            $sIns = $db->prepare("SELECT * FROM inscricoes WHERE id = ?");
            $sIns->execute([$ins['id']]);
            $inscricao = $sIns->fetch();
            $codigo = gerarCodigoCertificado();
            $db->prepare("INSERT INTO certificados (inscricao_id, curso_id, codigo_unico, nome_completo) VALUES (?,?,?,?)")
                ->execute([$inscricao['id'], $cursoId, $codigo, $inscricao['nome_completo']]);
            $db->prepare("UPDATE inscricoes SET certificado_emitido = 1 WHERE id = ?")->execute([$inscricao['id']]);
            $count++;
        }
        redirect('/admin/certificados.php', "$count certificado(s) emitido(s) em lote.", 'success');
    }
}

// Filtros
$cursoId  = (int)($_GET['curso_id'] ?? 0);
$searchQ  = sanitize($_GET['q'] ?? '');
$filtValido = $_GET['valido'] ?? '';
$aba      = sanitize($_GET['aba'] ?? 'emitidos');

// Cursos
$cursosList = $db->query("SELECT id, nome FROM cursos ORDER BY nome ASC")->fetchAll();

// Inscrições sem certificado (confirmadas)
$wherePend = ["i.status_pagamento = 'confirmado'", "cert.id IS NULL"];
$paramsPend = [];
if ($cursoId > 0) { $wherePend[] = "i.curso_id = ?"; $paramsPend[] = $cursoId; }
if ($searchQ) { $wherePend[] = "(i.nome_completo LIKE ? OR i.email LIKE ?)"; $paramsPend[] = "%$searchQ%"; $paramsPend[] = "%$searchQ%"; }
$stmtPend = $db->prepare("
    SELECT i.*, c.nome AS curso_nome
    FROM inscricoes i
    JOIN cursos c ON c.id = i.curso_id
    LEFT JOIN certificados cert ON cert.inscricao_id = i.id
    WHERE " . implode(' AND ', $wherePend) . "
    ORDER BY i.criado_em DESC
");
$stmtPend->execute($paramsPend);
$pendentes = $stmtPend->fetchAll();

// Certificados emitidos
$whereEmit = [];
$paramsEmit = [];
if ($cursoId > 0) { $whereEmit[] = "cert.curso_id = ?"; $paramsEmit[] = $cursoId; }
if ($searchQ) { $whereEmit[] = "(cert.nome_completo LIKE ? OR cert.codigo_unico LIKE ?)"; $paramsEmit[] = "%$searchQ%"; $paramsEmit[] = "%$searchQ%"; }
if ($filtValido !== '') { $whereEmit[] = "cert.valido = ?"; $paramsEmit[] = (int)$filtValido; }
$whereEmitSQL = $whereEmit ? 'WHERE ' . implode(' AND ', $whereEmit) : '';
$stmtEmit = $db->prepare("
    SELECT cert.*, c.nome AS curso_nome
    FROM certificados cert
    JOIN cursos c ON c.id = cert.curso_id
    $whereEmitSQL
    ORDER BY cert.emitido_em DESC
");
$stmtEmit->execute($paramsEmit);
$emitidos = $stmtEmit->fetchAll();
?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4" style="border-bottom:2px solid var(--gray-mid);">
  <li class="nav-item">
    <a class="nav-link <?= $aba === 'emitidos' ? 'active fw-bold' : '' ?> " href="?aba=emitidos<?= $cursoId ? '&curso_id='.$cursoId : '' ?>"
       style="<?= $aba === 'emitidos' ? 'color:var(--primary);border-bottom:3px solid var(--primary);' : 'color:#888;' ?>">
      <i class="bi bi-patch-check-fill me-1"></i>Emitidos (<?= count($emitidos) ?>)
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $aba === 'pendentes' ? 'active fw-bold' : '' ?>"
       href="?aba=pendentes<?= $cursoId ? '&curso_id='.$cursoId : '' ?>"
       style="<?= $aba === 'pendentes' ? 'color:var(--primary);border-bottom:3px solid var(--primary);' : 'color:#888;' ?>">
      <i class="bi bi-hourglass-split me-1"></i>Pendentes de Emissão (<?= count($pendentes) ?>)
    </a>
  </li>
</ul>

<!-- Filtros -->
<form method="GET" class="admin-filters">
  <input type="hidden" name="aba" value="<?= h($aba) ?>">
  <input type="text" class="form-control" name="q" placeholder="Nome ou código..." value="<?= h($searchQ) ?>" style="max-width:220px;">
  <select class="form-select auto-filter" name="curso_id" style="max-width:220px;">
    <option value="">Todos os cursos</option>
    <?php foreach ($cursosList as $c): ?>
    <option value="<?= $c['id'] ?>" <?= $cursoId === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['nome']) ?></option>
    <?php endforeach; ?>
  </select>
  <?php if ($aba === 'emitidos'): ?>
  <select class="form-select auto-filter" name="valido" style="max-width:150px;">
    <option value="">Todos</option>
    <option value="1" <?= $filtValido === '1' ? 'selected' : '' ?>>Válidos</option>
    <option value="0" <?= $filtValido === '0' ? 'selected' : '' ?>>Invalidados</option>
  </select>
  <?php endif; ?>
  <button type="submit" class="btn" style="background:var(--primary);color:#fff;border-radius:8px;font-weight:700;"><i class="bi bi-search"></i></button>
  <?php if ($searchQ || $cursoId): ?>
  <a href="<?= BASE_PATH ?>/admin/certificados.php?aba=<?= h($aba) ?>" class="btn btn-outline-secondary" style="border-radius:8px;">Limpar</a>
  <?php endif; ?>
  <?php if ($aba === 'emitidos'): ?>
  <a href="<?= BASE_PATH ?>/api/exportar.php?tipo=certificados<?= $cursoId ? '&curso_id='.$cursoId : '' ?>" class="btn ms-auto"
     style="background:var(--secondary);color:#fff;border-radius:8px;font-weight:700;">
    <i class="bi bi-file-earmark-excel"></i> Exportar Excel
  </a>
  <?php endif; ?>
</form>

<?php if ($aba === 'pendentes'): ?>

<!-- Emissão em lote -->
<?php if (!empty($pendentes) && $cursoId > 0): ?>
<div class="admin-card mb-4">
  <div class="admin-card-body d-flex align-items-center gap-3 flex-wrap">
    <div>
      <strong style="color:var(--primary);"><?= count($pendentes) ?> inscrito(s) com pagamento confirmado aguardando certificado</strong>
      <div style="font-size:.82rem;color:#888;">Curso selecionado: <?php
        $cursoNomeExib = '';
        foreach ($cursosList as $cl) { if ((int)$cl['id'] === $cursoId) { $cursoNomeExib = $cl['nome']; break; } }
        echo h($cursoNomeExib);
      ?></div>
    </div>
    <form method="POST" class="ms-auto" onsubmit="return confirm('Emitir certificados para todos os <?= count($pendentes) ?> inscrito(s)?')">
      <input type="hidden" name="csrf_token" value="<?= gerarCSRF() ?>">
      <input type="hidden" name="acao" value="emitir_lote">
      <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
      <button type="submit" class="btn" style="background:var(--secondary);color:#fff;border-radius:8px;font-weight:700;padding:10px 20px;">
        <i class="bi bi-patch-check me-1"></i>Emitir Todos em Lote
      </button>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="admin-card">
  <div class="admin-card-header"><h5><i class="bi bi-hourglass-split"></i> Aguardando Emissão</h5></div>
  <div style="overflow-x:auto;">
    <table class="admin-table">
      <thead>
        <tr><th>Nome</th><th>Curso</th><th>E-mail</th><th>Data Inscrição</th><th>Ação</th></tr>
      </thead>
      <tbody>
        <?php if (empty($pendentes)): ?>
        <tr><td colspan="5" class="text-center py-4 text-muted">
          <?= $cursoId ? 'Nenhum inscrito pendente neste curso' : 'Selecione um curso ou todos já possuem certificado' ?>
        </td></tr>
        <?php endif; ?>
        <?php foreach ($pendentes as $p): ?>
        <tr>
          <td style="font-weight:700;font-size:.88rem;color:var(--primary);"><?= h($p['nome_completo']) ?></td>
          <td style="font-size:.83rem;"><?= h($p['curso_nome']) ?></td>
          <td style="font-size:.8rem;"><?= h($p['email']) ?></td>
          <td style="font-size:.8rem;color:#888;"><?= formatarData($p['criado_em'], 'd/m/Y') ?></td>
          <td>
            <form method="POST" style="display:inline;" onsubmit="return confirm(<?= h(json_encode('Emitir certificado para ' . $p['nome_completo'] . '?')) ?>)">
              <input type="hidden" name="csrf_token" value="<?= gerarCSRF() ?>">
              <input type="hidden" name="acao" value="emitir">
              <input type="hidden" name="inscricao_id" value="<?= $p['id'] ?>">
              <button type="submit" class="btn-action success"><i class="bi bi-patch-check"></i> Emitir</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php else: // emitidos ?>

<div class="admin-card">
  <div class="admin-card-header"><h5><i class="bi bi-patch-check-fill"></i> Certificados Emitidos (<?= count($emitidos) ?>)</h5></div>
  <div style="overflow-x:auto;">
    <table class="admin-table">
      <thead>
        <tr><th>Nome</th><th>Curso</th><th>Código</th><th>Emitido em</th><th>Status</th><th>Ações</th></tr>
      </thead>
      <tbody>
        <?php if (empty($emitidos)): ?>
        <tr><td colspan="6" class="text-center py-4 text-muted">Nenhum certificado emitido</td></tr>
        <?php endif; ?>
        <?php foreach ($emitidos as $cert): ?>
        <tr>
          <td style="font-weight:700;font-size:.88rem;color:var(--primary);"><?= h($cert['nome_completo']) ?></td>
          <td style="font-size:.83rem;"><?= h($cert['curso_nome']) ?></td>
          <td><code style="font-size:.78rem;background:var(--gray-light);padding:3px 7px;border-radius:4px;"><?= h($cert['codigo_unico']) ?></code></td>
          <td style="font-size:.8rem;color:#888;"><?= formatarData($cert['emitido_em'], 'd/m/Y') ?></td>
          <td>
            <span class="badge <?= $cert['valido'] ? 'badge-confirmado' : 'badge-cancelado' ?>">
              <?= $cert['valido'] ? 'Válido' : 'Invalidado' ?>
            </span>
          </td>
          <td>
            <div class="actions">
              <a href="<?= BASE_PATH ?>/certificado-visualizar.php?codigo=<?= h($cert['codigo_unico']) ?>" target="_blank" class="btn-action view">
                <i class="bi bi-eye"></i>
              </a>
              <?php if (isAdmin()): ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= gerarCSRF() ?>">
                <input type="hidden" name="acao" value="<?= $cert['valido'] ? 'invalidar' : 'revalidar' ?>">
                <input type="hidden" name="cert_id" value="<?= $cert['id'] ?>">
                <button type="submit" class="btn-action <?= $cert['valido'] ? 'delete' : 'success' ?>">
                  <i class="bi <?= $cert['valido'] ? 'bi-x-circle' : 'bi-check-circle' ?>"></i>
                  <?= $cert['valido'] ? 'Invalidar' : 'Revalidar' ?>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
