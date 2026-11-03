<?php
/** Real WooCommerce regression for the Store API withdrawal gate. */
use PATSCH9FS\Checkout;
use PATSCH9FS\ProductData;
use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;

if ( '1' !== getenv( 'PATSCH9FS_RUN_INTEGRATION_TESTS' ) || ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WooCommerce' ) ) {
	throw new RuntimeException( 'A throwaway WordPress + WooCommerce installation and PATSCH9FS_RUN_INTEGRATION_TESTS=1 are required.' );
}

$checks = 0;
$assert = static function ( bool $condition, string $message ) use ( &$checks ): void {
	++$checks;
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

$assert( class_exists( '\\Automattic\\WooCommerce\\StoreApi\\Exceptions\\RouteException' ), 'WooCommerce RouteException must be available.' );
$assert( class_exists( '\\Automattic\\WooCommerce\\Blocks\\Package' ), 'WooCommerce Blocks package container must be available.' );
$assert( class_exists( '\\Automattic\\WooCommerce\\Blocks\\Domain\\Services\\CheckoutFields' ), 'WooCommerce CheckoutFields service must be available.' );

$product = new WC_Product_Simple();
$product->set_name( 'P9 Withdrawal Gate Integration Test' );
$product->set_status( 'publish' );
$product->set_regular_price( '4.50' );
$product->update_meta_data( ProductData::WITHDRAWAL_META, 'yes' );
$product->save();
Checkout::set_product_requirement( $product->get_id(), true );

$request = new WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
$order = wc_create_order();
$order->add_product( $product, 1 );

$blocked = false;
try {
	Checkout::handle_block_acknowledgement( $order, $request );
} catch ( RouteException $exception ) {
	$blocked = true;
	$assert( 'patsch9fs_withdrawal_ack_required' === $exception->getErrorCode(), 'Missing acknowledgement uses the plugin-specific Store API error code.' );
}
$assert( $blocked, 'A relevant Store API POST without acknowledgement is blocked with RouteException.' );

$order->update_meta_data( '_wc_other/patsch9-foodsettings/withdrawal-ack', '1' );
Checkout::handle_block_acknowledgement( $order, $request );
$assert( 'yes' === $order->get_meta( '_patsch9fs_withdrawal_acknowledged', true ), 'Checked Store API acknowledgement is recorded.' );
$assert( array( $product->get_id() ) === $order->get_meta( '_patsch9fs_withdrawal_acknowledgement_products', true ), 'Recorded acknowledgement identifies the relevant product.' );

$product->update_meta_data( ProductData::WITHDRAWAL_META, 'no' );
$product->save();
$order->update_meta_data( '_patsch9fs_withdrawal_acknowledged', 'yes' );
Checkout::handle_block_acknowledgement( $order, $request );
$assert( '' === $order->get_meta( '_patsch9fs_withdrawal_acknowledged', true ), 'Stale acknowledgement metadata is cleared when no order item requires it.' );

Checkout::set_product_requirement( $product->get_id(), false );
$order->delete( true );
$product->delete( true );

WP_CLI::success( 'P9 Store API withdrawal integration: ' . $checks . ' checks passed.' );
