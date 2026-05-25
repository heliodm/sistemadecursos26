<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CFB_Frontend {

    public function __construct() {
        add_shortcode( 'cfb_form', array( $this, 'render_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
        add_action( 'wp_ajax_cfb_submit',        array( $this, 'handle_submit' ) );
        add_action( 'wp_ajax_nopriv_cfb_submit', array( $this, 'handle_submit' ) );
    }

    public function enqueue() {
        wp_enqueue_style(
            'cfb-frontend',
            CFB_PLUGIN_URL . 'assets/css/frontend.css',
            array(), CFB_VERSION
        );
        wp_enqueue_script(
            'cfb-frontend',
            CFB_PLUGIN_URL . 'assets/js/frontend.js',
            array( 'jquery' ), CFB_VERSION, true
        );
        wp_localize_script( 'cfb-frontend', 'cfb', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'cfb_nonce' ),
        ) );
    }

    /* ── Shortcode ──────────────────────────────────────────── */

    public function render_shortcode( $atts ) {
        $atts = shortcode_atts( array( 'id' => 0 ), $atts );
        $id   = absint( $atts['id'] );
        if ( ! $id ) {
            return '<p class="cfb-error">' . esc_html__( 'ID de formulário inválido.', 'custom-form-builder' ) . '</p>';
        }

        $form   = CFB_Database::get_form( $id );
        $fields = CFB_Database::get_fields( $id );

        if ( ! $form ) {
            return '<p class="cfb-error">' . esc_html__( 'Formulário não encontrado.', 'custom-form-builder' ) . '</p>';
        }

        ob_start();
        include CFB_PLUGIN_DIR . 'admin/views/form-render.php';
        return ob_get_clean();
    }

    /* ── AJAX handler ───────────────────────────────────────── */

    public function handle_submit() {
        check_ajax_referer( 'cfb_nonce', 'nonce' );

        $form_id = absint( $_POST['form_id'] ?? 0 );
        $form    = CFB_Database::get_form( $form_id );
        $fields  = CFB_Database::get_fields( $form_id );

        if ( ! $form || ! $fields ) {
            wp_send_json_error( array( 'message' => __( 'Formulário inválido.', 'custom-form-builder' ) ) );
        }

        /* ── Honeypot anti-spam ─────────────────────────────── */
        if ( ! empty( $_POST['cfb_hp'] ) ) {
            wp_send_json_error( array( 'message' => __( 'Erro de validação.', 'custom-form-builder' ) ) );
        }

        /* ── Validate & collect data ────────────────────────── */
        $errors    = array();
        $submitted = array();

        foreach ( $fields as $f ) {
            $name  = $f->field_name;
            $label = $f->field_label;
            $type  = $f->field_type;

            if ( 'checkbox' === $type ) {
                $value = isset( $_POST[ $name ] ) ? array_map( 'sanitize_text_field', (array) $_POST[ $name ] ) : array();
            } elseif ( 'textarea' === $type ) {
                $value = sanitize_textarea_field( $_POST[ $name ] ?? '' );
            } elseif ( 'email' === $type ) {
                $value = sanitize_email( $_POST[ $name ] ?? '' );
                if ( $f->field_required && $value && ! is_email( $value ) ) {
                    $errors[ $name ] = sprintf( __( '%s deve ser um e-mail válido.', 'custom-form-builder' ), $label );
                }
            } else {
                $value = sanitize_text_field( $_POST[ $name ] ?? '' );
            }

            if ( $f->field_required && empty( $value ) ) {
                $errors[ $name ] = sprintf( __( '%s é obrigatório.', 'custom-form-builder' ), $label );
            }

            $submitted[ $name ] = $value;
        }

        if ( ! empty( $errors ) ) {
            wp_send_json_error( array( 'message' => __( 'Por favor corrija os erros.', 'custom-form-builder' ), 'errors' => $errors ) );
        }

        /* ── Send email ─────────────────────────────────────── */
        $sent = CFB_Email::send( $form, $fields, $submitted );

        if ( ! $sent ) {
            wp_send_json_error( array( 'message' => __( 'Não foi possível enviar o e-mail. Tente novamente.', 'custom-form-builder' ) ) );
        }

        /* ── Store entry ────────────────────────────────────── */
        if ( $form->store_entries ) {
            CFB_Database::save_entry( $form_id, $submitted );
        }

        $success = ! empty( $form->success_message )
            ? $form->success_message
            : __( 'Mensagem enviada com sucesso! Em breve entraremos em contato.', 'custom-form-builder' );

        wp_send_json_success( array(
            'message'      => $success,
            'redirect_url' => $form->redirect_url ?: '',
        ) );
    }
}
