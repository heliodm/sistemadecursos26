<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$db = getDB();

// Estatísticas
$totalCursos    = (int)$db->query("SELECT COUNT(*) FROM cursos")->fetchColumn();
$cursosAtivos   = (int)$db->query("SELECT COUNT(*) FROM cursos WHERE ativo = 1")->fetchColumn();
$totalInscritos = (int)$db->query("SELECT COUNT(*) FROM inscricoes")->fetchColumn();
$pagConfirmados = (int)$db->query("SELECT COUNT(*) FROM inscricoes WHERE status_pagamento = 'confirmado'")->fetchColumn();
$pagPendentes   = (int)$db->query("SELECT COUNT(*) FROM inscricoes WHERE status_pagamento = 'pendente'")->fetchColumn();
$totalCerts     = (int)$db->query("SELECT COUNT(*) FROM certificados WHERE valido = 1")->fetchColumn();

// Receita total (inscrições confirmadas com valor)
$receita = $db->query("SELECT COALESCE(SUM(c.valor), 0) FROM inscricoes i JOIN cursos c ON c.id = i.curso_id WHERE i.status_pagamento = 'confirmado'")->fetchColumn();

// Últimas inscrições
$stmtInsc = $db->query("
    SELECT i.nome_completo, i.email, i.criado_em, i.status_pagamento, i.forma_pagamento, c.nome AS curso_nome
    FROM inscricoes i JOIN cursos c ON c.id = i.curso_id
    ORDER BY i.criado_em DESC LIMIT 8
");
$ultimasInscricoes = $stmtInsc->fetchAll();

// Próximos cursos
$stmtProx = $db->query("
    SELECT c.*, (SELECT COUNT(*) FROM inscricoes WHERE curso_id = c.id AND status_pagamento != 'cancelado') AS inscritos
    FROM cursos c
    WHERE c.ativo = 1 AND c.data_hora >= NOW()
    ORDER BY c.data_hora ASC LIMIT 5
");
$proximosCursos = $stmtProx->fetchAll();

// Gráfico - inscrições por dia (últimos 14 dias)
$stmtGraf = $db->query("
    SELECT DATE(criado_em) as dia, COUNT(*) as total
    FROM inscricoes
    WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 14 DAY)
    GROUP BY DATE(criado_em)
    ORDER BY dia ASC
");
$grafData = $stmtGraf->fetchAll();
$grafLabels = json_encode(array_column($grafData, 'dia'));
$grafValues = json_encode(array_column($grafData, 'total'));
?>

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon primary"><i class="bi bi-mortarboard-fill"></i></div>
      <div>
        <div class="stat-value"><?= $totalCursos ?></div>
        <div class="stat-label"><?= $cursosAtivos ?> ativo(s) · Total Cursos</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon secondary"><i class="bi bi-people-fill"></i></div>
      <div>
        <div class="stat-value"><?= $totalInscritos ?></div>
        <div class="stat-label">Total de Inscrições</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon accent"><i class="bi bi-hourglass-split"></i></div>
      <div>
        <div class="stat-value"><?= $pagPendentes ?></div>
        <div class="stat-label">Pag. Pendentes</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card">
      <div class="stat-icon success"><i class="bi bi-patch-check-fill"></i></div>
      <div>
        <div class="stat-value"><?= $totalCerts ?></div>
        <div class="stat-label">Certificados Emitidos</div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Gráfico de inscrições -->
  <div class="col-lg-8">
    <div class="admin-card">
      <div class="admin-card-header">
        <h5><i class="bi bi-bar-chart-fill"></i> Inscrições - Últimos 14 dias</h5>
        <a href="<?= BASE_PATH ?>/admin/inscricoes.php" class="topbar-btn btn-primary-sm">Ver todas</a>
      </div>
      <div class="admin-card-body">
        <canvas id="graficoInscricoes" height="180"></canvas>
      </div>
    </div>
  </div>

  <!-- Resumo rápido -->
  <div class="col-lg-4">
    <div class="admin-card mb-4">
      <div class="admin-card-header">
        <h5><i class="bi bi-cash-coin"></i> Receita (Confirmados)</h5>
      </div>
      <div class="admin-card-body text-center">
        <div style="font-size:2.2rem;font-weight:bold;color:var(--primary);">
          <?= formatarMoeda((float)$receita) ?>
        </div>
        <div style="font-size:.82rem;color:#888;">de <?= $pagConfirmados ?> pagamentos confirmados</div>
        <div class="mt-3">
          <div class="d-flex justify-content-between mb-1" style="font-size:.8rem;">
            <span>Confirmados</span>
            <span class="text-success fw-bold"><?= $pagConfirmados ?></span>
          </div>
          <div class="d-flex justify-content-between mb-1" style="font-size:.8rem;">
            <span>Pendentes</span>
            <span class="text-warning fw-bold"><?= $pagPendentes ?></span>
          </div>
          <div class="d-flex justify-content-between" style="font-size:.8rem;">
            <span>Cancelados</span>
            <span class="text-danger fw-bold"><?= $totalInscritos - $pagConfirmados - $pagPendentes ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Atalhos -->
    <div class="admin-card">
      <div class="admin-card-header">
        <h5><i class="bi bi-lightning-fill"></i> Ações Rápidas</h5>
      </div>
      <div class="admin-card-body d-grid gap-2">
        <a href="<?= BASE_PATH ?>/admin/cursos.php?acao=criar" class="btn btn-sm" style="background:var(--primary);color:#fff;border-radius:8px;font-weight:700;padding:10px;">
          <i class="bi bi-plus-circle me-1"></i>Novo Curso
        </a>
        <a href="<?= BASE_PATH ?>/admin/inscricoes.php?status=pendente" class="btn btn-sm" style="background:#ffc107;color:#000;border-radius:8px;font-weight:700;padding:10px;">
          <i class="bi bi-clock-history me-1"></i>Ver Pendentes (<?= $pagPendentes ?>)
        </a>
        <a href="<?= BASE_PATH ?>/admin/certificados.php" class="btn btn-sm" style="background:var(--secondary);color:#fff;border-radius:8px;font-weight:700;padding:10px;">
          <i class="bi bi-patch-check me-1"></i>Emitir Certificados
        </a>
        <?php if (isAdmin()): ?>
        <a href="<?= BASE_PATH ?>/admin/configuracoes.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;font-weight:700;padding:10px;">
          <i class="bi bi-gear me-1"></i>Configurações
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Próximos Cursos e Últimas Inscrições -->
<div class="row g-4 mt-2">
  <div class="col-lg-6">
    <div class="admin-card">
      <div class="admin-card-header">
        <h5><i class="bi bi-calendar-event-fill"></i> Próximos Cursos</h5>
        <a href="<?= BASE_PATH ?>/admin/cursos.php" class="topbar-btn btn-primary-sm">Gerenciar</a>
      </div>
      <div class="admin-card-body p-0">
        <?php if (empty($proximosCursos)): ?>
        <div class="text-center py-4 text-muted" style="font-size:.9rem;">
          <i class="bi bi-calendar-x d-block" style="font-size:2rem;margin-bottom:8px;"></i>
          Nenhum curso agendado
        </div>
        <?php else: ?>
        <div class="list-group list-group-flush" style="border-radius:0 0 10px 10px;">
          <?php foreach ($proximosCursos as $c): ?>
          <a href="<?= BASE_PATH ?>/admin/cursos.php?editar=<?= $c['id'] ?>" class="list-group-item list-group-item-action" style="border:none;border-bottom:1px solid var(--gray-mid);">
            <div class="d-flex align-items-start justify-content-between gap-2">
              <div>
                <div style="font-weight:700;font-size:.9rem;color:var(--primary);"><?= h($c['nome']) ?></div>
                <div style="font-size:.8rem;color:#888;">
                  <i class="bi bi-calendar3"></i> <?= formatarDataHora($c['data_hora']) ?>
                  · <i class="bi bi-people"></i> <?= $c['inscritos'] ?> inscritos
                </div>
              </div>
              <span class="badge <?= $c['tipo'] === 'online' ? 'badge-tipo-online' : 'badge-tipo-presencial' ?>" style="font-size:.72rem;">
                <?= formatarTipoCurso($c['tipo']) ?>
              </span>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="admin-card">
      <div class="admin-card-header">
        <h5><i class="bi bi-person-plus-fill"></i> Últimas Inscrições</h5>
        <a href="<?= BASE_PATH ?>/admin/inscricoes.php" class="topbar-btn btn-primary-sm">Ver todas</a>
      </div>
      <div class="admin-card-body p-0">
        <?php if (empty($ultimasInscricoes)): ?>
        <div class="text-center py-4 text-muted" style="font-size:.9rem;">
          <i class="bi bi-inbox d-block" style="font-size:2rem;margin-bottom:8px;"></i>
          Nenhuma inscrição ainda
        </div>
        <?php else: ?>
        <div class="list-group list-group-flush" style="border-radius:0 0 10px 10px;">
          <?php foreach ($ultimasInscricoes as $ins): ?>
          <?php $st = formatarStatusPagamento($ins['status_pagamento']); ?>
          <div class="list-group-item" style="border:none;border-bottom:1px solid var(--gray-mid);">
            <div class="d-flex align-items-start justify-content-between gap-2">
              <div>
                <div style="font-weight:700;font-size:.88rem;color:var(--primary);"><?= h($ins['nome_completo']) ?></div>
                <div style="font-size:.78rem;color:#888;"><?= h($ins['curso_nome']) ?> · <?= formatarData($ins['criado_em'], 'd/m/Y') ?></div>
                <div style="font-size:.75rem;color:#aaa;"><?= h($ins['email']) ?></div>
              </div>
              <span class="badge badge-<?= $st['class'] ?>" style="font-size:.7rem;"><?= h($st['label']) ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('graficoInscricoes');
if (ctx) {
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: <?= $grafLabels ?>,
      datasets: [{
        label: 'Inscrições',
        data: <?= $grafValues ?>,
        backgroundColor: 'rgba(27,58,107,0.7)',
        borderColor: '#1B3A6B',
        borderWidth: 2,
        borderRadius: 6,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, ticks: { stepSize: 1 } },
        x: { grid: { display: false } }
      }
    }
  });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
