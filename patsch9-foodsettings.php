<?php
/**
 * Plugin Name: Patsch9 Food Settings – Lebensmittel
 * Description: Zutaten, Allergene, Nährwerte, Rezeptberechnung und öffentliche Lebensmitteldaten für WooCommerce.
 * Version: 2026.11.1
 * Author: Patrick Schmidt
 * Author URI: https://github.com/patsch9
 * Plugin URI: https://github.com/patsch9/patsch9-foodsettings
 * Update URI: https://github.com/patsch9/patsch9-foodsettings
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: patsch9-foodsettings
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * WC requires at least: 9.9
 */

defined( 'ABSPATH' ) || exit;
define( 'PATSCH9FS_VERSION', '2026.11.1' );
define( 'PATSCH9FS_PATH', plugin_dir_path( __FILE__ ) );
define( 'PATSCH9FS_URL', plugin_dir_url( __FILE__ ) );

add_action( 'before_woocommerce_init', static function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

add_action( 'plugins_loaded', static function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', static function () {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Patsch9 Food Settings benötigt WooCommerce.', 'patsch9-foodsettings' ) . '</p></div>';
		} );
		return;
	}
	if ( ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, '9.9', '<' ) ) {
		add_action( 'admin_notices', static function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Patsch9 Food Settings benötigt WooCommerce 9.9 oder neuer. Die Plugin-Funktionen wurden nicht geladen, damit die verpflichtende Checkout-Bestätigung nicht umgangen werden kann.', 'patsch9-foodsettings' ) . '</p></div>';
		} );
		return;
	}
	foreach ( array( 'nutrition', 'nutriscore', 'recipe', 'sources', 'product-data', 'editor-draft', 'admin', 'frontend', 'product-options', 'checkout' ) as $module ) {
		require_once PATSCH9FS_PATH . 'includes/class-' . $module . '.php';
	}
	\PATSCH9FS\Admin::init();
	\PATSCH9FS\Frontend::init();
	\PATSCH9FS\ProductOptions::init();
	\PATSCH9FS\Checkout::init();
} );
