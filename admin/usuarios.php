<?php
$pageTitle = 'Gerenciar Usuários';
require_once __DIR__ . '/includes/header.php';
requireAdmin();

$db    = getDB();
$erros = [];

// Processar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificarCSRF($_POST['csrf_token'] ?? '')) {
        redirect('/admin/usuarios.php', 'Token inválido.', 'danger');
    }
    $postAcao = sanitize($_POST['acao'] ?? '');

    if ($postAcao === 'criar') {
        $nome  = sanitize($_POST['nome'] ?? '');
        $email = strtolower(sanitize($_POST['email'] ?? ''));
        $senha = $_POST['senha'] ?? '';
        $conf  = $_POST['confirmar_senha'] ?? '';
        $tipo  = in_array($_POST['tipo'] ?? '', ['admin','usuario']) ? $_POST['tipo'] : 'usuario';

        if (empty($nome))  $erros[] = 'Nome é obrigatório.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $erros[] = 'E-mail inválido.';
        if (strlen($senha) < 8) $erros[] = 'Senha deve ter ao menos 8 caracteres.';
        if ($senha !== $conf)   $erros[] = 'Senhas não coincidem.';

        if (empty($erros)) {
            $s = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $s->execute([$email]);
            if ($s->fetch()) {
                $erros[] = 'E-mail já cadastrado.';
            } else {
                $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->prepare("INSERT INTO usuarios (nome, email, senha, tipo) VALUES (?,?,?,?)")->execute([$nome, $email, $hash, $tipo]);
                redirect('/admin/usuarios.php', 'Usuário criado com sucesso.', 'success');
            }
        }
    }

    if ($postAcao === 'editar') {
        $id    = (int)$_POST['usuario_id'];
        $nome  = sanitize($_POST['nome'] ?? '');
        $email = strtolower(sanitize($_POST['email'] ?? ''));
        $tipo  = in_array($_POST['tipo'] ?? '', ['admin','usuario']) ? $_POST['tipo'] : 'usuario';
        $senha = $_POST['senha'] ?? '';
        $conf  = $_POST['confirmar_senha'] ?? '';
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if (empty($nome))  $erros[] = 'Nome é obrigatório.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $erros[] = 'E-mail inválido.';

        // Verificar se é o único admin
        if ($tipo !== 'admin') {
            $nAdmins = (int)$db->query("SELECT COUNT(*) FROM usuarios WHERE tipo='admin' AND ativo=1")->fetchColumn();
            $isCurrentAdmin = $db->prepare("SELECT tipo FROM usuarios WHERE id=?");
            $isCurrentAdmin->execute([$id]);
            $curTipo = $isCurrentAdmin->fetchColumn();
            if ($curTipo === 'admin' && $nAdmins <= 1) {
                $erros[] = 'Não é possível rebaixar o único administrador ativo.';
            }
        }

        if (!empty($senha)) {
            if (strlen($senha) < 8) $erros[] = 'Senha deve ter ao menos 8 caracteres.';
            if ($senha !== $conf)   $erros[] = 'Senhas não coincidem.';
        }

        if (empty($erros)) {
            if (!empty($senha)) {
                $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->prepare("UPDATE usuarios SET nome=?, email=?, senha=?, tipo=?, ativo=? WHERE id=?")->execute([$nome, $email, $hash, $tipo, $ativo, $id]);
            } else {
                $db->prepare("UPDATE usuarios SET nome=?, email=?, tipo=?, ativo=? WHERE id=?")->execute([$nome, $email, $tipo, $ativo, $id]);
            }
            redirect('/admin/usuarios.php', 'Usuário atualizado com sucesso.', 'success');
        }
    }

    if ($postAcao === 'deletar') {
        $id = (int)$_POST['id'];
        $usuarioLogado = getUsuarioLogado();
        if ($id === (int)($usuarioLogado['id'] ?? 0)) {
            redirect('/admin/usuarios.php', 'Você não pode remover sua própria conta.', 'danger');
        }
        $db->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);
        redirect('/admin/usuarios.php', 'Usuário removido.', 'success');
    }
}

// Editar
$editUser = null;
$editId   = (int)($_GET['editar'] ?? 0);
if ($editId > 0) {
    $s = $db->prepare("SELECT id, nome, email, tipo, ativo FROM usuarios WHERE id = ?");
    $s->execute([$editId]);
    $editUser = $s->fetch();
}

// Listar
$usuarios = $db->query("SELECT id, nome, email, tipo, ativo, criado_em FROM usuarios ORDER BY tipo ASC, nome ASC")->fetchAll();
?>

