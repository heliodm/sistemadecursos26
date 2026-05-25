<?php if ( ! defined( 'ABSPATH' ) ) exit;

$is_new  = ! $form;
$form_id = $form ? $form->id : 0;
$title   = $is_new ? __( 'Novo Formulário', 'custom-form-builder' ) : sprintf( __( 'Editar: %s', 'custom-form-builder' ), $form->name );
$field_types = array(
    'text'     => __( 'Texto', 'custom-form-builder' ),
    'email'    => __( 'E-mail', 'custom-form-builder' ),
    'textarea' => __( 'Área de texto', 'custom-form-builder' ),
    'number'   => __( 'Número', 'custom-form-builder' ),
    'tel'      => __( 'Telefone', 'custom-form-builder' ),
    'url'      => __( 'URL', 'custom-form-builder' ),
    'select'   => __( 'Lista suspensa', 'custom-form-builder' ),
    'radio'    => __( 'Opção (radio)', 'custom-form-builder' ),
    'checkbox' => __( 'Caixas de seleção', 'custom-form-builder' ),
    'date'     => __( 'Data', 'custom-form-builder' ),
    'hidden'   => __( 'Oculto', 'custom-form-builder' ),
);
?>
<div class="wrap cfb-wrap">
    <h1><?php echo esc_html( $title ); ?></h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=cfb-forms' ) ); ?>">&larr; <?php esc_html_e( 'Voltar', 'custom-form-builder' ); ?></a>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Formulário salvo!', 'custom-form-builder' ); ?></p></div>
    <?php endif; ?>

    <?php if ( $form_id ) : ?>
        <div class="cfb-shortcode-bar">
            <strong><?php esc_html_e( 'Shortcode:', 'custom-form-builder' ); ?></strong>
            <code class="cfb-shortcode">[cfb_form id="<?php echo $form_id; ?>"]</code>
        </div>
    <?php endif; ?>

    <div class="cfb-edit-layout">

        <!-- ── Left column: form settings ─────────────── -->
        <div class="cfb-col-main">
            <form method="post" action="">
                <?php wp_nonce_field( 'cfb_save_form' ); ?>
                <input type="hidden" name="id" value="<?php echo $form_id; ?>">

                <!-- Basic Info -->
                <div class="cfb-card">
                    <h2><?php esc_html_e( 'Informações Básicas', 'custom-form-builder' ); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="cfb-name"><?php esc_html_e( 'Nome do formulário', 'custom-form-builder' ); ?> *</label></th>
                            <td><input type="text" id="cfb-name" name="name" class="regular-text" required
                                value="<?php echo esc_attr( $form->name ?? '' ); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="cfb-desc"><?php esc_html_e( 'Descrição', 'custom-form-builder' ); ?></label></th>
                            <td><input type="text" id="cfb-desc" name="description" class="large-text"
                                value="<?php echo esc_attr( $form->description ?? '' ); ?>"></td>
                        </tr>
                    </table>
                </div>

                <!-- Email Settings -->
                <div class="cfb-card">
                    <h2><?php esc_html_e( 'Configurações de E-mail', 'custom-form-builder' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Deixe em branco para usar as configurações globais.', 'custom-form-builder' ); ?></p>
                    <table class="form-table">
                        <tr>
                            <th><label for="cfb-email-to"><?php esc_html_e( 'Enviar para (destinatários)', 'custom-form-builder' ); ?> *</label></th>
                            <td>
                                <textarea id="cfb-email-to" name="email_to" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'Um e-mail por linha', 'custom-form-builder' ); ?>"><?php echo esc_textarea( $form->email_to ?? get_option( 'admin_email' ) ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'Coloque um e-mail por linha para múltiplos destinatários.', 'custom-form-builder' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cfb-email-from"><?php esc_html_e( 'De (e-mail)', 'custom-form-builder' ); ?></label></th>
                            <td><input type="email" id="cfb-email-from" name="email_from" class="regular-text"
                                value="<?php echo esc_attr( $form->email_from ?? '' ); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="cfb-email-from-name"><?php esc_html_e( 'De (nome)', 'custom-form-builder' ); ?></label></th>
                            <td><input type="text" id="cfb-email-from-name" name="email_from_name" class="regular-text"
                                value="<?php echo esc_attr( $form->email_from_name ?? '' ); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="cfb-email-subject"><?php esc_html_e( 'Assunto', 'custom-form-builder' ); ?> *</label></th>
                            <td>
                                <input type="text" id="cfb-email-subject" name="email_subject" class="large-text" required
                                    value="<?php echo esc_attr( $form->email_subject ?? sprintf( __( 'Novo envio: %s', 'custom-form-builder' ), '' ) ); ?>">
                                <p class="description"><?php esc_html_e( 'Use {nome_do_campo} para inserir valores dos campos.', 'custom-form-builder' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cfb-email-body"><?php esc_html_e( 'Corpo do e-mail', 'custom-form-builder' ); ?></label></th>
                            <td>
                                <textarea id="cfb-email-body" name="email_body" rows="8" class="large-text"><?php echo esc_textarea( $form->email_body ?? '' ); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e( 'Tags disponíveis:', 'custom-form-builder' ); ?>
                                    <code>{all_fields}</code> — <?php esc_html_e( 'todos os campos', 'custom-form-builder' ); ?>,
                                    <code>{nome_do_campo}</code> — <?php esc_html_e( 'campo específico', 'custom-form-builder' ); ?>,
                                    <code>{site_name}</code>, <code>{date}</code>, <code>{time}</code>.
                                    <?php esc_html_e( 'Deixe vazio para usar o template padrão.', 'custom-form-builder' ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Success -->
                <div class="cfb-card">
                    <h2><?php esc_html_e( 'Após o Envio', 'custom-form-builder' ); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="cfb-success"><?php esc_html_e( 'Mensagem de sucesso', 'custom-form-builder' ); ?></label></th>
                            <td>
                                <textarea id="cfb-success" name="success_message" rows="3" class="large-text"><?php echo esc_textarea( $form->success_message ?? '' ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'HTML permitido. Deixe em branco para usar a mensagem padrão.', 'custom-form-builder' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cfb-redirect"><?php esc_html_e( 'Redirecionar para URL', 'custom-form-builder' ); ?></label></th>
                            <td>
                                <input type="url" id="cfb-redirect" name="redirect_url" class="large-text"
                                    value="<?php echo esc_attr( $form->redirect_url ?? '' ); ?>">
                                <p class="description"><?php esc_html_e( 'Opcional. Se preenchido, redireciona após o envio em vez de mostrar a mensagem de sucesso.', 'custom-form-builder' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Armazenar entradas', 'custom-form-builder' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="store_entries" value="1" <?php checked( $form->store_entries ?? 1 ); ?>>
                                    <?php esc_html_e( 'Salvar envios no banco de dados', 'custom-form-builder' ); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>

                <p class="submit">
                    <button type="submit" name="cfb_save_form" class="button button-primary button-large">
                        <?php esc_html_e( 'Salvar Formulário', 'custom-form-builder' ); ?>
                    </button>
                </p>
            </form>
        </div>

        <!-- ── Right column: field builder ────────────── -->
        <?php if ( $form_id ) : ?>
        <div class="cfb-col-side">
            <div class="cfb-card">
                <h2><?php esc_html_e( 'Campos do Formulário', 'custom-form-builder' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Arraste para reordenar.', 'custom-form-builder' ); ?></p>

                <ul id="cfb-fields-list" data-form-id="<?php echo $form_id; ?>">
                    <?php foreach ( $fields as $f ) : ?>
                        <li class="cfb-field-item" data-id="<?php echo $f->id; ?>">
                            <span class="cfb-drag-handle dashicons dashicons-move"></span>
                            <span class="cfb-field-label"><?php echo esc_html( $f->field_label ); ?></span>
                            <span class="cfb-field-type-badge"><?php echo esc_html( $field_types[ $f->field_type ] ?? $f->field_type ); ?></span>
                            <?php if ( $f->field_required ) : ?>
                                <span class="cfb-required-badge">*</span>
                            <?php endif; ?>
                            <span class="cfb-field-actions">
                                <a href="#" class="cfb-edit-field" data-field='<?php echo esc_attr( wp_json_encode( $f ) ); ?>'>
                                    <?php esc_html_e( 'Editar', 'custom-form-builder' ); ?>
                                </a>
                                <a href="#" class="cfb-delete-field" data-id="<?php echo $f->id; ?>">
                                    <?php esc_html_e( 'Excluir', 'custom-form-builder' ); ?>
                                </a>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <button type="button" id="cfb-add-field-btn" class="button">
                    + <?php esc_html_e( 'Adicionar Campo', 'custom-form-builder' ); ?>
                </button>
            </div>
        </div>

        <!-- ── Field modal ─────────────────────────────── -->
        <div id="cfb-field-modal" class="cfb-modal" style="display:none">
            <div class="cfb-modal-content">
                <h3 id="cfb-modal-title"><?php esc_html_e( 'Campo', 'custom-form-builder' ); ?></h3>
                <form id="cfb-field-form">
                    <input type="hidden" id="cfb-field-id" name="id" value="">
                    <input type="hidden" name="form_id" value="<?php echo $form_id; ?>">

                    <table class="form-table">
                        <tr>
                            <th><label for="cfb-fl"><?php esc_html_e( 'Rótulo', 'custom-form-builder' ); ?> *</label></th>
                            <td><input type="text" id="cfb-fl" name="field_label" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th><label for="cfb-fn"><?php esc_html_e( 'Nome (slug)', 'custom-form-builder' ); ?> *</label></th>
                            <td>
                                <input type="text" id="cfb-fn" name="field_name" class="regular-text" required
                                    pattern="[a-z0-9_\-]+" title="<?php esc_attr_e( 'Somente letras minúsculas, números, hífens e underscores.', 'custom-form-builder' ); ?>">
                                <p class="description"><?php esc_html_e( 'Ex: nome, email, mensagem. Usado nas tags do e-mail.', 'custom-form-builder' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cfb-ft"><?php esc_html_e( 'Tipo', 'custom-form-builder' ); ?></label></th>
                            <td>
                                <select id="cfb-ft" name="field_type" class="regular-text">
                                    <?php foreach ( $field_types as $val => $label ) : ?>
                                        <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cfb-fp"><?php esc_html_e( 'Placeholder', 'custom-form-builder' ); ?></label></th>
                            <td><input type="text" id="cfb-fp" name="field_placeholder" class="regular-text"></td>
                        </tr>
                        <tr id="cfb-options-row">
                            <th><label for="cfb-fo"><?php esc_html_e( 'Opções', 'custom-form-builder' ); ?></label></th>
                            <td>
                                <textarea id="cfb-fo" name="field_options" rows="4" class="large-text"
                                    placeholder="<?php esc_attr_e( 'Uma opção por linha', 'custom-form-builder' ); ?>"></textarea>
                                <p class="description"><?php esc_html_e( 'Para select, radio e checkbox. Uma opção por linha. Formato: valor|Rótulo (ou apenas Rótulo).', 'custom-form-builder' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Largura', 'custom-form-builder' ); ?></th>
                            <td>
                                <select name="field_width" id="cfb-fw">
                                    <option value="full"><?php esc_html_e( 'Largura total', 'custom-form-builder' ); ?></option>
                                    <option value="half"><?php esc_html_e( 'Metade', 'custom-form-builder' ); ?></option>
                                    <option value="third"><?php esc_html_e( 'Um terço', 'custom-form-builder' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cfb-css"><?php esc_html_e( 'Classe CSS', 'custom-form-builder' ); ?></label></th>
                            <td><input type="text" id="cfb-css" name="css_class" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Obrigatório', 'custom-form-builder' ); ?></th>
                            <td>
                                <label><input type="checkbox" id="cfb-req" name="field_required" value="1">
                                    <?php esc_html_e( 'Campo obrigatório', 'custom-form-builder' ); ?>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <div class="cfb-modal-actions">
                        <button type="submit" class="button button-primary"><?php esc_html_e( 'Salvar Campo', 'custom-form-builder' ); ?></button>
                        <button type="button" id="cfb-modal-close" class="button"><?php esc_html_e( 'Cancelar', 'custom-form-builder' ); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php else : ?>
        <div class="cfb-col-side">
            <div class="cfb-card cfb-notice-card">
                <span class="dashicons dashicons-info"></span>
                <p><?php esc_html_e( 'Salve o formulário primeiro para adicionar campos.', 'custom-form-builder' ); ?></p>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- .cfb-edit-layout -->
</div>
