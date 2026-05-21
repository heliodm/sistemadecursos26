/* Sistema de Cursos - Scripts Admin */
document.addEventListener('DOMContentLoaded', function() {

  // Sidebar toggle mobile
  const sidebar = document.getElementById('admin-sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  const toggleBtn = document.getElementById('sidebar-toggle');

  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', function() {
      sidebar.classList.toggle('open');
      if (overlay) overlay.classList.toggle('active');
    });
  }
  if (overlay) {
    overlay.addEventListener('click', function() {
      sidebar.classList.remove('open');
      overlay.classList.remove('active');
    });
  }

  // Preview de imagem no upload
  document.querySelectorAll('.upload-input').forEach(input => {
    input.addEventListener('change', function() {
      const preview = document.getElementById(this.dataset.preview);
      if (!preview) return;
      const file = this.files[0];
      if (!file) return;
      if (!file.type.startsWith('image/')) {
        showAdminAlert('Selecione apenas arquivos de imagem.', 'danger');
        this.value = '';
        return;
      }
      if (file.size > 5 * 1024 * 1024) {
        showAdminAlert('Imagem muito grande. Máximo 5MB.', 'danger');
        this.value = '';
        return;
      }
      const reader = new FileReader();
      reader.onload = e => {
        preview.src = e.target.result;
        preview.classList.add('visible');
        preview.closest('.upload-zone')?.classList.add('has-file');
      };
      reader.readAsDataURL(file);
    });
  });

  // Confirmação antes de deletar
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', function(e) {
      const msg = this.dataset.confirm || 'Tem certeza?';
      if (!confirm(msg)) {
        e.preventDefault();
        return false;
      }
    });
  });

  // Seleção de todos da tabela
  const selectAll = document.getElementById('select-all');
  if (selectAll) {
    selectAll.addEventListener('change', function() {
      document.querySelectorAll('.select-row').forEach(cb => {
        cb.checked = this.checked;
      });
      updateBulkActions();
    });
    document.querySelectorAll('.select-row').forEach(cb => {
      cb.addEventListener('change', updateBulkActions);
    });
  }

  function updateBulkActions() {
    const selecionados = document.querySelectorAll('.select-row:checked').length;
    const bulk = document.getElementById('bulk-actions');
    if (bulk) {
      bulk.style.display = selecionados > 0 ? 'flex' : 'none';
      const counter = bulk.querySelector('.bulk-count');
      if (counter) counter.textContent = selecionados;
    }
  }

  // Tabs via URL hash
  const hash = window.location.hash;
  if (hash) {
    const tab = document.querySelector(`[data-bs-target="${hash}"]`);
    if (tab) new bootstrap.Tab(tab).show();
  }
  document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
    tab.addEventListener('shown.bs.tab', function() {
      history.replaceState(null, null, this.dataset.bsTarget);
    });
  });

  // Auto-submit filtros
  document.querySelectorAll('.auto-filter').forEach(el => {
    el.addEventListener('change', function() {
      this.closest('form')?.submit();
    });
  });

  // Flash messages auto-dismiss
  setTimeout(() => {
    document.querySelectorAll('.alert-dismissible:not(.alert-danger)').forEach(el => {
      el.classList.add('fade');
      setTimeout(() => el.remove(), 500);
    });
  }, 5000);

  // Tooltips Bootstrap
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el, { trigger: 'hover' });
  });

  // Status pagamento - atualização inline
  document.querySelectorAll('.status-select').forEach(sel => {
    sel.addEventListener('change', function() {
      const id = this.dataset.id;
      const status = this.value;
      fetch('/api/pagamento-status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, status, csrf: document.querySelector('meta[name="csrf-token"]')?.content })
      })
      .then(r => r.json())
      .then(data => {
        if (data.sucesso) {
          showAdminAlert('Status atualizado com sucesso.', 'success');
          const badge = document.querySelector(`.status-badge[data-id="${id}"]`);
          if (badge) {
            badge.className = `badge status-badge badge-${data.classe}`;
            badge.dataset.id = id;
            badge.textContent = data.label;
          }
        } else {
          showAdminAlert(data.erro || 'Erro ao atualizar status.', 'danger');
        }
      })
      .catch(() => showAdminAlert('Erro de comunicação.', 'danger'));
    });
  });

  // Alert helper
  function showAdminAlert(msg, type) {
    const existing = document.getElementById('admin-alert');
    if (existing) existing.remove();
    const div = document.createElement('div');
    div.id = 'admin-alert';
    div.className = `alert alert-${type} alert-dismissible position-fixed`;
    div.style.cssText = 'top:80px;right:24px;z-index:9999;min-width:280px;max-width:400px;';
    div.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    document.body.appendChild(div);
    setTimeout(() => { if (div.parentNode) { div.classList.add('fade'); setTimeout(() => div.remove(), 400); } }, 5000);
  }

  // Máscara CPF admin
  document.querySelectorAll('.mask-cpf').forEach(input => {
    input.addEventListener('input', function() {
      let v = this.value.replace(/\D/g, '').slice(0, 11);
      v = v.replace(/(\d{3})(\d)/, '$1.$2');
      v = v.replace(/(\d{3})(\d)/, '$1.$2');
      v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
      this.value = v;
    });
  });

  // Gerador de slug automático
  const nomeInput = document.getElementById('nome_curso');
  const slugInput = document.getElementById('slug_curso');
  if (nomeInput && slugInput && !slugInput.value) {
    nomeInput.addEventListener('input', function() {
      slugInput.value = slugify(this.value);
    });
  }
  function slugify(str) {
    const mapa = { 'á':'a','à':'a','ã':'a','â':'a','é':'e','ê':'e','í':'i','ó':'o','õ':'o','ô':'o','ú':'u','ü':'u','ç':'c','ñ':'n' };
    return str.toLowerCase()
      .replace(/[áàãâäéèêëíìîïóòõôöúùûüçñ]/g, c => mapa[c] || c)
      .replace(/[^a-z0-9\s-]/g, '')
      .replace(/[\s-]+/g, '-')
      .replace(/^-|-$/g, '');
  }

  // Certificado - emitir
  document.querySelectorAll('.btn-emitir-cert').forEach(btn => {
    btn.addEventListener('click', function() {
      const inscricaoId = this.dataset.inscricao;
      if (!confirm('Emitir certificado para este inscrito?')) return;
      fetch('/api/emitir-certificado.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ inscricao_id: inscricaoId, csrf: document.querySelector('meta[name="csrf-token"]')?.content })
      })
      .then(r => r.json())
      .then(data => {
        if (data.sucesso) {
          showAdminAlert('Certificado emitido! Código: ' + data.codigo, 'success');
          this.closest('tr')?.querySelector('.cert-status')?.replaceWith(
            Object.assign(document.createElement('span'), { className: 'badge badge-confirmado', textContent: 'Emitido' })
          );
          this.style.display = 'none';
        } else {
          showAdminAlert(data.erro || 'Erro ao emitir certificado.', 'danger');
        }
      });
    });
  });
});
