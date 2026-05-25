/* global cfbAdmin, jQuery */
(function ($) {
    'use strict';

    var $modal      = $('#cfb-field-modal');
    var $form       = $('#cfb-field-form');
    var $fieldsList = $('#cfb-fields-list');

    /* ── Open modal (new) ──────────────────────────────── */
    $('#cfb-add-field-btn').on('click', function () {
        $('#cfb-modal-title').text('Adicionar Campo');
        $form[0].reset();
        $('#cfb-field-id').val('');
        toggleOptionsRow($('#cfb-ft').val());
        $modal.show();
        $('#cfb-fl').focus();
    });

    /* ── Open modal (edit) ─────────────────────────────── */
    $(document).on('click', '.cfb-edit-field', function (e) {
        e.preventDefault();
        var f = $(this).data('field');
        $('#cfb-modal-title').text('Editar Campo');
        $('#cfb-field-id').val(f.id);
        $form.find('[name=form_id]').val(f.form_id);
        $('#cfb-fl').val(f.field_label);
        $('#cfb-fn').val(f.field_name);
        $('#cfb-ft').val(f.field_type);
        $('#cfb-fp').val(f.field_placeholder);
        $('#cfb-fo').val(f.field_options);
        $('#cfb-fw').val(f.field_width);
        $('#cfb-css').val(f.css_class);
        $('#cfb-req').prop('checked', f.field_required == 1);
        toggleOptionsRow(f.field_type);
        $modal.show();
        $('#cfb-fl').focus();
    });

    /* ── Close modal ───────────────────────────────────── */
    $('#cfb-modal-close').on('click', function () { $modal.hide(); });
    $(document).on('keydown', function (e) { if (e.key === 'Escape') $modal.hide(); });
    $modal.on('click', function (e) { if (e.target === this) $modal.hide(); });

    /* ── Auto-slug ─────────────────────────────────────── */
    $('#cfb-fl').on('input', function () {
        if (!$('#cfb-field-id').val()) {
            var slug = $(this).val()
                .toLowerCase()
                .normalize('NFD').replace(/[̀-ͯ]/g, '')
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_|_$/g, '');
            $('#cfb-fn').val(slug);
        }
    });

    /* ── Toggle options row ────────────────────────────── */
    $('#cfb-ft').on('change', function () { toggleOptionsRow($(this).val()); });

    function toggleOptionsRow(type) {
        var needs = ['select', 'radio', 'checkbox'].indexOf(type) !== -1;
        $('#cfb-options-row').toggle(needs);
    }

    /* ── Save field ────────────────────────────────────── */
    $form.on('submit', function (e) {
        e.preventDefault();
        var data = $form.serialize() + '&action=cfb_save_field&nonce=' + cfbAdmin.nonce;

        $.post(cfbAdmin.ajax_url, data, function (res) {
            if (!res.success) { alert('Erro ao salvar campo.'); return; }
            $modal.hide();
            location.reload();
        });
    });

    /* ── Delete field ──────────────────────────────────── */
    $(document).on('click', '.cfb-delete-field', function (e) {
        e.preventDefault();
        if (!confirm(cfbAdmin.confirm_delete)) return;
        var fieldId = $(this).data('id');
        $.post(cfbAdmin.ajax_url, {
            action: 'cfb_delete_field',
            nonce: cfbAdmin.nonce,
            field_id: fieldId
        }, function (res) {
            if (res.success) {
                $('[data-id="' + fieldId + '"]').remove();
            }
        });
    });

    /* ── Sortable reorder ──────────────────────────────── */
    if ($fieldsList.length) {
        $fieldsList.sortable({
            handle: '.cfb-drag-handle',
            update: function () {
                var order = $fieldsList.sortable('toArray', { attribute: 'data-id' });
                $.post(cfbAdmin.ajax_url, {
                    action: 'cfb_reorder_fields',
                    nonce: cfbAdmin.nonce,
                    order: order
                });
            }
        });
    }

    /* ── Delete entry ──────────────────────────────────── */
    $(document).on('click', '.cfb-delete-entry', function (e) {
        e.preventDefault();
        if (!confirm(cfbAdmin.confirm_delete)) return;
        var entryId = $(this).data('id');
        $.post(cfbAdmin.ajax_url, {
            action: 'cfb_delete_entry',
            nonce: cfbAdmin.nonce,
            entry_id: entryId
        }, function (res) {
            if (res.success) {
                $('[data-id="' + entryId + '"]').fadeOut(300, function () { $(this).remove(); });
            }
        });
    });

    /* ── Copy shortcode ────────────────────────────────── */
    $(document).on('click', '.cfb-shortcode', function () {
        var text = $(this).text().trim();
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function () {
                var $el = $('.cfb-shortcode');
                var orig = $el.css('background');
                $el.css('background', '#c6f0c2');
                setTimeout(function () { $el.css('background', orig); }, 800);
            });
        }
    });

}(jQuery));
