<?php
$siteName    = getConfig('site_nome', 'Sistema de Cursos');
$rodapeTexto = getConfig('rodape_texto', 'Todos os direitos reservados.');
$emailContato = getConfig('email_contato', '');
?>
<footer class="site-footer">
  <div class="container">
    <div class="row g-4">
      <div class="col-md-4">
        <h6><?= h($siteName) ?></h6>
        <p style="font-size:.88rem;opacity:.7;"><?= h(getConfig('site_descricao', '')) ?></p>
        <?php if ($emailContato): ?>
        <p style="font-size:.85rem;">
          <i class="bi bi-envelope" style="color:var(--secondary);"></i>
          <a href="mailto:<?= h($emailContato) ?>"><?= h($emailContato) ?></a>
        </p>
        <?php endif; ?>
      </div>
      <div class="col-md-3">
        <h6>Navegação</h6>
        <ul class="list-unstyled" style="font-size:.88rem;">
          <li class="mb-1"><a href="<?= BASE_PATH ?>/index.php"><i class="bi bi-chevron-right"></i> Início</a></li>
          <li class="mb-1"><a href="<?= BASE_PATH ?>/index.php#cursos"><i class="bi bi-chevron-right"></i> Cursos</a></li>
          <li class="mb-1"><a href="<?= BASE_PATH ?>/certificados.php"><i class="bi bi-chevron-right"></i> Certificados</a></li>
        </ul>
      </div>
      <div class="col-md-5">
        <h6>Formas de Pagamento</h6>
        <div class="d-flex flex-wrap gap-2 mt-1">
          <?php if (getConfig('pix_ativo') === '1'): ?>
          <span class="badge" style="background:rgba(255,255,255,.15);color:#fff;font-size:.8rem;padding:6px 12px;">
            <i class="bi bi-qr-code"></i> PIX
          </span>
          <?php endif; ?>
          <?php if (getConfig('transferencia_ativo') === '1'): ?>
          <span class="badge" style="background:rgba(255,255,255,.15);color:#fff;font-size:.8rem;padding:6px 12px;">
            <i class="bi bi-bank"></i> Transferência
          </span>
          <?php endif; ?>
          <?php if (getConfig('deposito_ativo') === '1'): ?>
          <span class="badge" style="background:rgba(255,255,255,.15);color:#fff;font-size:.8rem;padding:6px 12px;">
            <i class="bi bi-cash"></i> Depósito
          </span>
          <?php endif; ?>
          <?php if (getConfig('cartao_ativo') === '1'): ?>
          <span class="badge" style="background:rgba(255,255,255,.15);color:#fff;font-size:.8rem;padding:6px 12px;">
            <i class="bi bi-credit-card"></i> Cartão
          </span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="footer-divider"></div>
    <div class="footer-copy">
      &copy; <?= date('Y') ?> <?= h($siteName) ?>. <?= h($rodapeTexto) ?>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_PATH ?>/assets/js/main.js"></script>
</body>
</html>
