<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="cfb-form-wrap" id="cfb-form-<?php echo absint( $form->id ); ?>">
    <form class="cfb-form" data-form-id="<?php echo absint( $form->id ); ?>" novalidate>
        <?php wp_nonce_field( 'cfb_nonce', 'cfb_nonce_field' ); ?>

        <!-- Honeypot -->
        <div style="display:none !important" aria-hidden="true">
            <input type="text" name="cfb_hp" value="" tabindex="-1" autocomplete="off">
        </div>

        <div class="cfb-fields-grid">
            <?php foreach ( $fields as $f ) :
                $required_attr = $f->field_required ? 'required' : '';
                $required_mark = $f->field_required ? '<span class="cfb-required" aria-hidden="true">*</span>' : '';
                $width_class   = 'cfb-width-' . esc_attr( $f->field_width );
                $extra_class   = $f->css_class ? ' ' . esc_attr( $f->css_class ) : '';
                $input_id      = 'cfb-' . absint( $form->id ) . '-' . esc_attr( $f->field_name );
                $options_raw   = $f->field_options ? array_filter( explode( "\n", trim( $f->field_options ) ) ) : array();
            ?>
                <div class="cfb-field-wrap <?php echo $width_class . $extra_class; ?>"
                     data-field="<?php echo esc_attr( $f->field_name ); ?>">

                    <?php if ( 'hidden' !== $f->field_type ) : ?>
                        <label for="<?php echo esc_attr( $input_id ); ?>" class="cfb-label">
                            <?php echo esc_html( $f->field_label ); ?><?php echo $required_mark; ?>
                        </label>
                    <?php endif; ?>

                    <?php if ( 'textarea' === $f->field_type ) : ?>
                        <textarea
                            id="<?php echo esc_attr( $input_id ); ?>"
                            name="<?php echo esc_attr( $f->field_name ); ?>"
                            class="cfb-input cfb-textarea"
                            placeholder="<?php echo esc_attr( $f->field_placeholder ); ?>"
                            <?php echo $required_attr; ?>
                            rows="5"
                        ></textarea>

                    <?php elseif ( 'select' === $f->field_type ) : ?>
                        <select
                            id="<?php echo esc_attr( $input_id ); ?>"
                            name="<?php echo esc_attr( $f->field_name ); ?>"
                            class="cfb-input cfb-select"
                            <?php echo $required_attr; ?>
                        >
                            <option value=""><?php echo esc_html( $f->field_placeholder ?: __( '— Selecione —', 'custom-form-builder' ) ); ?></option>
                            <?php foreach ( $options_raw as $opt ) :
                                $parts = explode( '|', trim( $opt ), 2 );
                                $val   = sanitize_text_field( $parts[0] );
                                $lbl   = isset( $parts[1] ) ? $parts[1] : $parts[0];
                            ?>
                                <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $lbl ); ?></option>
                            <?php endforeach; ?>
                        </select>

                    <?php elseif ( 'radio' === $f->field_type ) : ?>
                        <div class="cfb-radio-group" role="group" aria-labelledby="label-<?php echo esc_attr( $input_id ); ?>">
                            <?php foreach ( $options_raw as $i => $opt ) :
                                $parts = explode( '|', trim( $opt ), 2 );
                                $val   = sanitize_text_field( $parts[0] );
                                $lbl   = isset( $parts[1] ) ? $parts[1] : $parts[0];
                            ?>
                                <label class="cfb-radio-label">
                                    <input type="radio" name="<?php echo esc_attr( $f->field_name ); ?>"
                                        value="<?php echo esc_attr( $val ); ?>" <?php echo $i === 0 && $f->field_required ? 'required' : ''; ?>>
                                    <?php echo esc_html( $lbl ); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>

                    <?php elseif ( 'checkbox' === $f->field_type ) : ?>
                        <div class="cfb-checkbox-group" role="group">
                            <?php foreach ( $options_raw as $opt ) :
                                $parts = explode( '|', trim( $opt ), 2 );
                                $val   = sanitize_text_field( $parts[0] );
                                $lbl   = isset( $parts[1] ) ? $parts[1] : $parts[0];
                            ?>
                                <label class="cfb-checkbox-label">
                                    <input type="checkbox" name="<?php echo esc_attr( $f->field_name ); ?>[]"
                                        value="<?php echo esc_attr( $val ); ?>">
                                    <?php echo esc_html( $lbl ); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>

                    <?php else : ?>
                        <input
                            type="<?php echo esc_attr( $f->field_type ); ?>"
                            id="<?php echo esc_attr( $input_id ); ?>"
                            name="<?php echo esc_attr( $f->field_name ); ?>"
                            class="cfb-input"
                            placeholder="<?php echo esc_attr( $f->field_placeholder ); ?>"
                            <?php echo $required_attr; ?>
                        >
                    <?php endif; ?>

                    <span class="cfb-field-error" role="alert"></span>
                </div>
            <?php endforeach; ?>
        </div><!-- .cfb-fields-grid -->

        <div class="cfb-submit-wrap">
            <button type="submit" class="cfb-submit-btn">
                <?php esc_html_e( 'Enviar', 'custom-form-builder' ); ?>
            </button>
            <span class="cfb-spinner" style="display:none"></span>
        </div>

        <div class="cfb-form-message" role="alert" style="display:none"></div>
    </form>
</div>
