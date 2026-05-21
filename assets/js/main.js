/* Sistema de Cursos - Scripts Públicos */
document.addEventListener('DOMContentLoaded', function() {

  // Filtro de cursos por tipo
  const filtrosBtns = document.querySelectorAll('.btn-filter');
  const cursosCards = document.querySelectorAll('.curso-card-wrapper');

  filtrosBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      filtrosBtns.forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      const filtro = this.dataset.filter;
      let visivel = 0;
      cursosCards.forEach(card => {
        const tipo = card.dataset.tipo;
        const mostrar = filtro === 'todos' || tipo === filtro;
        card.style.display = mostrar ? '' : 'none';
        if (mostrar) visivel++;
      });
      const noResults = document.getElementById('no-results');
      if (noResults) noResults.style.display = visivel === 0 ? 'block' : 'none';
    });
  });

  // Busca de cursos
  const searchInput = document.getElementById('search-cursos');
  if (searchInput) {
    searchInput.addEventListener('input', debounce(function() {
      const termo = this.value.toLowerCase().trim();
      let visivel = 0;
      cursosCards.forEach(card => {
        const titulo = (card.dataset.titulo || '').toLowerCase();
        const ministrador = (card.dataset.ministrador || '').toLowerCase();
        const mostrar = !termo || titulo.includes(termo) || ministrador.includes(termo);
        card.style.display = mostrar ? '' : 'none';
        if (mostrar) visivel++;
      });
      const noResults = document.getElementById('no-results');
      if (noResults) noResults.style.display = visivel === 0 ? 'block' : 'none';
    }, 300));

    // Submissão do form de busca
    const searchForm = document.getElementById('form-busca');
    if (searchForm) {
      searchForm.addEventListener('submit', function(e) {
        e.preventDefault();
        searchInput.dispatchEvent(new Event('input'));
        const cursosSection = document.getElementById('cursos');
        if (cursosSection) {
          cursosSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    }
  }

  // Seleção de forma de pagamento
  const pagamentoOpcoes = document.querySelectorAll('.pagamento-opcao:not(.em-breve)');
  const pagamentoInput = document.getElementById('forma_pagamento');

  pagamentoOpcoes.forEach(opcao => {
    opcao.addEventListener('click', function() {
      pagamentoOpcoes.forEach(o => o.classList.remove('selecionado'));
      this.classList.add('selecionado');
      const forma = this.dataset.forma;
      if (pagamentoInput) pagamentoInput.value = forma;

      // Mostrar instruções da forma selecionada
      document.querySelectorAll('.pagamento-instrucoes').forEach(el => {
        el.classList.remove('visivel');
      });
      const instrucoes = document.getElementById('instrucoes-' + forma);
      if (instrucoes) instrucoes.classList.add('visivel');
    });
  });

  // Máscara CPF
  const cpfInput = document.getElementById('cpf');
  if (cpfInput) {
    cpfInput.addEventListener('input', function() {
      let v = this.value.replace(/\D/g, '');
      if (v.length > 11) v = v.slice(0, 11);
      v = v.replace(/(\d{3})(\d)/, '$1.$2');
      v = v.replace(/(\d{3})(\d)/, '$1.$2');
      v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
      this.value = v;
    });
  }

  // Máscara Telefone
  const telInput = document.getElementById('telefone');
  if (telInput) {
    telInput.addEventListener('input', function() {
      let v = this.value.replace(/\D/g, '');
      if (v.length > 11) v = v.slice(0, 11);
      if (v.length > 10) {
        v = v.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
      } else if (v.length > 6) {
        v = v.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
      } else if (v.length > 2) {
        v = v.replace(/(\d{2})(\d{0,5})/, '($1) $2');
      }
      this.value = v;
    });
  }

  // Validação do formulário de inscrição
  const formInscricao = document.getElementById('form-inscricao');
  if (formInscricao) {
    formInscricao.addEventListener('submit', function(e) {
      e.preventDefault();
      if (!validarFormulario()) return;
      if (!pagamentoInput || !pagamentoInput.value) {
        showAlert('Selecione uma forma de pagamento.', 'warning');
        document.querySelector('.pagamento-section')?.scrollIntoView({ behavior: 'smooth' });
        return;
      }
      // Mostrar spinner
      const overlay = document.getElementById('spinner-overlay');
      if (overlay) overlay.classList.add('active');
      this.submit();
    });
  }

  // Validar campos obrigatórios
  function validarFormulario() {
    const campos = formInscricao.querySelectorAll('[required]');
    let valido = true;
    campos.forEach(campo => {
      campo.classList.remove('is-invalid');
      if (!campo.value.trim()) {
        campo.classList.add('is-invalid');
        valido = false;
      }
    });
    if (!valido) {
      showAlert('Preencha todos os campos obrigatórios.', 'danger');
      formInscricao.querySelector('.is-invalid')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    return valido;
  }

  // Alertas inline
  function showAlert(msg, type) {
    const existing = document.getElementById('form-alert');
    if (existing) existing.remove();
    const alert = document.createElement('div');
    alert.id = 'form-alert';
    alert.className = `alert alert-${type} alert-dismissible mt-3`;
    alert.innerHTML = `${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    formInscricao?.prepend(alert);
    alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(() => { if (alert.parentNode) alert.remove(); }, 6000);
  }

  // Flash messages auto-dismiss
  setTimeout(() => {
    document.querySelectorAll('.alert-dismissible:not(.alert-danger)').forEach(el => {
      el.classList.add('fade');
      setTimeout(() => el.remove(), 500);
    });
  }, 5000);

  // Animate on scroll
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('fade-in-up');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1 });
    document.querySelectorAll('.course-card').forEach(el => observer.observe(el));
  }

  // Debounce helper
  function debounce(fn, delay) {
    let timer;
    return function(...args) {
      clearTimeout(timer);
      timer = setTimeout(() => fn.apply(this, args), delay);
    };
  }
});
