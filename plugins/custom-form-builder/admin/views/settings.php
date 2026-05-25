<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap cfb-wrap">
    <h1><?php esc_html_e( 'Configurações Globais', 'custom-form-builder' ); ?></h1>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Configurações salvas!', 'custom-form-builder' ); ?></p></div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field( 'cfb_save_settings' ); ?>

        <div class="cfb-card">
            <h2><?php esc_html_e( 'Remetente Padrão', 'custom-form-builder' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Usado quando o formulário não tem remetente configurado.', 'custom-form-builder' ); ?></p>
            <table class="form-table">
                <tr>
                    <th><label for="from_name"><?php esc_html_e( 'Nome do remetente', 'custom-form-builder' ); ?></label></th>
                    <td><input type="text" id="from_name" name="from_name" class="regular-text"
                        value="<?php echo esc_attr( $settings['from_name'] ?? get_option( 'blogname' ) ); ?>"></td>
                </tr>
                <tr>
                    <th><label for="from_email"><?php esc_html_e( 'E-mail do remetente', 'custom-form-builder' ); ?></label></th>
                    <td><input type="email" id="from_email" name="from_email" class="regular-text"
                        value="<?php echo esc_attr( $settings['from_email'] ?? get_option( 'admin_email' ) ); ?>"></td>
                </tr>
                <tr>
                    <th><label for="fallback_email"><?php esc_html_e( 'E-mail padrão de destino', 'custom-form-builder' ); ?></label></th>
                    <td>
                        <input type="email" id="fallback_email" name="fallback_email" class="regular-text"
                            value="<?php echo esc_attr( $settings['fallback_email'] ?? get_option( 'admin_email' ) ); ?>">
                        <p class="description"><?php esc_html_e( 'Usado quando o formulário não tem destinatário configurado.', 'custom-form-builder' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="cfb-card">
            <h2><?php esc_html_e( 'Cópia (CC / BCC)', 'custom-form-builder' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th><label for="cc"><?php esc_html_e( 'CC (com cópia)', 'custom-form-builder' ); ?></label></th>
                    <td><input type="email" id="cc" name="cc" class="regular-text"
                        value="<?php echo esc_attr( $settings['cc'] ?? '' ); ?>"></td>
                </tr>
                <tr>
                    <th><label for="bcc"><?php esc_html_e( 'BCC (cópia oculta)', 'custom-form-builder' ); ?></label></th>
                    <td><input type="email" id="bcc" name="bcc" class="regular-text"
                        value="<?php echo esc_attr( $settings['bcc'] ?? '' ); ?>"></td>
                </tr>
            </table>
        </div>

        <div class="cfb-card">
            <h2><?php esc_html_e( 'Auto-resposta', 'custom-form-builder' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Envia uma resposta automática para o e-mail digitado no formulário.', 'custom-form-builder' ); ?></p>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Ativar auto-resposta', 'custom-form-builder' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" id="auto_reply" name="auto_reply" value="1" <?php checked( $settings['auto_reply'] ?? 0 ); ?>>
                            <?php esc_html_e( 'Enviar e-mail de confirmação ao visitante', 'custom-form-builder' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><label for="auto_reply_subject"><?php esc_html_e( 'Assunto da auto-resposta', 'custom-form-builder' ); ?></label></th>
                    <td><input type="text" id="auto_reply_subject" name="auto_reply_subject" class="large-text"
                        value="<?php echo esc_attr( $settings['auto_reply_subject'] ?? '' ); ?>"></td>
                </tr>
                <tr>
                    <th><label for="auto_reply_body"><?php esc_html_e( 'Mensagem da auto-resposta', 'custom-form-builder' ); ?></label></th>
                    <td>
                        <textarea id="auto_reply_body" name="auto_reply_body" rows="5" class="large-text"><?php echo esc_textarea( $settings['auto_reply_body'] ?? '' ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'HTML permitido.', 'custom-form-builder' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <button type="submit" name="cfb_save_settings" class="button button-primary button-large">
                <?php esc_html_e( 'Salvar Configurações', 'custom-form-builder' ); ?>
            </button>
        </p>
    </form>
</div>
