<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = getConfig('site_nome', 'Sistema de Cursos') . ' - Cursos Disponíveis';
$pageDesc  = getConfig('site_descricao', 'Confira nossos cursos disponíveis e faça sua inscrição.');

$db = getDB();

// Busca e filtros
$busca  = sanitize($_GET['q'] ?? '');
$tipo   = sanitize($_GET['tipo'] ?? '');
$where  = ["c.ativo = 1"];
$params = [];

if ($busca) {
    $where[]  = "(c.nome LIKE ? OR c.ministrador_nome LIKE ? OR c.descricao_curta LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}
if ($tipo && in_array($tipo, ['presencial', 'online'], true)) {
    $where[]  = "c.tipo = ?";
    $params[] = $tipo;
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);
$stmt = $db->prepare("
    SELECT c.*, COALESCE(ic.total, 0) AS inscritos_count
    FROM cursos c
    LEFT JOIN (
        SELECT curso_id, COUNT(*) AS total
        FROM inscricoes
        WHERE status_pagamento != 'cancelado'
        GROUP BY curso_id
    ) ic ON ic.curso_id = c.id
    $whereSQL
    ORDER BY c.data_hora ASC, c.criado_em DESC
");
$stmt->execute($params);
$cursos = $stmt->fetchAll();

$totalCursos = count($cursos);
$nPresencial = count(array_filter($cursos, fn($c) => $c['tipo'] === 'presencial'));
$nOnline     = count(array_filter($cursos, fn($c) => $c['tipo'] === 'online'));

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-7">
        <h1 class="fade-in-up">
          <?= h(getConfig('site_nome', 'Sistema de Cursos')) ?>
        </h1>
        <p class="lead mb-4 fade-in-up">
          <?= h(getConfig('site_descricao', 'Encontre o curso ideal para você e faça sua inscrição online.')) ?>
        </p>
        <form class="hero-search-bar d-flex fade-in-up" id="form-busca" action="<?= BASE_PATH ?>/index.php" method="GET">
          <input type="text" id="search-cursos" name="q" class="form-control"
            placeholder="Buscar cursos, instrutores..." value="<?= h($busca) ?>" autocomplete="off">
          <button type="submit" class="btn">
            <i class="bi bi-search"></i> Buscar
          </button>
        </form>
      </div>
      <div class="col-lg-5 d-none d-lg-flex justify-content-end mt-4 mt-lg-0">
        <div class="d-flex gap-3 flex-wrap justify-content-end">
          <div class="text-center p-3" style="background:rgba(255,255,255,.1);border-radius:12px;min-width:100px;">
            <div style="font-size:2rem;font-weight:bold;color:var(--secondary);"><?= $totalCursos ?></div>
            <div style="font-size:.8rem;opacity:.8;">Cursos</div>
          </div>
          <div class="text-center p-3" style="background:rgba(255,255,255,.1);border-radius:12px;min-width:100px;">
            <div style="font-size:2rem;font-weight:bold;color:var(--secondary);"><?= $nPresencial ?></div>
            <div style="font-size:.8rem;opacity:.8;">Presenciais</div>
          </div>
          <div class="text-center p-3" style="background:rgba(255,255,255,.1);border-radius:12px;min-width:100px;">
            <div style="font-size:2rem;font-weight:bold;color:var(--secondary);"><?= $nOnline ?></div>
            <div style="font-size:.8rem;opacity:.8;">Online</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Filtros -->
<section class="filter-bar" id="cursos">
  <div class="container d-flex align-items-center flex-wrap gap-2">
    <span class="me-2" style="font-size:.88rem;font-weight:700;color:#666;">Filtrar:</span>
    <a href="<?= BASE_PATH ?>/index.php<?= $busca ? '?q=' . urlencode($busca) : '' ?>"
       class="btn-filter <?= !$tipo ? 'active' : '' ?>">
      Todos (<?= $totalCursos ?>)
    </a>
    <a href="<?= BASE_PATH ?>/index.php?tipo=presencial<?= $busca ? '&q=' . urlencode($busca) : '' ?>"
       class="btn-filter <?= $tipo === 'presencial' ? 'active' : '' ?>">
      <i class="bi bi-geo-alt"></i> Presencial (<?= $nPresencial ?>)
    </a>
    <a href="<?= BASE_PATH ?>/index.php?tipo=online<?= $busca ? '&q=' . urlencode($busca) : '' ?>"
       class="btn-filter <?= $tipo === 'online' ? 'active' : '' ?>">
      <i class="bi bi-wifi"></i> Online (<?= $nOnline ?>)
    </a>
    <?php if ($busca): ?>
    <span class="ms-auto" style="font-size:.85rem;color:#888;">
      Resultados para: <strong>"<?= h($busca) ?>"</strong>
      <a href="<?= BASE_PATH ?>/index.php" class="ms-1 text-danger" style="font-size:.8rem;"><i class="bi bi-x-circle"></i> Limpar</a>
    </span>
    <?php endif; ?>
  </div>
</section>

<!-- Cursos -->
<section class="py-5">
  <div class="container">
    <?php if ($busca || $tipo): ?>
    <h2 class="section-title mb-1">
      <?= $busca ? 'Resultados da busca' : 'Cursos ' . formatarTipoCurso($tipo) ?>
    </h2>
    <p class="section-subtitle"><?= $totalCursos ?> curso(s) encontrado(s)<?= $busca ? ' para "' . h($busca) . '"' : '' ?></p>
    <?php else: ?>
    <h2 class="section-title mb-1">Cursos Disponíveis</h2>
    <p class="section-subtitle">Confira todos os nossos cursos e faça sua inscrição</p>
    <?php endif; ?>

    <?php if (empty($cursos)): ?>
    <div class="no-results" id="no-results">
      <i class="bi bi-search d-block"></i>
      <h5>Nenhum curso encontrado</h5>
      <p>Tente buscar por outro termo ou <a href="<?= BASE_PATH ?>/index.php">ver todos os cursos</a>.</p>
    </div>
    <?php else: ?>
    <div id="no-results" class="no-results" style="display:none;">
      <i class="bi bi-search d-block"></i>
      <h5>Nenhum curso encontrado</h5>
    </div>
    <div class="row g-4">
      <?php foreach ($cursos as $i => $curso): ?>
      <div class="col-sm-6 col-lg-4 curso-card-wrapper"
           data-tipo="<?= h($curso['tipo']) ?>"
           data-titulo="<?= h(mb_strtolower($curso['nome'])) ?>"
           data-ministrador="<?= h(mb_strtolower($curso['ministrador_nome'] ?? '')) ?>"
           style="animation-delay:<?= $i * 0.05 ?>s">
        <div class="course-card">
          <div class="course-card-img">
            <?php $capa = urlImagem($curso['capa_foto'] ?? ''); ?>
            <?php if ($capa): ?>
              <img src="<?= h($capa) ?>" alt="<?= h($curso['nome']) ?>" loading="lazy">
            <?php else: ?>
              <div style="width:100%;height:100%;background:linear-gradient(135deg,var(--primary),var(--primary-light));display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-mortarboard-fill" style="font-size:4rem;color:rgba(255,255,255,.3);"></i>
              </div>
            <?php endif; ?>
            <span class="badge-tipo"><?= $curso['tipo'] === 'online' ? '<i class="bi bi-wifi"></i> Online' : '<i class="bi bi-geo-alt"></i> Presencial' ?></span>
            <?php if ($curso['vagas'] !== null): ?>
            <span class="badge-vagas">
              <?php
              $vagas_rest = max(0, $curso['vagas'] - (int)$curso['inscritos_count']);
              echo $vagas_rest . ' vaga' . ($vagas_rest !== 1 ? 's' : '');
              ?>
            </span>
            <?php endif; ?>
          </div>
          <div class="course-card-body">
            <h3 class="course-card-title"><?= h($curso['nome']) ?></h3>
            <div class="course-card-meta">
              <?php if ($curso['data_hora']): ?>
              <div><i class="bi bi-calendar3"></i> <?= formatarDataHora($curso['data_hora']) ?></div>
              <?php endif; ?>
              <?php if ($curso['ministrador_nome']): ?>
              <div><i class="bi bi-person-circle"></i> <?= h($curso['ministrador_nome']) ?></div>
              <?php endif; ?>
              <?php if ($curso['local'] && $curso['tipo'] === 'presencial'): ?>
              <div><i class="bi bi-geo-alt-fill"></i> <?= h($curso['local']) ?></div>
              <?php endif; ?>
              <?php if ($curso['carga_horaria']): ?>
              <div><i class="bi bi-clock"></i> <?= h($curso['carga_horaria']) ?>h</div>
              <?php endif; ?>
            </div>
            <?php if ($curso['descricao_curta']): ?>
            <p class="course-card-desc"><?= h($curso['descricao_curta']) ?></p>
            <?php endif; ?>
          </div>
          <div class="course-card-footer">
            <div>
              <?php if ((float)$curso['valor'] <= 0): ?>
              <span class="course-price gratuito"><i class="bi bi-gift"></i> Gratuito</span>
              <?php else: ?>
              <span class="course-price"><?= formatarMoeda((float)$curso['valor']) ?></span>
              <?php endif; ?>
            </div>
            <?php if ($curso['inscricoes_abertas']): ?>
            <a href="<?= BASE_PATH ?>/curso.php?slug=<?= h($curso['slug']) ?>" class="btn-inscricao">
              Inscrever-se <i class="bi bi-arrow-right"></i>
            </a>
            <?php else: ?>
            <span class="badge bg-secondary">Inscrições encerradas</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
