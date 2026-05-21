<?php
$pageTitle = 'Gerenciar Inscrições';
require_once __DIR__ . '/includes/header.php';

$db = getDB();

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarCSRF($_POST['csrf_token'] ?? '')) {
        redirect('/admin/inscricoes.php', 'Token inválido.', 'danger');
    }
    $postAcao = sanitize($_POST['acao'] ?? '');
    $id = (int)($_POST['id'] ?? 0);

    if ($postAcao === 'atualizar_status' && $id > 0) {
        $status = sanitize($_POST['status'] ?? '');
        if (in_array($status, ['pendente', 'confirmado', 'cancelado'], true)) {
            $obs = sanitize($_POST['observacoes'] ?? '');
            $db->prepare("UPDATE inscricoes SET status_pagamento = ?, observacoes = ? WHERE id = ?")->execute([$status, $obs ?: null, $id]);
            redirect('/admin/inscricoes.php', 'Status atualizado com sucesso.', 'success');
        }
    }

    if ($postAcao === 'deletar' && $id > 0) {
        requireAdmin();
        $db->prepare("DELETE FROM inscricoes WHERE id = ?")->execute([$id]);
        redirect('/admin/inscricoes.php', 'Inscrição removida.', 'success');
    }
}

// Filtros
$cursoId    = (int)($_GET['curso_id'] ?? 0);
$status     = sanitize($_GET['status'] ?? '');
$searchQ    = sanitize($_GET['q'] ?? '');
$formaPag   = sanitize($_GET['forma'] ?? '');

$where  = [];
$params = [];

if ($cursoId > 0) { $where[] = "i.curso_id = ?"; $params[] = $cursoId; }
if ($status && in_array($status, ['pendente','confirmado','cancelado'])) { $where[] = "i.status_pagamento = ?"; $params[] = $status; }
if ($searchQ) { $where[] = "(i.nome_completo LIKE ? OR i.email LIKE ? OR i.cpf LIKE ?)"; $params[] = "%$searchQ%"; $params[] = "%$searchQ%"; $params[] = "%$searchQ%"; }
if ($formaPag) { $where[] = "i.forma_pagamento = ?"; $params[] = $formaPag; }

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmtAll = $db->prepare("
    SELECT i.*, c.nome AS curso_nome, c.valor AS curso_valor
    FROM inscricoes i
    JOIN cursos c ON c.id = i.curso_id
    $whereSQL
    ORDER BY i.criado_em DESC
");
$stmtAll->execute($params);
$inscricoes = $stmtAll->fetchAll();

// Cursos para filtro
$cursosList = $db->query("SELECT id, nome FROM cursos ORDER BY nome ASC")->fetchAll();

// Detalhes de inscrição para modal
$detalhesId = (int)($_GET['ver'] ?? 0);
$detalhe    = null;
if ($detalhesId > 0) {
    $s = $db->prepare("SELECT i.*, c.nome AS curso_nome, c.valor AS curso_valor FROM inscricoes i JOIN cursos c ON c.id = i.curso_id WHERE i.id = ?");
    $s->execute([$detalhesId]);
    $detalhe = $s->fetch();
}
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div></div>
  <a href="/api/exportar.php?tipo=inscricoes<?= $cursoId ? '&curso_id='.$cursoId : '' ?><?= $status ? '&status='.$status : '' ?>"
     class="topbar-btn" style="background:var(--secondary);color:#fff;font-weight:700;">
    <i class="bi bi-file-earmark-excel"></i> Exportar Excel
  </a>
</div>

<!-- Filtros -->
<form method="GET" class="admin-filters">
  <input type="text" class="form-control" name="q" placeholder="Nome, e-mail, CPF..." value="<?= h($searchQ) ?>" style="max-width:220px;">
  <select class="form-select auto-filter" name="curso_id" style="max-width:220px;">
    <option value="">Todos os cursos</option>
    <?php foreach ($cursosList as $c): ?>
    <option value="<?= $c['id'] ?>" <?= $cursoId === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['nome']) ?></option>
    <?php endforeach; ?>
  </select>
  <select class="form-select auto-filter" name="status" style="max-width:160px;">
    <option value="">Todos os status</option>
    <option value="pendente" <?= $status === 'pendente' ? 'selected' : '' ?>>Pendente</option>
    <option value="confirmado" <?= $status === 'confirmado' ? 'selected' : '' ?>>Confirmado</option>
    <option value="cancelado" <?= $status === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
  </select>
  <select class="form-select auto-filter" name="forma" style="max-width:160px;">
    <option value="">Todas as formas</option>
    <option value="pix" <?= $formaPag === 'pix' ? 'selected' : '' ?>>PIX</option>
    <option value="transferencia" <?= $formaPag === 'transferencia' ? 'selected' : '' ?>>Transferência</option>
    <option value="deposito" <?= $formaPag === 'deposito' ? 'selected' : '' ?>>Depósito</option>
    <option value="cartao" <?= $formaPag === 'cartao' ? 'selected' : '' ?>>Cartão</option>
  </select>
  <button type="submit" class="btn" style="background:var(--primary);color:#fff;border-radius:8px;font-weight:700;">
    <i class="bi bi-search"></i>
  </button>
  <?php if ($searchQ || $cursoId || $status || $formaPag): ?>
  <a href="/admin/inscricoes.php" class="btn btn-outline-secondary" style="border-radius:8px;">Limpar</a>
  <?php endif; ?>