<div class="row g-4">
  <!-- Formulário -->
  <div class="col-lg-5">
    <div class="admin-card">
      <div class="admin-card-header">
        <h5><i class="bi bi-person-plus-fill"></i> <?= $editUser ? 'Editar Usuário' : 'Novo Usuário' ?></h5>
        <?php if ($editUser): ?>
        <a href="<?= BASE_PATH ?>/admin/usuarios.php" class="btn-action view"><i class="bi bi-x"></i> Cancelar</a>
        <?php endif; ?>
      </div>
      <div class="admin-card-body">
        <?php if (!empty($erros)): ?>
        <div class="alert alert-danger mb-3">
          <ul class="mb-0"><?php foreach ($erros as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>

        <form method="POST" novalidate>
          <input type="hidden" name="csrf_token" value="<?= gerarCSRF() ?>">
          <input type="hidden" name="acao" value="<?= $editUser ? 'editar' : 'criar' ?>">
          <?php if ($editUser): ?>
          <input type="hidden" name="usuario_id" value="<?= $editUser['id'] ?>">
          <?php endif; ?>

          <div class="admin-form-group">
            <label>Nome Completo <span class="required">*</span></label>
            <input type="text" class="form-control" name="nome"
                   value="<?= h($editUser['nome'] ?? '') ?>" required maxlength="150">
          </div>
          <div class="admin-form-group">
            <label>E-mail <span class="required">*</span></label>
            <input type="email" class="form-control" name="email"
                   value="<?= h($editUser['email'] ?? '') ?>" required maxlength="150">
          </div>
          <div class="admin-form-group">
            <label>
              <?= $editUser ? 'Nova Senha (deixe em branco para manter)' : 'Senha *' ?>
            </label>
            <input type="password" class="form-control" name="senha" minlength="8"
                   placeholder="Mínimo 8 caracteres" <?= $editUser ? '' : 'required' ?>>
          </div>
          <div class="admin-form-group">
            <label>Confirmar Senha <?= $editUser ? '' : '*' ?></label>
            <input type="password" class="form-control" name="confirmar_senha" <?= $editUser ? '' : 'required' ?>>
          </div>
          <div class="admin-form-group">
            <label>Tipo de Usuário <span class="required">*</span></label>
            <select class="form-select" name="tipo">
              <option value="usuario" <?= ($editUser['tipo'] ?? '') === 'usuario' ? 'selected' : '' ?>>
                Usuário (Cursos, Inscrições, Pagamentos, Certificados)
              </option>
              <option value="admin" <?= ($editUser['tipo'] ?? '') === 'admin' ? 'selected' : '' ?>>
                Administrador (Acesso Total)
              </option>
            </select>
          </div>
          <?php if ($editUser && $editUser['id'] !== (int)$usuario['id']): ?>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="ativo" id="ativo_user"
                   <?= $editUser['ativo'] ? 'checked' : '' ?> style="width:44px;height:22px;">
            <label class="form-check-label fw-bold ms-2" for="ativo_user">Usuário Ativo</label>
          </div>
          <?php endif; ?>

          <button type="submit" class="btn w-100" style="background:var(--primary);color:#fff;font-weight:700;border-radius:8px;padding:11px;">
            <i class="bi bi-check2 me-1"></i><?= $editUser ? 'Salvar Alterações' : 'Criar Usuário' ?>
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Lista -->
  <div class="col-lg-7">
    <div class="admin-card">
      <div class="admin-card-header">
        <h5><i class="bi bi-people-fill"></i> Usuários (<?= count($usuarios) ?>)</h5>
        <a href="<?= BASE_PATH ?>/api/exportar.php?tipo=usuarios" class="topbar-btn btn-primary-sm">
          <i class="bi bi-file-earmark-excel"></i> Exportar
        </a>
      </div>
      <div style="overflow-x:auto;">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Nome</th>
              <th>E-mail</th>
              <th>Tipo</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($usuarios as $u): ?>
            <tr>
              <td>
                <div style="font-weight:700;font-size:.88rem;color:var(--primary);"><?= h($u['nome']) ?></div>
                <div style="font-size:.72rem;color:#888;"><?= formatarData($u['criado_em'], 'd/m/Y') ?></div>
                <?php if ($u['id'] === (int)$usuario['id']): ?>
                <span class="badge" style="background:var(--secondary);font-size:.65rem;">Você</span>
                <?php endif; ?>
              </td>
              <td style="font-size:.83rem;"><?= h($u['email']) ?></td>
              <td>
                <span class="badge <?= $u['tipo'] === 'admin' ? 'badge-admin' : 'badge-usuario' ?>">
                  <?= $u['tipo'] === 'admin' ? 'Admin' : 'Usuário' ?>
                </span>
              </td>
              <td>
                <span class="badge <?= $u['ativo'] ? 'badge-ativo' : 'badge-inativo' ?>">
                  <?= $u['ativo'] ? 'Ativo' : 'Inativo' ?>
                </span>
              </td>
              <td>
                <div class="actions">
                  <a href="<?= BASE_PATH ?>/admin/usuarios.php?editar=<?= $u['id'] ?>" class="btn-action edit">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <?php if ($u['id'] !== (int)$usuario['id']): ?>
                  <form method="POST" style="display:inline;" onsubmit="return confirm(<?= h(json_encode('Remover usuário ' . $u['nome'] . '?')) ?>)">
                    <input type="hidden" name="csrf_token" value="<?= gerarCSRF() ?>">
                    <input type="hidden" name="acao" value="deletar">
                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
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

    <!-- Info permissões -->
    <div class="admin-card mt-3">
      <div class="admin-card-header"><h5><i class="bi bi-shield-check"></i> Permissões por Tipo</h5></div>
      <div class="admin-card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <div style="background:rgba(27,58,107,.06);border-radius:8px;padding:14px;">
              <div class="fw-bold mb-2" style="color:var(--primary);"><i class="bi bi-shield-fill-check me-1"></i>Administrador</div>
              <ul style="font-size:.82rem;color:#555;padding-left:16px;margin:0;">
                <li>Acesso total ao sistema</li>
                <li>Gerenciar usuários</li>
                <li>Configurações do sistema</li>
                <li>Excluir registros</li>
                <li>Gerenciar cursos, inscrições, certificados</li>
              </ul>
            </div>
          </div>
          <div class="col-md-6">
            <div style="background:rgba(201,162,39,.08);border-radius:8px;padding:14px;">
              <div class="fw-bold mb-2" style="color:var(--secondary);"><i class="bi bi-person-check me-1"></i>Usuário</div>
              <ul style="font-size:.82rem;color:#555;padding-left:16px;margin:0;">
                <li>Criar e editar cursos</li>
                <li>Gerenciar inscrições</li>
                <li>Gerenciar pagamentos</li>
                <li>Emitir certificados</li>
                <li>Sem acesso a usuários e configurações</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
