<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CFB_Admin {

    public function __construct() {
        add_action( 'admin_menu',            array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
        add_action( 'admin_init',            array( $this, 'handle_actions' ) );
        add_action( 'wp_ajax_cfb_save_field',   array( $this, 'ajax_save_field' ) );
        add_action( 'wp_ajax_cfb_delete_field', array( $this, 'ajax_delete_field' ) );
        add_action( 'wp_ajax_cfb_reorder_fields', array( $this, 'ajax_reorder_fields' ) );
        add_action( 'wp_ajax_cfb_delete_entry',   array( $this, 'ajax_delete_entry' ) );
        add_filter( 'set-screen-option', array( $this, 'set_screen_option' ), 10, 3 );
    }

    /* ── Menu ───────────────────────────────────────────────── */

    public function register_menu() {
        add_menu_page(
            __( 'Form Builder', 'custom-form-builder' ),
            __( 'Form Builder', 'custom-form-builder' ),
            'manage_options',
            'cfb-forms',
            array( $this, 'page_forms' ),
            'dashicons-feedback',
            58
        );
        add_submenu_page(
            'cfb-forms',
            __( 'Formulários', 'custom-form-builder' ),
            __( 'Formulários', 'custom-form-builder' ),
            'manage_options',
            'cfb-forms',
            array( $this, 'page_forms' )
        );
        add_submenu_page(
            'cfb-forms',
            __( 'Configurações Globais', 'custom-form-builder' ),
            __( 'Configurações', 'custom-form-builder' ),
            'manage_options',
            'cfb-settings',
            array( $this, 'page_settings' )
        );
    }

    /* ── Enqueue ────────────────────────────────────────────── */

    public function enqueue( $hook ) {
        if ( strpos( $hook, 'cfb' ) === false ) return;

        wp_enqueue_style(
            'cfb-admin',
            CFB_PLUGIN_URL . 'admin/css/admin.css',
            array(), CFB_VERSION
        );
        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_enqueue_script(
            'cfb-admin',
            CFB_PLUGIN_URL . 'admin/js/admin.js',
            array( 'jquery', 'jquery-ui-sortable' ), CFB_VERSION, true
        );
        wp_localize_script( 'cfb-admin', 'cfbAdmin', array(
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'cfb_admin_nonce' ),
            'confirm_delete'=> __( 'Confirma a exclusão?', 'custom-form-builder' ),
        ) );
    }

    public function set_screen_option( $status, $option, $value ) {
        return $value;
    }

    /* ── Router ─────────────────────────────────────────────── */

    public function handle_actions() {
        if ( ! isset( $_GET['page'] ) || strpos( $_GET['page'], 'cfb' ) === false ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        /* Save form */
        if ( isset( $_POST['cfb_save_form'] ) ) {
            check_admin_referer( 'cfb_save_form' );
            $id = CFB_Database::save_form( $_POST );
            wp_redirect( admin_url( 'admin.php?page=cfb-forms&action=edit&id=' . $id . '&saved=1' ) );
            exit;
        }

        /* Delete form */
        if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['id'] ) ) {
            check_admin_referer( 'cfb_delete_form_' . $_GET['id'] );
            CFB_Database::delete_form( absint( $_GET['id'] ) );
            wp_redirect( admin_url( 'admin.php?page=cfb-forms&deleted=1' ) );
            exit;
        }

        /* Save global settings */
        if ( isset( $_POST['cfb_save_settings'] ) ) {
            check_admin_referer( 'cfb_save_settings' );
            $settings = array(
                'from_name'           => sanitize_text_field( $_POST['from_name'] ?? '' ),
                'from_email'          => sanitize_email( $_POST['from_email'] ?? '' ),
                'fallback_email'      => sanitize_email( $_POST['fallback_email'] ?? '' ),
                'cc'                  => sanitize_email( $_POST['cc'] ?? '' ),
                'bcc'                 => sanitize_email( $_POST['bcc'] ?? '' ),
                'auto_reply'          => ! empty( $_POST['auto_reply'] ) ? 1 : 0,
                'auto_reply_subject'  => sanitize_text_field( $_POST['auto_reply_subject'] ?? '' ),
                'auto_reply_body'     => wp_kses_post( $_POST['auto_reply_body'] ?? '' ),
            );
            update_option( 'cfb_global_settings', $settings );
            wp_redirect( admin_url( 'admin.php?page=cfb-settings&saved=1' ) );
            exit;
        }
    }

    /* ── Pages ──────────────────────────────────────────────── */

    public function page_forms() {
        $action = $_GET['action'] ?? 'list';

        if ( 'edit' === $action || 'new' === $action ) {
            $form_id = absint( $_GET['id'] ?? 0 );
            $form    = $form_id ? CFB_Database::get_form( $form_id ) : null;
            $fields  = $form_id ? CFB_Database::get_fields( $form_id ) : array();
            include CFB_PLUGIN_DIR . 'admin/views/form-edit.php';
        } elseif ( 'entries' === $action ) {
            $form_id = absint( $_GET['id'] ?? 0 );
            $form    = CFB_Database::get_form( $form_id );
            $entries = CFB_Database::get_entries( $form_id );
            include CFB_PLUGIN_DIR . 'admin/views/entries.php';
        } else {
            $forms = CFB_Database::get_forms();
            include CFB_PLUGIN_DIR . 'admin/views/forms-list.php';
        }
    }

    public function page_settings() {
        $settings = get_option( 'cfb_global_settings', array() );
        include CFB_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /* ── AJAX ───────────────────────────────────────────────── */

    public function ajax_save_field() {
        check_ajax_referer( 'cfb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

        $id = CFB_Database::save_field( $_POST );
        wp_send_json_success( array( 'id' => $id ) );
    }

    public function ajax_delete_field() {
        check_ajax_referer( 'cfb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

        CFB_Database::delete_field( absint( $_POST['field_id'] ?? 0 ) );
        wp_send_json_success();
    }

    public function ajax_reorder_fields() {
        check_ajax_referer( 'cfb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

        global $wpdb;
        $order = array_map( 'absint', (array) ( $_POST['order'] ?? array() ) );
        foreach ( $order as $i => $field_id ) {
            $wpdb->update( $wpdb->prefix . 'cfb_fields', array( 'sort_order' => $i ), array( 'id' => $field_id ), array( '%d' ), array( '%d' ) );
        }
        wp_send_json_success();
    }

    public function ajax_delete_entry() {
        check_ajax_referer( 'cfb_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

        CFB_Database::delete_entry( absint( $_POST['entry_id'] ?? 0 ) );
        wp_send_json_success();
    }
}
