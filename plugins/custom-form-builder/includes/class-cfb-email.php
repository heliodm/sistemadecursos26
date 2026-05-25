<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CFB_Email {

    public static function send( $form, $fields, $submitted ) {
        $global = get_option( 'cfb_global_settings', array() );

        $placeholders = self::build_placeholders( $fields, $submitted );

        /* ── Recipients ─────────────────────────────────────── */
        $email_to = ! empty( $form->email_to )
            ? $form->email_to
            : ( $global['fallback_email'] ?? get_option( 'admin_email' ) );

        $recipients = array_filter( array_map( 'trim', explode( "\n", $email_to ) ) );

        /* ── Subject ────────────────────────────────────────── */
        $subject = ! empty( $form->email_subject )
            ? self::replace( $form->email_subject, $placeholders )
            : sprintf( __( 'Novo envio: %s', 'custom-form-builder' ), $form->name );

        /* ── Body ───────────────────────────────────────────── */
        $body = ! empty( $form->email_body )
            ? self::replace( $form->email_body, $placeholders )
            : self::default_body( $form, $fields, $submitted );

        /* ── Headers ────────────────────────────────────────── */
        $from_name  = ! empty( $form->email_from_name )
            ? $form->email_from_name
            : ( $global['from_name'] ?? get_option( 'blogname' ) );

        $from_email = ! empty( $form->email_from )
            ? $form->email_from
            : ( $global['from_email'] ?? get_option( 'admin_email' ) );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            sprintf( 'From: %s <%s>', $from_name, $from_email ),
        );

        /* ── Reply-To (campo e-mail do formulário) ─────────── */
        $reply_email = self::find_email_value( $fields, $submitted );
        if ( $reply_email ) {
            $headers[] = 'Reply-To: ' . $reply_email;
        }

        /* ── CC / BCC globais ───────────────────────────────── */
        if ( ! empty( $global['cc'] ) ) {
            $headers[] = 'Cc: ' . sanitize_email( $global['cc'] );
        }
        if ( ! empty( $global['bcc'] ) ) {
            $headers[] = 'Bcc: ' . sanitize_email( $global['bcc'] );
        }

        $body_html = self::wrap_html( $body, $form->name );
        $ok        = wp_mail( $recipients, $subject, $body_html, $headers );

        /* ── Auto-reply ─────────────────────────────────────── */
        if ( $ok && ! empty( $global['auto_reply'] ) && $reply_email ) {
            self::send_auto_reply( $reply_email, $form, $global, $from_name, $from_email );
        }

        return $ok;
    }

    /* ── Private helpers ──────────────────────────────────── */

    private static function build_placeholders( $fields, $submitted ) {
        $map = array(
            '{site_name}'  => get_option( 'blogname' ),
            '{site_url}'   => get_option( 'siteurl' ),
            '{date}'       => date_i18n( get_option( 'date_format' ) ),
            '{time}'       => date_i18n( get_option( 'time_format' ) ),
            '{all_fields}' => self::all_fields_html( $fields, $submitted ),
        );
        foreach ( $fields as $f ) {
            $val = isset( $submitted[ $f->field_name ] )
                ? ( is_array( $submitted[ $f->field_name ] )
                    ? implode( ', ', $submitted[ $f->field_name ] )
                    : $submitted[ $f->field_name ] )
                : '';
            $map[ '{' . $f->field_name . '}' ] = esc_html( $val );
        }
        return $map;
    }

    private static function replace( $text, $map ) {
        return str_replace( array_keys( $map ), array_values( $map ), $text );
    }

    private static function all_fields_html( $fields, $submitted ) {
        $html = '<table style="width:100%;border-collapse:collapse;">';
        foreach ( $fields as $f ) {
            $val = isset( $submitted[ $f->field_name ] )
                ? ( is_array( $submitted[ $f->field_name ] )
                    ? implode( ', ', $submitted[ $f->field_name ] )
                    : $submitted[ $f->field_name ] )
                : '';
            $html .= sprintf(
                '<tr><th style="text-align:left;padding:6px 12px;background:#f5f5f5;border:1px solid #ddd;width:35%%">%s</th>'
                . '<td style="padding:6px 12px;border:1px solid #ddd;">%s</td></tr>',
                esc_html( $f->field_label ),
                nl2br( esc_html( $val ) )
            );
        }
        $html .= '</table>';
        return $html;
    }

    private static function default_body( $form, $fields, $submitted ) {
        return sprintf(
            '<p>%s <strong>%s</strong>:</p>%s',
            esc_html__( 'Novo envio do formulário', 'custom-form-builder' ),
            esc_html( $form->name ),
            self::all_fields_html( $fields, $submitted )
        );
    }

    private static function find_email_value( $fields, $submitted ) {
        foreach ( $fields as $f ) {
            if ( 'email' === $f->field_type && ! empty( $submitted[ $f->field_name ] ) ) {
                return sanitize_email( $submitted[ $f->field_name ] );
            }
        }
        return '';
    }

    private static function send_auto_reply( $to, $form, $global, $from_name, $from_email ) {
        $subject = $global['auto_reply_subject'] ?? sprintf( __( 'Recebemos seu contato - %s', 'custom-form-builder' ), $form->name );
        $body    = $global['auto_reply_body']    ?? sprintf( __( 'Olá, recebemos seu envio no formulário <strong>%s</strong> e retornaremos em breve.', 'custom-form-builder' ), esc_html( $form->name ) );
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            sprintf( 'From: %s <%s>', $from_name, $from_email ),
        );
        wp_mail( $to, $subject, self::wrap_html( $body, $form->name ), $headers );
    }

    private static function wrap_html( $body, $form_name ) {
        return sprintf( '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body
            style="font-family:Arial,sans-serif;font-size:14px;color:#333;padding:20px">
            <h2 style="color:#444;border-bottom:2px solid #eee;padding-bottom:10px">%s</h2>
            <div>%s</div>
            <p style="margin-top:30px;font-size:12px;color:#999">%s — %s</p>
            </body></html>',
            esc_html( $form_name ),
            $body,
            esc_html( get_option( 'blogname' ) ),
            esc_html( get_option( 'siteurl' ) )
        );
    }
}
