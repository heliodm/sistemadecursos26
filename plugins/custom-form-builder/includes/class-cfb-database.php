<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CFB_Database {

    public static function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $forms = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cfb_forms (
            id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name          VARCHAR(200)        NOT NULL,
            description   TEXT,
            email_to      TEXT                NOT NULL,
            email_from    VARCHAR(200),
            email_from_name VARCHAR(200),
            email_subject VARCHAR(300)        NOT NULL,
            email_body    LONGTEXT            NOT NULL,
            success_message TEXT,
            redirect_url  VARCHAR(500),
            store_entries TINYINT(1)          NOT NULL DEFAULT 1,
            created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;";

        $fields = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cfb_fields (
            id               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id          BIGINT(20) UNSIGNED NOT NULL,
            field_label      VARCHAR(200)        NOT NULL,
            field_name       VARCHAR(200)        NOT NULL,
            field_type       VARCHAR(50)         NOT NULL DEFAULT 'text',
            field_placeholder VARCHAR(300),
            field_options    TEXT,
            field_required   TINYINT(1)          NOT NULL DEFAULT 0,
            field_width      VARCHAR(20)         NOT NULL DEFAULT 'full',
            css_class        VARCHAR(200),
            sort_order       INT                 NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY form_id (form_id)
        ) $charset;";

        $entries = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cfb_entries (
            id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id    BIGINT(20) UNSIGNED NOT NULL,
            entry_data LONGTEXT            NOT NULL,
            user_ip    VARCHAR(50),
            user_agent TEXT,
            created_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY form_id (form_id)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $forms );
        dbDelta( $fields );
        dbDelta( $entries );

        add_option( 'cfb_version', CFB_VERSION );
    }

    public static function uninstall() {
        global $wpdb;
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cfb_entries" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cfb_fields" );
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cfb_forms" );
        delete_option( 'cfb_version' );
        delete_option( 'cfb_global_settings' );
    }

    /* ── Forms ─────────────────────────────────────────────── */

    public static function get_forms() {
        global $wpdb;
        return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}cfb_forms ORDER BY id DESC" );
    }

    public static function get_form( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cfb_forms WHERE id = %d", $id
        ) );
    }

    public static function save_form( $data ) {
        global $wpdb;
        $table  = $wpdb->prefix . 'cfb_forms';
        $fields = array(
            'name'           => sanitize_text_field( $data['name'] ),
            'description'    => sanitize_textarea_field( $data['description'] ?? '' ),
            'email_to'       => sanitize_textarea_field( $data['email_to'] ),
            'email_from'     => sanitize_email( $data['email_from'] ?? '' ),
            'email_from_name'=> sanitize_text_field( $data['email_from_name'] ?? '' ),
            'email_subject'  => sanitize_text_field( $data['email_subject'] ),
            'email_body'     => wp_kses_post( $data['email_body'] ),
            'success_message'=> wp_kses_post( $data['success_message'] ?? '' ),
            'redirect_url'   => esc_url_raw( $data['redirect_url'] ?? '' ),
            'store_entries'  => isset( $data['store_entries'] ) ? 1 : 0,
        );
        $formats = array( '%s','%s','%s','%s','%s','%s','%s','%s','%s','%d' );

        if ( ! empty( $data['id'] ) ) {
            $wpdb->update( $table, $fields, array( 'id' => absint( $data['id'] ) ), $formats, array( '%d' ) );
            return absint( $data['id'] );
        }

        $wpdb->insert( $table, $fields, $formats );
        return $wpdb->insert_id;
    }

    public static function delete_form( $id ) {
        global $wpdb;
        $id = absint( $id );
        $wpdb->delete( $wpdb->prefix . 'cfb_fields',  array( 'form_id' => $id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'cfb_entries', array( 'form_id' => $id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'cfb_forms',   array( 'id'      => $id ), array( '%d' ) );
    }

    /* ── Fields ─────────────────────────────────────────────── */

    public static function get_fields( $form_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cfb_fields WHERE form_id = %d ORDER BY sort_order ASC",
            $form_id
        ) );
    }

    public static function save_field( $data ) {
        global $wpdb;
        $table  = $wpdb->prefix . 'cfb_fields';
        $fields = array(
            'form_id'          => absint( $data['form_id'] ),
            'field_label'      => sanitize_text_field( $data['field_label'] ),
            'field_name'       => sanitize_key( $data['field_name'] ),
            'field_type'       => sanitize_key( $data['field_type'] ),
            'field_placeholder'=> sanitize_text_field( $data['field_placeholder'] ?? '' ),
            'field_options'    => sanitize_textarea_field( $data['field_options'] ?? '' ),
            'field_required'   => isset( $data['field_required'] ) ? 1 : 0,
            'field_width'      => sanitize_key( $data['field_width'] ?? 'full' ),
            'css_class'        => sanitize_html_class( $data['css_class'] ?? '' ),
            'sort_order'       => absint( $data['sort_order'] ?? 0 ),
        );
        $formats = array( '%d','%s','%s','%s','%s','%s','%d','%s','%s','%d' );

        if ( ! empty( $data['id'] ) ) {
            $wpdb->update( $table, $fields, array( 'id' => absint( $data['id'] ) ), $formats, array( '%d' ) );
            return absint( $data['id'] );
        }

        $wpdb->insert( $table, $fields, $formats );
        return $wpdb->insert_id;
    }

    public static function delete_field( $id ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'cfb_fields', array( 'id' => absint( $id ) ), array( '%d' ) );
    }

    /* ── Entries ────────────────────────────────────────────── */

    public static function save_entry( $form_id, $entry_data ) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'cfb_entries',
            array(
                'form_id'    => absint( $form_id ),
                'entry_data' => wp_json_encode( $entry_data ),
                'user_ip'    => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
                'user_agent' => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
            ),
            array( '%d', '%s', '%s', '%s' )
        );
        return $wpdb->insert_id;
    }

    public static function get_entries( $form_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cfb_entries WHERE form_id = %d ORDER BY created_at DESC",
            $form_id
        ) );
    }

    public static function delete_entry( $id ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'cfb_entries', array( 'id' => absint( $id ) ), array( '%d' ) );
    }
}
