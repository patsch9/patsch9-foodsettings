<?php
/**
 * Plugin Name: P9 Food Settings – Lebensmittel
 * Description: Zutaten, Allergene, Nährwerte, Rezeptberechnung und öffentliche Lebensmitteldaten für WooCommerce.
 * Version: 2026.11.1
 * Author: Patrick Schmidt
 * Author URI: https://github.com/patsch9
 * Plugin URI: https://github.com/patsch9/patsch9-foodsettings
 * Update URI: https://github.com/patsch9/patsch9-foodsettings
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: patsch9-productsettings
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * WC requires at least: 9.0
 */

defined( 'ABSPATH' ) || exit;
define( 'P9PS_VERSION', '2026.11.1' );
define( 'P9PS_PATH', plugin_dir_path( __FILE__ ) );
define( 'P9PS_URL', plugin_dir_url( __FILE__ ) );

add_action( 'before_woocommerce_init', static function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

add_action( 'plugins_loaded', static function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', static function () {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'P9 Food Settings benötigt WooCommerce.', 'patsch9-productsettings' ) . '</p></div>';
		} );
		return;
	}
	foreach ( array( 'nutrition', 'nutriscore', 'recipe', 'sources', 'product-data', 'editor-draft', 'admin', 'frontend' ) as $module ) {
		require_once P9PS_PATH . 'includes/class-' . $module . '.php';
	}
	\P9PS\Admin::init();
	\P9PS\Frontend::init();
} );
