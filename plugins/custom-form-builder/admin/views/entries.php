<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap cfb-wrap">
    <h1>
        <?php printf( esc_html__( 'Entradas: %s', 'custom-form-builder' ), esc_html( $form->name ) ); ?>
    </h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=cfb-forms' ) ); ?>">&larr; <?php esc_html_e( 'Voltar para formulários', 'custom-form-builder' ); ?></a>

    <?php if ( empty( $entries ) ) : ?>
        <div class="cfb-empty-state">
            <span class="dashicons dashicons-email-alt"></span>
            <p><?php esc_html_e( 'Nenhuma entrada registrada ainda.', 'custom-form-builder' ); ?></p>
        </div>
    <?php else : ?>
        <p>
            <strong><?php echo count( $entries ); ?></strong>
            <?php esc_html_e( 'entrada(s) encontrada(s).', 'custom-form-builder' ); ?>
        </p>
        <div id="cfb-entries-list">
            <?php foreach ( $entries as $entry ) :
                $data = json_decode( $entry->entry_data, true );
            ?>
                <div class="cfb-entry-card" data-id="<?php echo $entry->id; ?>">
                    <div class="cfb-entry-meta">
                        <span><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry->created_at ) ) ); ?></span>
                        <span><?php echo esc_html( $entry->user_ip ); ?></span>
                        <a href="#" class="cfb-delete-entry" data-id="<?php echo $entry->id; ?>">
                            <?php esc_html_e( 'Excluir', 'custom-form-builder' ); ?>
                        </a>
                    </div>
                    <table class="cfb-entry-table">
                        <?php if ( is_array( $data ) ) : ?>
                            <?php foreach ( $data as $key => $val ) : ?>
                                <tr>
                                    <th><?php echo esc_html( $key ); ?></th>
                                    <td><?php echo nl2br( esc_html( is_array( $val ) ? implode( ', ', $val ) : $val ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
