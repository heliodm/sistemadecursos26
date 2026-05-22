<?php
$pageTitle = 'Gerenciar Pagamentos';
require_once __DIR__ . '/includes/header.php';

$db = getDB();

// Processar atualização de status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarCSRF($_POST['csrf_token'] ?? '')) {
        redirect('/admin/pagamentos.php', 'Token inválido.', 'danger');
    }
    $id     = (int)$_POST['id'];
    $status = sanitize($_POST['status'] ?? '');
    $obs    = sanitize($_POST['observacoes'] ?? '');
    if ($id > 0 && in_array($status, ['pendente','confirmado','cancelado'], true)) {
        $db->prepare("UPDATE inscricoes SET status_pagamento = ?, observacoes = ? WHERE id = ?")->execute([$status, $obs ?: null, $id]);
        redirect('/admin/pagamentos.php', 'Status atualizado com sucesso.', 'success');
    }
}

// Filtros
$status   = sanitize($_GET['status'] ?? '');
$forma    = sanitize($_GET['forma'] ?? '');
$cursoId  = (int)($_GET['curso_id'] ?? 0);
$searchQ  = sanitize($_GET['q'] ?? '');

$where  = [];
$params = [];
if ($status && in_array($status, ['pendente','confirmado','cancelado'])) { $where[] = "i.status_pagamento = ?"; $params[] = $status; }
if ($forma) { $where[] = "i.forma_pagamento = ?"; $params[] = $forma; }
if ($cursoId > 0) { $where[] = "i.curso_id = ?"; $params[] = $cursoId; }
if ($searchQ) { $where[] = "(i.nome_completo LIKE ? OR i.email LIKE ?)"; $params[] = "%$searchQ%"; $params[] = "%$searchQ%"; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("
    SELECT i.id, i.nome_completo, i.email, i.telefone, i.cpf, i.forma_pagamento,
           i.status_pagamento, i.observacoes, i.criado_em, i.atualizado_em,
           c.nome AS curso_nome, c.valor AS curso_valor
    FROM inscricoes i
    JOIN cursos c ON c.id = i.curso_id
    $whereSQL
    ORDER BY i.criado_em DESC
");
$stmt->execute($params);
$pagamentos = $stmt->fetchAll();

// Stats
$stPend = (int)$db->query("SELECT COUNT(*) FROM inscricoes WHERE status_pagamento='pendente'")->fetchColumn();
$stConf = (int)$db->query("SELECT COUNT(*) FROM inscricoes WHERE status_pagamento='confirmado'")->fetchColumn();
$stCanc = (int)$db->query("SELECT COUNT(*) FROM inscricoes WHERE status_pagamento='cancelado'")->fetchColumn();
$receita = (float)$db->query("SELECT COALESCE(SUM(c.valor),0) FROM inscricoes i JOIN cursos c ON c.id=i.curso_id WHERE i.status_pagamento='confirmado'")->fetchColumn();

$cursosList = $db->query("SELECT id, nome FROM cursos ORDER BY nome ASC")->fetchAll();
?>

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(255,193,7,.15);color:#856404;"><i class="bi bi-hourglass-split"></i></div>
      <div><div class="stat-value"><?= $stPend ?></div><div class="stat-label">Pendentes</div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon success"><i class="bi bi-check-circle-fill"></i></div>
      <div><div class="stat-value"><?= $stConf ?></div><div class="stat-label">Confirmados</div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon accent"><i class="bi bi-x-circle-fill"></i></div>
      <div><div class="stat-value"><?= $stCanc ?></div><div class="stat-label">Cancelados</div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-icon secondary"><i class="bi bi-cash-coin"></i></div>
      <div><div class="stat-value" style="font-size:1.3rem;"><?= formatarMoeda($receita) ?></div><div class="stat-label">Receita Total</div></div>
    </div>
  </div>
</div>

<!-- Filtros -->
<form method="GET" class="admin-filters">
  <input type="text" class="form-control" name="q" placeholder="Nome ou e-mail..." value="<?= h($searchQ) ?>" style="max-width:200px;">
  <select class="form-select auto-filter" name="status" style="max-width:160px;">
    <option value="">Todos</option>
    <option value="pendente" <?= $status === 'pendente' ? 'selected' : '' ?>>Pendentes</option>
    <option value="confirmado" <?= $status === 'confirmado' ? 'selected' : '' ?>>Confirmados</option>
    <option value="cancelado" <?= $status === 'cancelado' ? 'selected' : '' ?>>Cancelados</option>
  </select>
  <select class="form-select auto-filter" name="forma" style="max-width:160px;">
    <option value="">Todas as formas</option>
    <option value="pix" <?= $forma === 'pix' ? 'selected' : '' ?>>PIX</option>
    <option value="transferencia" <?= $forma === 'transferencia' ? 'selected' : '' ?>>Transferência</option>
    <option value="deposito" <?= $forma === 'deposito' ? 'selected' : '' ?>>Depósito</option>
    <option value="cartao" <?= $forma === 'cartao' ? 'selected' : '' ?>>Cartão</option>
  </select>
  <select class="form-select auto-filter" name="curso_id" style="max-width:200px;">
    <option value="">Todos os cursos</option>
    <?php foreach ($cursosList as $c): ?>
    <option value="<?= $c['id'] ?>" <?= $cursoId === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['nome']) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn" style="background:var(--primary);color:#fff;border-radius:8px;font-weight:700;"><i class="bi bi-search"></i></button>
  <?php if ($searchQ || $status || $forma || $cursoId): ?>
  <a href="/admin/pagamentos.php" class="btn btn-outline-secondary" style="border-radius:8px;">Limpar</a>
  <?php endif; ?>
  <a href="/api/exportar.php?tipo=pagamentos<?= $status ? '&status='.$status : '' ?><?= $cursoId ? '&curso_id='.$cursoId : '' ?>"
     class="btn ms-auto" style="background:var(--secondary);color:#fff;border-radius:8px;font-weight:700;">
    <i class="bi bi-file-earmark-excel"></i> Exportar Excel
  </a>
</form>

<div class="admin-card">
  <div class="admin-card-header">
    <h5><i class="bi bi-cash-coin"></i> Pagamentos (<?= count($pagamentos) ?>)</h5>
  </div>
  <div style="overflow-x:auto;">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Inscrito</th>
          <th>Curso</th>
          <th>Valor</th>
          <th>Forma</th>
          <th>Status</th>
          <th>Data</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($pagamentos)): ?>
        <tr><td colspan="7" class="text-center py-4 text-muted">Nenhum pagamento encontrado</td></tr>
        <?php endif; ?>
        <?php foreach ($pagamentos as $p): ?>
        <?php $st = formatarStatusPagamento($p['status_pagamento']); ?>
        <tr>
          <td>
            <div style="font-weight:700;font-size:.88rem;"><?= h($p['nome_completo']) ?></div>
            <div style="font-size:.75rem;color:#888;"><?= h($p['email']) ?></div>
          </td>
          <td style="font-size:.85rem;color:var(--primary);font-weight:600;max-width:180px;">
            <?= h($p['curso_nome']) ?>
          </td>
          <td style="font-weight:700;color:<?= (float)$p['curso_valor'] > 0 ? 'var(--accent)' : '#28a745' ?>;">
            <?= (float)$p['curso_valor'] > 0 ? formatarMoeda((float)$p['curso_valor']) : 'Grátis' ?>
          </td>
          <td style="font-size:.83rem;"><?= h(formatarFormaPagamento($p['forma_pagamento'])) ?></td>
          <td><span class="badge badge-<?= $st['class'] ?> status-badge" data-id="<?= $p['id'] ?>"><?= h($st['label']) ?></span></td>
          <td style="font-size:.8rem;color:#888;"><?= formatarData($p['criado_em'], 'd/m/Y H:i') ?></td>
          <td>
            <button type="button" class="btn-action view"
                    onclick="abrirModalPag(<?= $p['id'] ?>, <?= h(json_encode($p['nome_completo'])) ?>, <?= h(json_encode($p['status_pagamento'])) ?>, <?= h(json_encode($p['observacoes'] ?? '')) ?>)">
              <i class="bi bi-pencil-square"></i> Status
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Atualizar Status -->
<div class="modal fade" id="modalPagamento" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-cash-coin me-2"></i>Atualizar Status do Pagamento</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= gerarCSRF() ?>">
          <input type="hidden" name="id" id="modal-pag-id">
          <div class="mb-3">
            <strong id="modal-pag-nome" class="d-block mb-3" style="color:var(--primary);"></strong>
            <label class="form-label fw-bold" style="font-size:.88rem;color:var(--primary);">Status do Pagamento</label>
            <select class="form-select" name="status" id="modal-pag-status">
              <option value="pendente">Pendente</option>
              <option value="confirmado">Confirmado</option>
              <option value="cancelado">Cancelado</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold" style="font-size:.88rem;color:var(--primary);">Observações</label>
            <textarea class="form-control" name="observacoes" id="modal-pag-obs" rows="3" maxlength="500"
                      placeholder="Ex: Comprovante recebido, transferência confirmada..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn" style="background:var(--primary);color:#fff;font-weight:700;border-radius:8px;">
            <i class="bi bi-check2"></i> Confirmar
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function abrirModalPag(id, nome, status, obs) {
  document.getElementById('modal-pag-id').value = id;
  document.getElementById('modal-pag-nome').textContent = nome;
  document.getElementById('modal-pag-status').value = status;
  document.getElementById('modal-pag-obs').value = obs;
  new bootstrap.Modal(document.getElementById('modalPagamento')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
