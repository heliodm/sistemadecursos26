<?php
$pageTitle = 'Gerenciar Cursos';
require_once __DIR__ . '/includes/header.php';

$db     = getDB();
$acao   = sanitize($_GET['acao'] ?? '');
$editId = (int)($_GET['editar'] ?? 0);
$erros  = [];

// ---- PROCESSAR FORMULÁRIO ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarCSRF($_POST['csrf_token'] ?? '')) {
        $erros[] = 'Token de segurança inválido.';
    } else {
        $postAcao = sanitize($_POST['acao'] ?? '');

        if ($postAcao === 'deletar') {
            requireAdmin();
            $id = (int)$_POST['id'];
            // Buscar arquivos para deletar
            $c = $db->prepare("SELECT capa_foto, ministrador_foto FROM cursos WHERE id = ?");
            $c->execute([$id]);
            $oldCurso = $c->fetch();
            if ($oldCurso) {
                deletarUpload($oldCurso['capa_foto']);
                deletarUpload($oldCurso['ministrador_foto']);
            }
            $db->prepare("DELETE FROM cursos WHERE id = ?")->execute([$id]);
            redirect('/admin/cursos.php', 'Curso removido com sucesso.', 'success');
        }

        if ($postAcao === 'toggle_status') {
            $id = (int)$_POST['id'];
            $db->prepare("UPDATE cursos SET ativo = NOT ativo WHERE id = ?")->execute([$id]);
            redirect('/admin/cursos.php', 'Status do curso atualizado.', 'success');
        }

        if (in_array($postAcao, ['criar', 'editar'], true)) {
            $cursoId    = (int)($_POST['curso_id'] ?? 0);
            $nome       = sanitize($_POST['nome'] ?? '');
            $dataHora   = sanitize($_POST['data_hora'] ?? '');
            $dataFim    = sanitize($_POST['data_fim'] ?? '');
            $tipo       = in_array($_POST['tipo'] ?? '', ['presencial', 'online']) ? $_POST['tipo'] : 'presencial';
            $local      = sanitize($_POST['local'] ?? '');
            $cargaH     = (int)($_POST['carga_horaria'] ?? 0) ?: null;
            $vagas      = strlen($_POST['vagas'] ?? '') > 0 ? (int)$_POST['vagas'] : null;
            $valor      = (float)str_replace(',', '.', $_POST['valor'] ?? '0');
            $descCurta  = sanitize($_POST['descricao_curta'] ?? '');
            $descricao  = sanitize($_POST['descricao'] ?? '');
            $minNome    = sanitize($_POST['ministrador_nome'] ?? '');
            $minBio     = sanitize($_POST['ministrador_bio'] ?? '');
            $ativo      = isset($_POST['ativo']) ? 1 : 0;
            $inscAbertas = isset($_POST['inscricoes_abertas']) ? 1 : 0;

            if (empty($nome)) $erros[] = 'Nome do curso é obrigatório.';
            if (strlen($descCurta) > 500) $erros[] = 'Descrição curta deve ter no máximo 500 caracteres.';

            // Slug (somente se nome válido)
            $slug = !empty($nome) ? gerarSlugUnico($nome, $postAcao === 'editar' ? $cursoId : null) : '';

            // Uploads
            $capaFoto  = null;
            $minFoto   = null;

            // Buscar dados antigos para manter imagens existentes
            $oldData = null;
            if ($postAcao === 'editar' && $cursoId > 0) {
                $s = $db->prepare("SELECT capa_foto, ministrador_foto FROM cursos WHERE id = ?");
                $s->execute([$cursoId]);
                $oldData = $s->fetch();
                $capaFoto = $oldData['capa_foto'] ?? null;
                $minFoto  = $oldData['ministrador_foto'] ?? null;
            }

            if (!empty($_FILES['capa_foto']['name'])) {
                $upResult = uploadImagem($_FILES['capa_foto'], 'cursos');
                if ($upResult['sucesso']) {
                    if ($capaFoto) deletarUpload($capaFoto);
                    $capaFoto = $upResult['arquivo'];
                } else {
                    $erros[] = 'Capa: ' . $upResult['erro'];
                }
            }
            if (!empty($_FILES['ministrador_foto']['name'])) {
                $upResult = uploadImagem($_FILES['ministrador_foto'], 'instrutores');
                if ($upResult['sucesso']) {
                    if ($minFoto) deletarUpload($minFoto);
                    $minFoto = $upResult['arquivo'];
                } else {
                    $erros[] = 'Foto do instrutor: ' . $upResult['erro'];
                }
            }

            if (empty($erros)) {
                $params = [
                    $nome, $slug,
                    $dataHora ?: null, $dataFim ?: null,
                    $tipo, $local ?: null, $cargaH, $vagas, $valor,
                    $descricao, $descCurta,
                    $minNome ?: null, $minBio ?: null, $minFoto, $capaFoto,
                    $ativo, $inscAbertas,
                ];
                if ($postAcao === 'criar') {
                    $params[] = $usuario['id'];
                    $stmt = $db->prepare("
                        INSERT INTO cursos (nome, slug, data_hora, data_fim, tipo, local, carga_horaria, vagas, valor,
                        descricao, descricao_curta, ministrador_nome, ministrador_bio, ministrador_foto, capa_foto,
                        ativo, inscricoes_abertas, criado_por)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                    ");
                    $stmt->execute($params);
                    redirect('/admin/cursos.php', 'Curso criado com sucesso!', 'success');
                } else {
                    $params[] = $cursoId;
                    $stmt = $db->prepare("
                        UPDATE cursos SET nome=?, slug=?, data_hora=?, data_fim=?, tipo=?, local=?,
                        carga_horaria=?, vagas=?, valor=?, descricao=?, descricao_curta=?,
                        ministrador_nome=?, ministrador_bio=?, ministrador_foto=?, capa_foto=?,
                        ativo=?, inscricoes_abertas=? WHERE id=?
                    ");
                    $stmt->execute($params);
                    redirect('/admin/cursos.php', 'Curso atualizado com sucesso!', 'success');
                }
            }
        }
    }
}

// ---- BUSCAR CURSO PARA EDIÇÃO ----
$cursoEdit = null;
if ($editId > 0 || $acao === 'criar') {
    if ($editId > 0) {
        $s = $db->prepare("SELECT * FROM cursos WHERE id = ?");
        $s->execute([$editId]);
        $cursoEdit = $s->fetch();
        if (!$cursoEdit) redirect('/admin/cursos.php', 'Curso não encontrado.', 'danger');
        $pageTitle = 'Editar Curso: ' . $cursoEdit['nome'];
    } else {
        $pageTitle = 'Novo Curso';
        $cursoEdit = ['id'=>0,'nome'=>'','slug'=>'','data_hora'=>'','data_fim'=>'','tipo'=>'presencial','local'=>'','carga_horaria'=>'','vagas'=>'','valor'=>'','descricao'=>'','descricao_curta'=>'','ministrador_nome'=>'','ministrador_bio'=>'','capa_foto'=>'','ministrador_foto'=>'','ativo'=>1,'inscricoes_abertas'=>1];
    }
}

// ---- LISTAGEM ----
$searchQ = sanitize($_GET['q'] ?? '');
$filtroTipo = sanitize($_GET['tipo'] ?? '');
$filtroAtivo = $_GET['ativo'] ?? '';
$where = [];
$params = [];
if ($searchQ) { $where[] = "(nome LIKE ? OR ministrador_nome LIKE ?)"; $params[] = "%$searchQ%"; $params[] = "%$searchQ%"; }
if ($filtroTipo && in_array($filtroTipo, ['presencial','online'])) { $where[] = "tipo = ?"; $params[] = $filtroTipo; }
if ($filtroAtivo !== '') { $where[] = "ativo = ?"; $params[] = (int)$filtroAtivo; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$stmtList = $db->prepare("SELECT c.*, (SELECT COUNT(*) FROM inscricoes WHERE curso_id = c.id AND status_pagamento != 'cancelado') AS inscritos FROM cursos c $whereSQL ORDER BY c.criado_em DESC");
$stmtList->execute($params);
$cursos = $stmtList->fetchAll();
?>

<?php if ($cursoEdit !== null): ?>
<!-- ===== FORM CRIAR/EDITAR ===== -->
<div class="d-flex align-items-center gap-2 mb-4">
  <a href="/admin/cursos.php" class="btn-action view"><i class="bi bi-arrow-left"></i> Voltar</a>
  <h4 class="mb-0" style="color:var(--primary);font-weight:bold;"><?= h($pageTitle) ?></h4>
</div>

<?php if (!empty($erros)): ?>
<div class="alert alert-danger mb-4">
  <strong><i class="bi bi-exclamation-triangle"></i> Corrija os erros:</strong>
  <ul class="mb-0 mt-1"><?php foreach ($erros as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="csrf_token" value="<?= gerarCSRF() ?>">
  <input type="hidden" name="acao" value="<?= $cursoEdit['id'] ? 'editar' : 'criar' ?>">
  <input type="hidden" name="curso_id" value="<?= (int)$cursoEdit['id'] ?>">

  <div class="row g-4">
    <!-- Coluna Principal -->
    <div class="col-lg-8">
      <div class="admin-card">
        <div class="admin-card-header"><h5><i class="bi bi-mortarboard-fill"></i> Informações do Curso</h5></div>
        <div class="admin-card-body">
          <div class="admin-form-group">
            <label>Nome do Curso <span class="required">*</span></label>
            <input type="text" class="form-control" name="nome" id="nome_curso"
                   value="<?= h($cursoEdit['nome']) ?>" required maxlength="255">
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="admin-form-group">
                <label>Data e Hora de Início</label>
                <input type="datetime-local" class="form-control" name="data_hora"
                       value="<?= $cursoEdit['data_hora'] ? date('Y-m-d\TH:i', strtotime($cursoEdit['data_hora'])) : '' ?>">
              </div>
            </div>
            <div class="col-md-6">
              <div class="admin-form-group">
                <label>Data e Hora de Encerramento</label>
                <input type="datetime-local" class="form-control" name="data_fim"
                       value="<?= $cursoEdit['data_fim'] ? date('Y-m-d\TH:i', strtotime($cursoEdit['data_fim'])) : '' ?>">
              </div>
            </div>
            <div class="col-md-6">
              <div class="admin-form-group">
                <label>Modalidade <span class="required">*</span></label>
                <select class="form-select" name="tipo">
                  <option value="presencial" <?= $cursoEdit['tipo'] === 'presencial' ? 'selected' : '' ?>>Presencial</option>
                  <option value="online" <?= $cursoEdit['tipo'] === 'online' ? 'selected' : '' ?>>Online</option>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="admin-form-group">
                <label>Local (para presencial)</label>
                <input type="text" class="form-control" name="local"
                       value="<?= h($cursoEdit['local'] ?? '') ?>" maxlength="255">
              </div>
            </div>
            <div class="col-md-4">
              <div class="admin-form-group">
                <label>Carga Horária (h)</label>
                <input type="number" class="form-control" name="carga_horaria"
                       value="<?= h($cursoEdit['carga_horaria'] ?? '') ?>" min="0" max="9999">
              </div>
            </div>
            <div class="col-md-4">
              <div class="admin-form-group">
                <label>Vagas (vazio = ilimitado)</label>
                <input type="number" class="form-control" name="vagas"
                       value="<?= $cursoEdit['vagas'] !== null ? h($cursoEdit['vagas']) : '' ?>" min="0">
              </div>
            </div>
            <div class="col-md-4">
              <div class="admin-form-group">
                <label>Valor (R$)</label>
                <input type="number" class="form-control" name="valor" step="0.01"
                       value="<?= number_format((float)($cursoEdit['valor'] ?? 0), 2, '.', '') ?>" min="0">
                <div class="form-text">0 = Gratuito</div>
              </div>
            </div>
          </div>

          <div class="admin-form-group">
            <label>Descrição Curta (exibida na listagem)</label>
            <textarea class="form-control" name="descricao_curta" rows="2" maxlength="500"><?= h($cursoEdit['descricao_curta'] ?? '') ?></textarea>
            <div class="form-text">Máx. 500 caracteres</div>
          </div>
          <div class="admin-form-group">
            <label>Descrição Completa</label>
            <textarea class="form-control" name="descricao" rows="6"><?= h($cursoEdit['descricao'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <!-- Instrutor -->
      <div class="admin-card">
        <div class="admin-card-header"><h5><i class="bi bi-person-badge-fill"></i> Instrutor</h5></div>
        <div class="admin-card-body">
          <div class="row g-3">
            <div class="col-md-8">
              <div class="admin-form-group">
                <label>Nome do Ministrador</label>
                <input type="text" class="form-control" name="ministrador_nome"
                       value="<?= h($cursoEdit['ministrador_nome'] ?? '') ?>" maxlength="150">
              </div>
              <div class="admin-form-group">
                <label>Bio do Ministrador</label>
                <textarea class="form-control" name="ministrador_bio" rows="3"><?= h($cursoEdit['ministrador_bio'] ?? '') ?></textarea>
              </div>
            </div>
            <div class="col-md-4">
              <div class="admin-form-group">
                <label>Foto do Ministrador</label>
                <?php $minFotoUrl = urlImagem($cursoEdit['ministrador_foto'] ?? ''); ?>
                <div class="upload-zone" onclick="document.getElementById('ministrador_foto').click()">
                  <i class="bi bi-person-circle" style="font-size:2rem;color:#ccc;"></i>
                  <p style="font-size:.8rem;color:#888;margin:4px 0 0;">Clique para upload</p>
                  <p style="font-size:.72rem;color:#bbb;margin:0;">JPG, PNG, WebP · Máx 5MB</p>
                </div>
                <input type="file" class="upload-input d-none" id="ministrador_foto" name="ministrador_foto"
                       data-preview="preview-min" accept="image/*">
                <img id="preview-min" class="upload-preview <?= $minFotoUrl ? 'visible' : '' ?>"
                     src="<?= $minFotoUrl ?: '' ?>" alt="Preview" style="border-radius:50%;">
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Coluna Lateral -->
    <div class="col-lg-4">
      <!-- Publicação -->
      <div class="admin-card">
        <div class="admin-card-header"><h5><i class="bi bi-toggle-on"></i> Publicação</h5></div>
        <div class="admin-card-body">
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="ativo" id="ativo"
                   <?= $cursoEdit['ativo'] ? 'checked' : '' ?> style="width:48px;height:24px;cursor:pointer;">
            <label class="form-check-label fw-bold ms-2" for="ativo">Curso Ativo (visível ao público)</label>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="inscricoes_abertas" id="inscricoes_abertas"
                   <?= $cursoEdit['inscricoes_abertas'] ? 'checked' : '' ?> style="width:48px;height:24px;cursor:pointer;">
            <label class="form-check-label fw-bold ms-2" for="inscricoes_abertas">Inscrições Abertas</label>
          </div>
        </div>
      </div>

      <!-- Capa -->
      <div class="admin-card">
        <div class="admin-card-header"><h5><i class="bi bi-image-fill"></i> Imagem de Capa</h5></div>
        <div class="admin-card-body">
          <?php $capaUrl = urlImagem($cursoEdit['capa_foto'] ?? ''); ?>
          <div class="upload-zone" onclick="document.getElementById('capa_foto').click()">
            <i class="bi bi-cloud-upload" style="font-size:2rem;color:#ccc;"></i>
            <p style="font-size:.82rem;color:#888;margin:4px 0 0;">Clique para selecionar</p>
            <p style="font-size:.72rem;color:#bbb;margin:0;">JPG, PNG, WebP · Máx 5MB</p>
          </div>
          <input type="file" class="upload-input d-none" id="capa_foto" name="capa_foto"
                 data-preview="preview-capa" accept="image/*">
          <img id="preview-capa" class="upload-preview w-100 <?= $capaUrl ? 'visible' : '' ?>"
               src="<?= $capaUrl ?: '' ?>" alt="Preview capa"
               style="object-fit:cover;max-height:180px;border-radius:8px;">
        </div>
      </div>

      <!-- Botões -->
      <div class="admin-card">
        <div class="admin-card-body d-grid gap-2">
          <button type="submit" class="btn" style="background:var(--primary);color:#fff;font-weight:700;border-radius:8px;padding:12px;">
            <i class="bi bi-check2-circle me-1"></i>
            <?= $cursoEdit['id'] ? 'Salvar Alterações' : 'Criar Curso' ?>
          </button>
          <a href="/admin/cursos.php" class="btn btn-outline-secondary" style="border-radius:8px;font-weight:700;padding:11px;">
            Cancelar
          </a>
          <?php if ($cursoEdit['id'] && $cursoEdit['slug']): ?>
          <a href="/curso.php?slug=<?= h($cursoEdit['slug']) ?>" target="_blank"
             class="btn btn-outline-secondary" style="border-radius:8px;padding:10px;font-size:.85rem;">
            <i class="bi bi-box-arrow-up-right me-1"></i>Ver Página Pública
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</form>

<?php else: ?>
<!-- ===== LISTAGEM ===== -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div></div>
  <a href="/admin/cursos.php?acao=criar" class="topbar-btn btn-primary-sm">
    <i class="bi bi-plus-circle"></i> Novo Curso
  </a>
</div>

<!-- Filtros -->
<form method="GET" class="admin-filters">
  <input type="text" class="form-control" name="q" placeholder="Buscar cursos..." value="<?= h($searchQ) ?>" style="max-width:250px;">
  <select class="form-select auto-filter" name="tipo" style="max-width:160px;">
    <option value="">Todos os tipos</option>
    <option value="presencial" <?= $filtroTipo === 'presencial' ? 'selected' : '' ?>>Presencial</option>
    <option value="online" <?= $filtroTipo === 'online' ? 'selected' : '' ?>>Online</option>
  </select>
  <select class="form-select auto-filter" name="ativo" style="max-width:150px;">
    <option value="">Todos os status</option>
    <option value="1" <?= $filtroAtivo === '1' ? 'selected' : '' ?>>Ativos</option>
    <option value="0" <?= $filtroAtivo === '0' ? 'selected' : '' ?>>Inativos</option>
  </select>
  <button type="submit" class="btn" style="background:var(--primary);color:#fff;border-radius:8px;font-weight:700;">
    <i class="bi bi-search"></i>
  </button>
  <?php if ($searchQ || $filtroTipo || $filtroAtivo !== ''): ?>
  <a href="/admin/cursos.php" class="btn btn-outline-secondary" style="border-radius:8px;">Limpar</a>
  <?php endif; ?>
  <a href="/api/exportar.php?tipo=cursos<?= $searchQ ? '&q='.urlencode($searchQ) : '' ?>" class="btn ms-auto"
     style="background:var(--secondary);color:#fff;border-radius:8px;font-weight:700;">
    <i class="bi bi-file-earmark-excel"></i> Exportar Excel
  </a>
</form>

<div class="admin-card">
  <div class="admin-card-header">
    <h5><i class="bi bi-mortarboard-fill"></i> Cursos (<?= count($cursos) ?>)</h5>
  </div>
  <div style="overflow-x:auto;">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Curso</th>
          <th>Tipo</th>
          <th>Data</th>
          <th>Vagas</th>
          <th>Inscritos</th>
          <th>Valor</th>
          <th>Status</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($cursos)): ?>
        <tr><td colspan="8" class="text-center py-4 text-muted">Nenhum curso encontrado</td></tr>
        <?php endif; ?>
        <?php foreach ($cursos as $c): ?>
        <tr>
          <td>
            <div style="font-weight:700;color:var(--primary);font-size:.9rem;"><?= h($c['nome']) ?></div>
            <?php if ($c['ministrador_nome']): ?>
            <div style="font-size:.75rem;color:#888;"><i class="bi bi-person"></i> <?= h($c['ministrador_nome']) ?></div>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge <?= $c['tipo'] === 'online' ? 'badge-tipo-online' : 'badge-tipo-presencial' ?>">
              <?= formatarTipoCurso($c['tipo']) ?>
            </span>
          </td>
          <td style="font-size:.83rem;"><?= $c['data_hora'] ? formatarDataHora($c['data_hora']) : '-' ?></td>
          <td style="font-size:.85rem;"><?= $c['vagas'] !== null ? $c['vagas'] : '∞' ?></td>
          <td>
            <span class="badge" style="background:var(--primary);color:#fff;"><?= $c['inscritos'] ?></span>
          </td>
          <td style="font-size:.85rem;font-weight:700;color:<?= (float)$c['valor'] > 0 ? 'var(--accent)' : '#28a745' ?>;">
            <?= (float)$c['valor'] > 0 ? formatarMoeda((float)$c['valor']) : 'Grátis' ?>
          </td>
          <td>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= gerarCSRF() ?>">
              <input type="hidden" name="acao" value="toggle_status">
              <input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button type="submit" class="badge border-0 cursor-pointer <?= $c['ativo'] ? 'badge-ativo' : 'badge-inativo' ?>"
                      title="Clique para alternar" style="cursor:pointer;">
                <?= $c['ativo'] ? 'Ativo' : 'Inativo' ?>
              </button>
            </form>
            <?php if (!$c['inscricoes_abertas']): ?>
            <span class="badge badge-inativo ms-1">Inscr. fechadas</span>
            <?php endif; ?>
          </td>
          <td>
            <div class="actions">
              <a href="/admin/cursos.php?editar=<?= $c['id'] ?>" class="btn-action edit">
                <i class="bi bi-pencil"></i>
              </a>
              <a href="/admin/inscricoes.php?curso_id=<?= $c['id'] ?>" class="btn-action view">
                <i class="bi bi-people"></i>
              </a>
              <a href="/curso.php?slug=<?= h($c['slug']) ?>" target="_blank" class="btn-action success">
                <i class="bi bi-box-arrow-up-right"></i>
              </a>
              <?php if (isAdmin()): ?>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Excluir este curso? Todos os inscritos serão removidos!')">
                <input type="hidden" name="csrf_token" value="<?= gerarCSRF() ?>">
                <input type="hidden" name="acao" value="deletar">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
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
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