</form>

<!-- Resumo -->
<?php
$total = count($inscricoes);
$nPend = count(array_filter($inscricoes, fn($i) => $i['status_pagamento'] === 'pendente'));
$nConf = count(array_filter($inscricoes, fn($i) => $i['status_pagamento'] === 'confirmado'));
$nCanc = count(array_filter($inscricoes, fn($i) => $i['status_pagamento'] === 'cancelado'));
?>
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon primary"><i class="bi bi-people-fill"></i></div>
      <div><div class="stat-value"><?= $total ?></div><div class="stat-label">Total</div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(255,193,7,.15);color:#856404;"><i class="bi bi-hourglass-split"></i></div>
      <div><div class="stat-value"><?= $nPend ?></div><div class="stat-label">Pendentes</div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon success"><i class="bi bi-check-circle-fill"></i></div>
      <div><div class="stat-value"><?= $nConf ?></div><div class="stat-label">Confirmados</div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon accent"><i class="bi bi-x-circle-fill"></i></div>
      <div><div class="stat-value"><?= $nCanc ?></div><div class="stat-label">Cancelados</div></div>
    </div>
  </div>
</div>

<div class="admin-card">
  <div class="admin-card-header">
    <h5><i class="bi bi-people-fill"></i> Inscrições (<?= $total ?>)</h5>
  </div>
  <div style="overflow-x:auto;">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Nome</th>
          <th>Curso</th>
          <th>Contato</th>
          <th>Pagamento</th>
          <th>Status</th>
          <th>Data</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($inscricoes)): ?>
        <tr><td colspan="7" class="text-center py-4 text-muted">Nenhuma inscrição encontrada</td></tr>
        <?php endif; ?>
        <?php foreach ($inscricoes as $ins): ?>
        <?php $st = formatarStatusPagamento($ins['status_pagamento']); ?>
        <tr>
          <td>
            <div style="font-weight:700;font-size:.88rem;"><?= h($ins['nome_completo']) ?></div>
            <div style="font-size:.75rem;color:#888;"><?= h($ins['cpf'] ? mascaraCPF($ins['cpf']) : '-') ?></div>
          </td>
          <td style="font-size:.83rem;color:var(--primary);font-weight:600;"><?= h($ins['curso_nome']) ?></td>
          <td>
            <div style="font-size:.8rem;"><?= h($ins['email']) ?></div>
            <div style="font-size:.78rem;color:#888;"><?= h($ins['telefone']) ?></div>
          </td>
          <td style="font-size:.82rem;"><?= h(formatarFormaPagamento($ins['forma_pagamento'])) ?></td>
          <td><span class="badge badge-<?= $st['class'] ?>"><?= h($st['label']) ?></span></td>
          <td style="font-size:.8rem;color:#888;"><?= formatarData($ins['criado_em'], 'd/m/Y H:i') ?></td>
          <td>
            <div class="actions">
              <a href="/admin/inscricoes.php?ver=<?= $ins['id'] ?>" class="btn-action view"
                 title="Ver detalhes"><i class="bi bi-eye"></i></a>
              <?php if (isAdmin()): ?>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Remover esta inscrição?')">
                <input type="hidden" name="csrf_token" value="<?= gerarCSRF() ?>">
                <input type="hidden" name="acao" value="deletar">
                <input type="hidden" name="id" value="<?= $ins['id'] ?>">
                <button type="submit" class="btn-action delete"><i class="bi bi-trash"></i></button>
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

