<?php
/**
 * Plugin Name: Custom Form Builder
 * Plugin URI:  https://github.com/heliodm/sistemadecursos26
 * Description: Crie e personalize formulários diretamente no painel do WordPress, com envio por e-mail totalmente configurável.
 * Version:     1.0.0
 * Author:      Hélio
 * Text Domain: custom-form-builder
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CFB_VERSION',     '1.0.0' );
define( 'CFB_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'CFB_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'CFB_PLUGIN_FILE', __FILE__ );

require_once CFB_PLUGIN_DIR . 'includes/class-cfb-database.php';
require_once CFB_PLUGIN_DIR . 'includes/class-cfb-email.php';
require_once CFB_PLUGIN_DIR . 'includes/class-cfb-frontend.php';
require_once CFB_PLUGIN_DIR . 'includes/class-cfb-admin.php';

register_activation_hook( __FILE__, array( 'CFB_Database', 'install' ) );
register_uninstall_hook( __FILE__, array( 'CFB_Database', 'uninstall' ) );

add_action( 'plugins_loaded', 'cfb_init' );

function cfb_init() {
    if ( is_admin() ) {
        new CFB_Admin();
    }
    new CFB_Frontend();
}
