<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$codigo = strtoupper(sanitize($_GET['codigo'] ?? ''));
$download = isset($_GET['download']);

if (!$codigo) {
    redirect('/certificados.php', 'Código inválido.', 'danger');
}

$db   = getDB();
$stmt = $db->prepare("
    SELECT cert.*, c.nome AS curso_nome, c.carga_horaria, c.data_hora
    FROM certificados cert
    JOIN cursos c ON c.id = cert.curso_id
    WHERE cert.codigo_unico = ? AND cert.valido = 1
    LIMIT 1
");
$stmt->execute([$codigo]);
$cert = $stmt->fetch();

if (!$cert) {
    redirect('/certificados.php', 'Certificado não encontrado ou inválido.', 'danger');
}

// Configurações do certificado
$bgCert    = urlImagem(getConfig('cert_background'), '');
$titulo    = getConfig('cert_titulo', 'CERTIFICADO DE PARTICIPAÇÃO');
$texto     = getConfig('cert_texto', 'Certificamos que {NOME} participou do curso {CURSO}.');
$assNome   = getConfig('cert_assinatura_nome', '');
$assCargo  = getConfig('cert_assinatura_cargo', '');
$assImg    = urlImagem(getConfig('cert_assinatura_imagem'), '');
$certLogo  = urlImagem(getConfig('cert_logo'), '');
$cidade    = getConfig('cert_cidade', '');
$validText = getConfig('cert_validade_texto', 'Código: {CODIGO}');

// Substituir variáveis no texto
$dataRealizacao = $cert['data_hora'] ? formatarData($cert['data_hora'], 'd/m/Y') : formatarData($cert['emitido_em'], 'd/m/Y');
$replacements = [
    '{NOME}'          => $cert['nome_completo'],
    '{CURSO}'         => $cert['curso_nome'],
    '{DATA}'          => $dataRealizacao,
    '{CARGA_HORARIA}' => $cert['carga_horaria'] ?? '',
    '{CODIGO}'        => $cert['codigo_unico'],
    '{CIDADE}'        => $cidade,
    '{DATA_EMISSAO}'  => formatarData($cert['emitido_em'], 'd/m/Y'),
];
$textoFinal    = str_replace(array_keys($replacements), array_values($replacements), $texto);
$validFinal    = str_replace(array_keys($replacements), array_values($replacements), $validText);

if ($download) {
    // Para download, usar mPDF ou simplesmente mandar para impressão
    // Marcamos com print mode
    $printMode = true;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Certificado - <?= h($cert['nome_completo']) ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: Tahoma, Geneva, Verdana, sans-serif;
      background: #888;
      display: flex;
      flex-direction: column;
      align-items: center;
      min-height: 100vh;
      padding: 30px 20px;
    }
    .cert-toolbar {
      width: 100%;
      max-width: 960px;
      display: flex;
      gap: 10px;
      justify-content: flex-end;
      margin-bottom: 16px;
    }
    .cert-toolbar a, .cert-toolbar button {
      background: #1B3A6B;
      color: #fff;
      border: none;
      border-radius: 8px;
      padding: 8px 18px;
      font-family: Tahoma, sans-serif;
      font-size: .88rem;
      font-weight: 700;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    .cert-toolbar a:hover, .cert-toolbar button:hover { background: #C9A227; }

    /* Certificado A4 Paisagem */
    .certificado {
      width: 960px;
      height: 679px; /* A4 paisagem proporcional (297x210mm ~ 960x679px a 82dpi) */
      background: #fff;
      position: relative;
      overflow: hidden;
      box-shadow: 0 8px 32px rgba(0,0,0,.4);
      border-radius: 4px;
    }
    .cert-bg {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .cert-content {
      position: absolute;
      inset: 0;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 50px 80px;
      text-align: center;
    }
    .cert-logo { max-height: 70px; max-width: 200px; margin-bottom: 16px; object-fit: contain; }
    .cert-titulo {
      font-size: 2rem;
      font-weight: bold;
      color: #1B3A6B;
      letter-spacing: 3px;
      text-transform: uppercase;
      margin-bottom: 12px;
    }
    .cert-linha { width: 80px; height: 3px; background: #C9A227; margin: 0 auto 16px; border-radius: 2px; }
    .cert-texto {
      font-size: 1.05rem;
      color: #333;
      line-height: 1.7;
      max-width: 700px;
      margin-bottom: 20px;
    }
    .cert-nome {
      font-size: 1.6rem;
      font-weight: bold;
      color: #1B3A6B;
      margin-bottom: 4px;
    }
    .cert-rodape {
      position: absolute;
      bottom: 28px;
      left: 0;
      right: 0;
      padding: 0 80px;
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
    }
    .cert-assinatura { text-align: center; }
    .cert-assinatura img { max-width: 140px; max-height: 50px; object-fit: contain; display: block; margin: 0 auto 4px; }
    .cert-assinatura .ass-linha { width: 160px; border-top: 1.5px solid #333; margin: 0 auto 3px; }
    .cert-assinatura .ass-nome { font-size: .78rem; font-weight: bold; color: #333; }
    .cert-assinatura .ass-cargo { font-size: .72rem; color: #666; }
    .cert-codigo {
      font-size: .72rem;
      color: #666;
      font-family: monospace;
      text-align: center;
    }
    @media print {
      body { background: #fff; padding: 0; }
      .cert-toolbar { display: none !important; }
      .certificado {
        width: 100%;
        height: auto;
        aspect-ratio: 297 / 210;
        box-shadow: none;
        page-break-inside: avoid;
      }
      @page { size: A4 landscape; margin: 0; }
    }
  </style>
</head>
<body>
  <div class="cert-toolbar no-print">
    <a href="/certificados.php"><i>←</i> Voltar</a>
    <button onclick="window.print()"><i>⎙</i> Imprimir / Salvar PDF</button>
  </div>

  <div class="certificado" id="certificado">
    <?php if ($bgCert): ?>
    <img class="cert-bg" src="<?= h($bgCert) ?>" alt="Background">
    <?php else: ?>
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,#f8f9fa 0%,#ffffff 50%,#f0f4f8 100%);border:12px solid #1B3A6B;">
      <div style="position:absolute;inset:8px;border:3px solid #C9A227;"></div>
    </div>
    <?php endif; ?>

    <div class="cert-content">
      <?php if ($certLogo): ?>
      <img class="cert-logo" src="<?= h($certLogo) ?>" alt="Logo">
      <?php endif; ?>

      <div class="cert-titulo"><?= h($titulo) ?></div>
      <div class="cert-linha"></div>
      <div class="cert-texto"><?= nl2br(h($textoFinal)) ?></div>
    </div>

    <div class="cert-rodape">
      <?php if ($assNome): ?>
      <div class="cert-assinatura">
        <?php if ($assImg): ?>
        <img src="<?= h($assImg) ?>" alt="Assinatura">
        <?php endif; ?>
        <div class="ass-linha"></div>
        <div class="ass-nome"><?= h($assNome) ?></div>
        <?php if ($assCargo): ?>
        <div class="ass-cargo"><?= h($assCargo) ?></div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <div class="cert-codigo">
        <?= h($validFinal) ?>
      </div>
    </div>
  </div>

  <?php if ($download): ?>
  <script>setTimeout(() => window.print(), 500);</script>
  <?php endif; ?>
</body>
</html>
