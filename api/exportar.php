<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin('/login.php');

$tipo    = sanitize($_GET['tipo'] ?? '');
$cursoId = (int)($_GET['curso_id'] ?? 0);
$status  = sanitize($_GET['status'] ?? '');
$searchQ = sanitize($_GET['q'] ?? '');

$db = getDB();

$filename = 'exportacao-' . $tipo . '-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
// BOM para Excel reconhecer UTF-8
fputs($out, "\xEF\xBB\xBF");

if ($tipo === 'inscricoes' || $tipo === 'pagamentos') {
    $where  = [];
    $params = [];
    if ($cursoId > 0) { $where[] = "i.curso_id = ?"; $params[] = $cursoId; }
    if ($status && in_array($status, ['pendente','confirmado','cancelado'])) { $where[] = "i.status_pagamento = ?"; $params[] = $status; }
    if ($searchQ) { $where[] = "(i.nome_completo LIKE ? OR i.email LIKE ?)"; $params[] = "%$searchQ%"; $params[] = "%$searchQ%"; }
    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $db->prepare("
        SELECT i.id, i.nome_completo, i.profissao, i.email, i.telefone, i.cpf, i.rg,
               i.formacao, i.endereco, i.forma_pagamento, i.status_pagamento,
               i.observacoes, i.criado_em, c.nome AS curso_nome, c.valor AS curso_valor
        FROM inscricoes i
        JOIN cursos c ON c.id = i.curso_id
        $whereSQL
        ORDER BY i.criado_em DESC
    ");
    $stmt->execute($params);
    $dados = $stmt->fetchAll();

    $header = ['#', 'Nome Completo', 'Profissão', 'E-mail', 'Telefone', 'CPF', 'RG', 'Formação', 'Endereço', 'Curso', 'Valor', 'Forma Pagamento', 'Status', 'Observações', 'Data Inscrição'];
    fputcsv($out, $header, ';');

    foreach ($dados as $row) {
        fputcsv($out, [
            $row['id'],
            $row['nome_completo'],
            $row['profissao'] ?? '',
            $row['email'],
            $row['telefone'] ?? '',
            $row['cpf'] ?? '',
            $row['rg'] ?? '',
            $row['formacao'] ?? '',
            $row['endereco'] ?? '',
            $row['curso_nome'],
            number_format((float)$row['curso_valor'], 2, ',', '.'),
            formatarFormaPagamento($row['forma_pagamento']),
            formatarStatusPagamento($row['status_pagamento'])['label'],
            $row['observacoes'] ?? '',
            date('d/m/Y H:i', strtotime($row['criado_em'])),
        ], ';');
    }

} elseif ($tipo === 'cursos') {
    $where  = [];
    $params = [];
    if ($searchQ) { $where[] = "nome LIKE ?"; $params[] = "%$searchQ%"; }
    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $db->prepare("
        SELECT c.*,
               (SELECT COUNT(*) FROM inscricoes WHERE curso_id = c.id AND status_pagamento != 'cancelado') AS inscritos,
               (SELECT COUNT(*) FROM inscricoes WHERE curso_id = c.id AND status_pagamento = 'confirmado') AS confirmados
        FROM cursos c $whereSQL ORDER BY c.criado_em DESC
    ");
    $stmt->execute($params);
    $dados = $stmt->fetchAll();

    $header = ['ID', 'Nome', 'Tipo', 'Data Início', 'Data Fim', 'Local', 'Carga Horária (h)', 'Vagas', 'Valor', 'Inscritos', 'Confirmados', 'Ativo', 'Inscrições Abertas', 'Criado em'];
    fputcsv($out, $header, ';');

    foreach ($dados as $row) {
        fputcsv($out, [
            $row['id'],
            $row['nome'],
            formatarTipoCurso($row['tipo']),
            $row['data_hora'] ? date('d/m/Y H:i', strtotime($row['data_hora'])) : '',
            $row['data_fim'] ? date('d/m/Y H:i', strtotime($row['data_fim'])) : '',
            $row['local'] ?? '',
            $row['carga_horaria'] ?? '',
            $row['vagas'] ?? 'Ilimitado',
            number_format((float)$row['valor'], 2, ',', '.'),
            $row['inscritos'],
            $row['confirmados'],
            $row['ativo'] ? 'Sim' : 'Não',
            $row['inscricoes_abertas'] ? 'Sim' : 'Não',
            date('d/m/Y H:i', strtotime($row['criado_em'])),
        ], ';');
    }

} elseif ($tipo === 'usuarios') {
    requireAdmin();
    $stmt = $db->query("SELECT id, nome, email, tipo, ativo, criado_em FROM usuarios ORDER BY tipo ASC, nome ASC");
    $dados = $stmt->fetchAll();

    $header = ['ID', 'Nome', 'E-mail', 'Tipo', 'Ativo', 'Criado em'];
    fputcsv($out, $header, ';');

    foreach ($dados as $row) {
        fputcsv($out, [
            $row['id'],
            $row['nome'],
            $row['email'],
            $row['tipo'] === 'admin' ? 'Administrador' : 'Usuário',
            $row['ativo'] ? 'Sim' : 'Não',
            date('d/m/Y H:i', strtotime($row['criado_em'])),
        ], ';');
    }

} elseif ($tipo === 'certificados') {
    $where  = [];
    $params = [];
    if ($cursoId > 0) { $where[] = "cert.curso_id = ?"; $params[] = $cursoId; }
    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $db->prepare("
        SELECT cert.codigo_unico, cert.nome_completo, c.nome AS curso_nome,
               c.carga_horaria, cert.emitido_em, cert.valido
        FROM certificados cert
        JOIN cursos c ON c.id = cert.curso_id
        $whereSQL
        ORDER BY cert.emitido_em DESC
    ");
    $stmt->execute($params);
    $dados = $stmt->fetchAll();

    $header = ['Código', 'Nome do Participante', 'Curso', 'Carga Horária (h)', 'Emitido em', 'Válido'];
    fputcsv($out, $header, ';');

    foreach ($dados as $row) {
        fputcsv($out, [
            $row['codigo_unico'],
            $row['nome_completo'],
            $row['curso_nome'],
            $row['carga_horaria'] ?? '',
            date('d/m/Y H:i', strtotime($row['emitido_em'])),
            $row['valido'] ? 'Sim' : 'Não',
        ], ';');
    }
} else {
    fputcsv($out, ['Tipo de exportação inválido'], ';');
}

fclose($out);
exit;
