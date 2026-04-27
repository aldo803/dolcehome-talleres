<?php
/**
 * Plugin Name: Dolce Home Talleres
 * Plugin URI:  https://talleres.dolcehome.uy
 * Description: Sistema de registro y venta de talleres de mantas de lana XXL para Dolce Home.
 * Version:     1.6.0
 * Author:      Dolce Home
 * Text Domain: dh-talleres
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

define( 'DH_TALLERES_VERSION',    '1.6.0' );
define( 'DH_TALLERES_PATH',       plugin_dir_path( __FILE__ ) );
define( 'DH_TALLERES_URL',        plugin_dir_url( __FILE__ ) );
define( 'DH_TALLERES_DB_VERSION', '1.6' );
define( 'DH_TALLERES_BASENAME',   plugin_basename( __FILE__ ) );

function dh_talleres_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>Dolce Home Talleres</strong> requiere que <strong>WooCommerce</strong> esté instalado y activo.</p></div>';
        } );
        deactivate_plugins( DH_TALLERES_BASENAME );
    }
}
add_action( 'admin_init', 'dh_talleres_check_woocommerce' );

// Orden correcto: settings y email antes que la clase principal
require_once DH_TALLERES_PATH . 'includes/class-dh-settings.php';
require_once DH_TALLERES_PATH . 'includes/class-dh-email.php';
require_once DH_TALLERES_PATH . 'includes/class-dh-talleres.php';
require_once DH_TALLERES_PATH . 'includes/class-dh-admin.php';
require_once DH_TALLERES_PATH . 'includes/class-dh-woocommerce.php';
require_once DH_TALLERES_PATH . 'includes/class-dh-frontend.php';

register_activation_hook( __FILE__, array( 'DH_Talleres', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'DH_Talleres', 'deactivate' ) );

// Migración automática de DB al cargar (detecta versión desactualizada)
add_action( 'plugins_loaded', function () {
    if ( get_option( 'dh_talleres_db_version' ) !== DH_TALLERES_DB_VERSION ) {
        DH_Talleres::create_table();       // dbDelta agrega columnas faltantes
        DH_Talleres::maybe_add_columns();  // ALTER TABLE explícito para seguridad
    }
}, 5 );

function dh_talleres_init() {
    DH_Talleres::get_instance();
}
add_action( 'plugins_loaded', 'dh_talleres_init' );

// =============================
// SISTEMA DE ACTUALIZACIONES
// =============================

require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/aldo803/dolcehome-talleres/',
    __FILE__,
    'dolcehome-talleres'
);

// Usar releases de GitHub
$updateChecker->getVcsApi()->enableReleaseAssets();