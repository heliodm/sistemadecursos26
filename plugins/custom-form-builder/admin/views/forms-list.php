<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap cfb-wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Formulários', 'custom-form-builder' ); ?></h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=cfb-forms&action=new' ) ); ?>" class="page-title-action">
        <?php esc_html_e( '+ Novo Formulário', 'custom-form-builder' ); ?>
    </a>
    <hr class="wp-header-end">

    <?php if ( isset( $_GET['deleted'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Formulário excluído.', 'custom-form-builder' ); ?></p></div>
    <?php endif; ?>

    <?php if ( empty( $forms ) ) : ?>
        <div class="cfb-empty-state">
            <span class="dashicons dashicons-feedback"></span>
            <p><?php esc_html_e( 'Nenhum formulário criado ainda.', 'custom-form-builder' ); ?></p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=cfb-forms&action=new' ) ); ?>" class="button button-primary button-hero">
                <?php esc_html_e( 'Criar primeiro formulário', 'custom-form-builder' ); ?>
            </a>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped cfb-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Nome', 'custom-form-builder' ); ?></th>
                    <th><?php esc_html_e( 'Shortcode', 'custom-form-builder' ); ?></th>
                    <th><?php esc_html_e( 'Destinatário(s)', 'custom-form-builder' ); ?></th>
                    <th><?php esc_html_e( 'Criado em', 'custom-form-builder' ); ?></th>
                    <th><?php esc_html_e( 'Ações', 'custom-form-builder' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $forms as $f ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $f->name ); ?></strong>
                            <?php if ( $f->description ) : ?>
                                <br><small><?php echo esc_html( $f->description ); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <code class="cfb-shortcode" title="<?php esc_attr_e( 'Clique para copiar', 'custom-form-builder' ); ?>">
                                [cfb_form id="<?php echo absint( $f->id ); ?>"]
                            </code>
                        </td>
                        <td><?php echo esc_html( $f->email_to ?: '—' ); ?></td>
                        <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $f->created_at ) ) ); ?></td>
                        <td class="cfb-actions">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=cfb-forms&action=edit&id=' . $f->id ) ); ?>">
                                <?php esc_html_e( 'Editar', 'custom-form-builder' ); ?>
                            </a>
                            &nbsp;|&nbsp;
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=cfb-forms&action=entries&id=' . $f->id ) ); ?>">
                                <?php esc_html_e( 'Entradas', 'custom-form-builder' ); ?>
                            </a>
                            &nbsp;|&nbsp;
                            <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=cfb-forms&action=delete&id=' . $f->id ), 'cfb_delete_form_' . $f->id ) ); ?>"
                               class="cfb-delete" onclick="return confirm('<?php esc_attr_e( 'Excluir este formulário e todos os seus dados?', 'custom-form-builder' ); ?>')">
                                <?php esc_html_e( 'Excluir', 'custom-form-builder' ); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
