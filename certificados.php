<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Certificados - ' . getConfig('site_nome', 'Sistema de Cursos');
$pageDesc  = 'Acesse e valide seus certificados de participação nos cursos.';

$db = getDB();
$codigo   = strtoupper(sanitize($_GET['codigo'] ?? ''));
$resultado = null;
$erroBusca = '';

if ($codigo) {
    $stmt = $db->prepare("
        SELECT cert.*, c.nome AS curso_nome, c.carga_horaria, c.data_hora, c.data_fim
        FROM certificados cert
        JOIN cursos c ON c.id = cert.curso_id
        WHERE cert.codigo_unico = ?
        LIMIT 1
    ");
    $stmt->execute([$codigo]);
    $resultado = $stmt->fetch();
    if (!$resultado) {
        $erroBusca = 'Certificado não encontrado. Verifique o código digitado.';
    } elseif (!$resultado['valido']) {
        $erroBusca = 'Este certificado foi invalidado.';
        $resultado = null;
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero -->
<section class="cert-hero">
  <div class="container">
    <div class="mb-3">
      <i class="bi bi-patch-check-fill" style="font-size:3.5rem;color:var(--secondary);"></i>
    </div>
    <h1>Certificados</h1>
    <p class="lead" style="opacity:.85;">Acesse e valide certificados de participação nos cursos</p>
  </div>
</section>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-6">
      <!-- Busca de Certificado -->
      <div class="cert-search-card shadow-lg">
        <h4><i class="bi bi-search me-2" style="color:var(--secondary);"></i>Validar Certificado</h4>
        <p style="font-size:.9rem;color:#666;margin-bottom:16px;">Digite o código único do certificado para acessar e verificar sua autenticidade.</p>
        <form method="GET" action="<?= BASE_PATH ?>/certificados.php" novalidate>
          <div class="input-group mb-3">
            <input type="text" class="form-control" name="codigo"
                   value="<?= h($codigo) ?>"
                   placeholder="Ex: ABCD1234-EFGH5678"
                   style="border-radius:8px 0 0 8px;text-transform:uppercase;font-family:monospace;letter-spacing:1px;"
                   maxlength="25" required>
            <button class="btn" type="submit" style="background:var(--primary);color:#fff;border-radius:0 8px 8px 0;font-weight:700;">
              <i class="bi bi-search"></i> Validar
            </button>
          </div>
        </form>

        <?php if ($erroBusca): ?>
        <div class="cert-resultado">
          <i class="bi bi-x-circle-fill cert-invalido" style="font-size:2.5rem;display:block;margin-bottom:8px;"></i>
          <strong class="cert-invalido d-block mb-1">Certificado Inválido</strong>
          <p style="font-size:.9rem;color:#666;margin:0;"><?= h($erroBusca) ?></p>
        </div>
        <?php endif; ?>

        <?php if ($resultado): ?>
        <div class="cert-resultado">
          <i class="bi bi-patch-check-fill cert-valido" style="font-size:2.5rem;display:block;margin-bottom:8px;"></i>
          <strong class="cert-valido d-block mb-1" style="font-size:1.1rem;">Certificado Válido</strong>
          <div class="mt-3 text-start" style="font-size:.9rem;">
            <div class="mb-2 d-flex align-items-start gap-2">
              <i class="bi bi-person-fill mt-1" style="color:var(--secondary);width:18px;flex-shrink:0;"></i>
              <div><strong>Participante:</strong><br><?= h($resultado['nome_completo']) ?></div>
            </div>
            <div class="mb-2 d-flex align-items-start gap-2">
              <i class="bi bi-mortarboard-fill mt-1" style="color:var(--secondary);width:18px;flex-shrink:0;"></i>
              <div><strong>Curso:</strong><br><?= h($resultado['curso_nome']) ?></div>
            </div>
            <?php if ($resultado['carga_horaria']): ?>
            <div class="mb-2 d-flex align-items-start gap-2">
              <i class="bi bi-clock-fill mt-1" style="color:var(--secondary);width:18px;flex-shrink:0;"></i>
              <div><strong>Carga Horária:</strong><br><?= h($resultado['carga_horaria']) ?> horas</div>
            </div>
            <?php endif; ?>
            <div class="mb-2 d-flex align-items-start gap-2">
              <i class="bi bi-calendar-check-fill mt-1" style="color:var(--secondary);width:18px;flex-shrink:0;"></i>
              <div><strong>Emitido em:</strong><br><?= formatarData($resultado['emitido_em'], 'd/m/Y') ?></div>
            </div>
            <div class="d-flex align-items-start gap-2">
              <i class="bi bi-key-fill mt-1" style="color:var(--secondary);width:18px;flex-shrink:0;"></i>
              <div><strong>Código:</strong><br><code style="font-size:.85rem;"><?= h($resultado['codigo_unico']) ?></code></div>
            </div>
          </div>
          <div class="mt-3 d-flex gap-2 justify-content-center flex-wrap">
            <a href="<?= BASE_PATH ?>/certificado-visualizar.php?codigo=<?= h($codigo) ?>" target="_blank"
               class="btn btn-sm" style="background:var(--primary);color:#fff;border-radius:8px;font-weight:700;">
              <i class="bi bi-eye me-1"></i>Visualizar Certificado
            </a>
            <a href="<?= BASE_PATH ?>/certificado-visualizar.php?codigo=<?= h($codigo) ?>&download=1" target="_blank"
               class="btn btn-sm" style="background:var(--secondary);color:#fff;border-radius:8px;font-weight:700;">
              <i class="bi bi-download me-1"></i>Baixar PDF
            </a>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Informações -->
      <div class="text-center mt-4 p-4" style="background:var(--gray-light);border-radius:12px;">
        <i class="bi bi-shield-check" style="font-size:2rem;color:var(--primary);"></i>
        <h6 class="mt-2 mb-1" style="color:var(--primary);font-weight:bold;">Verificação Segura</h6>
        <p style="font-size:.85rem;color:#666;margin:0;">
          Todos os certificados emitidos por esta plataforma podem ser verificados aqui.
          Cada certificado possui um código único que garante sua autenticidade.
        </p>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