<!-- Modal de detalhes -->
<?php if ($detalhe): ?>
<div class="modal fade show" style="display:block;background:rgba(0,0,0,.5);" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-lines-fill me-2"></i>Detalhes da Inscrição</h5>
        <a href="/admin/inscricoes.php" class="btn-close"></a>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <strong style="color:var(--primary);font-size:.82rem;">Nome Completo</strong>
            <div><?= h($detalhe['nome_completo']) ?></div>
          </div>
          <div class="col-md-6">
            <strong style="color:var(--primary);font-size:.82rem;">Curso</strong>
            <div><?= h($detalhe['curso_nome']) ?></div>
          </div>
          <div class="col-md-6">
            <strong style="color:var(--primary);font-size:.82rem;">E-mail</strong>
            <div><?= h($detalhe['email']) ?></div>
          </div>
          <div class="col-md-6">
            <strong style="color:var(--primary);font-size:.82rem;">Telefone</strong>
            <div><?= h($detalhe['telefone']) ?></div>
          </div>
          <div class="col-md-6">
            <strong style="color:var(--primary);font-size:.82rem;">CPF</strong>
            <div><?= h($detalhe['cpf']) ?></div>
          </div>
          <div class="col-md-6">
            <strong style="color:var(--primary);font-size:.82rem;">RG</strong>
            <div><?= h($detalhe['rg'] ?: '-') ?></div>
          </div>
          <div class="col-md-6">
            <strong style="color:var(--primary);font-size:.82rem;">Profissão</strong>
            <div><?= h($detalhe['profissao'] ?: '-') ?></div>
          </div>
          <div class="col-md-6">
            <strong style="color:var(--primary);font-size:.82rem;">Formação</strong>
            <div><?= h($detalhe['formacao'] ?: '-') ?></div>
          </div>
          <div class="col-12">
            <strong style="color:var(--primary);font-size:.82rem;">Endereço</strong>
            <div><?= h($detalhe['endereco'] ?: '-') ?></div>
          </div>
          <div class="col-md-6">
            <strong style="color:var(--primary);font-size:.82rem;">Forma de Pagamento</strong>
            <div><?= h(formatarFormaPagamento($detalhe['forma_pagamento'])) ?></div>
          </div>
          <div class="col-md-6">
            <strong style="color:var(--primary);font-size:.82rem;">Data de Inscrição</strong>
            <div><?= formatarData($detalhe['criado_em'], 'd/m/Y H:i') ?></div>
          </div>
          <?php if ($detalhe['observacoes']): ?>
          <div class="col-12">
            <strong style="color:var(--primary);font-size:.82rem;">Observações</strong>
            <div><?= nl2br(h($detalhe['observacoes'])) ?></div>
          </div>
          <?php endif; ?>
        </div>

        <!-- Atualizar status -->
        <hr>
        <form method="POST" class="row g-3 align-items-end">
          <input type="hidden" name="csrf_token" value="<?= gerarCSRF() ?>">
          <input type="hidden" name="acao" value="atualizar_status">
          <input type="hidden" name="id" value="<?= $detalhe['id'] ?>">
          <div class="col-md-4">
            <label class="form-label fw-bold" style="font-size:.85rem;color:var(--primary);">Status do Pagamento</label>
            <select class="form-select" name="status">
              <option value="pendente" <?= $detalhe['status_pagamento'] === 'pendente' ? 'selected' : '' ?>>Pendente</option>
              <option value="confirmado" <?= $detalhe['status_pagamento'] === 'confirmado' ? 'selected' : '' ?>>Confirmado</option>
              <option value="cancelado" <?= $detalhe['status_pagamento'] === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold" style="font-size:.85rem;color:var(--primary);">Observações</label>
            <input type="text" class="form-control" name="observacoes" value="<?= h($detalhe['observacoes'] ?? '') ?>" maxlength="500">
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn w-100" style="background:var(--primary);color:#fff;font-weight:700;border-radius:8px;padding:10px;">
              Salvar
            </button>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <a href="/admin/inscricoes.php" class="btn btn-secondary" style="border-radius:8px;">Fechar</a>
        <a href="/admin/certificados.php?inscricao_id=<?= $detalhe['id'] ?>"
           class="btn" style="background:var(--secondary);color:#fff;border-radius:8px;font-weight:700;">
          <i class="bi bi-patch-check me-1"></i>Emitir Certificado
        </a>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
