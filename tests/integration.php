<?php
/** Run with: wp eval-file tests/integration.php (throwaway WordPress + WooCommerce only). */
use P9PS\Admin;
use P9PS\Frontend;
use P9PS\Nutrition;
use P9PS\ProductData;
use P9PS\Sources;

if ( '1' !== getenv( 'P9PS_RUN_INTEGRATION_TESTS' ) || ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WooCommerce' ) ) {
    throw new RuntimeException( 'A throwaway WordPress + WooCommerce installation and P9PS_RUN_INTEGRATION_TESTS=1 are required.' );
}
if ( ! class_exists( 'WC_Admin_Meta_Boxes' ) ) {
    require_once WC_ABSPATH . 'includes/admin/class-wc-admin-meta-boxes.php';
}
$GLOBALS['p9ps_checks'] = 0;
function p9ps_assert( $ok, $message ) {
    ++$GLOBALS['p9ps_checks'];
    if ( ! $ok ) { throw new RuntimeException( $message ); }
}
function p9ps_ajax_request( array $request ): array {
    if ( ! defined( 'DOING_AJAX' ) ) { define( 'DOING_AJAX', true ); }
    $old_post = $_POST; $old_request = $_REQUEST;
    $_POST = $request; $_REQUEST = $request;
    $handler = static function () {
        return static function ( $message ) { throw new RuntimeException( 'p9ps-ajax-stop:' . (string) $message ); };
    };
    add_filter( 'wp_die_ajax_handler', $handler );
    ob_start();
    try {
        Admin::ajax();
        throw new RuntimeException( 'AJAX failed to terminate.' );
    } catch ( RuntimeException $error ) {
        if ( ! str_starts_with( $error->getMessage(), 'p9ps-ajax-stop:' ) ) { throw $error; }
        $stopped = $error->getMessage();
    } finally {
        $output = ob_get_clean();
        remove_filter( 'wp_die_ajax_handler', $handler );
        $_POST = $old_post; $_REQUEST = $old_request;
    }
    return array( 'response' => json_decode( $output, true ), 'stopped' => $stopped );
}
$admin = get_user_by( 'login', 'p9test' );
if ( ! $admin ) {
    $admins = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
    $admin = $admins[0];
}
wp_set_current_user( $admin->ID );
$p = new WC_Product_Simple();
$p->set_name( 'P9 Integration Test' );
$p->set_status( 'publish' );
$p->set_regular_price( '4.50' );
$p->save();
$ajax = array( 'nonce' => wp_create_nonce( 'p9ps_admin' ), 'product_id' => $p->get_id(), 'task' => 'search', 'query' => 'Weizen Mehl' );
$ajax_result = p9ps_ajax_request( $ajax );
p9ps_assert( true === $ajax_result['response']['success'] && count( $ajax_result['response']['data'] ) > 0, 'Authorized AJAX searches bundled BLS data.' );
$invalid_ajax = $ajax; $invalid_ajax['nonce'] = 'invalid';
p9ps_assert( 'p9ps-ajax-stop:-1' === p9ps_ajax_request( $invalid_ajax )['stopped'], 'Invalid AJAX nonce is rejected before processing.' );
$invalid_ajax = $ajax; $invalid_ajax['product_id'] = 0;
p9ps_assert( false === p9ps_ajax_request( $invalid_ajax )['response']['success'], 'AJAX cannot operate without a real editable product.' );
$_POST = array(
    'p9ps_present' => '1', 'p9ps_nonce' => wp_create_nonce( 'p9ps_save_food' ), 'p9ps_approve' => '1',
    'p9ps' => array( 'enabled' => '1', 'ingredients' => '<strong onclick="alert(1)">MILCH</strong>, Zucker<img src=x onerror="alert(1)">', 'operator' => 'Hersteller, Straße 1, Ort', 'nutrients' => array_fill_keys( Nutrition::KEYS, '0' ) ),
    'p9ps_recipe_json' => '[]', 'p9ps_sources_json' => '[]',
);
Admin::save( $p ); $p->save();
$d = ProductData::get( wc_get_product( $p->get_id() ) );
p9ps_assert( ProductData::approved( $d ), 'Valid saved declaration is approved.' );
$html = Frontend::render( $p );
p9ps_assert( str_contains( $html, '<strong>MILCH</strong>' ), 'Safe allergen emphasis retained.' );
p9ps_assert( ! str_contains( $html, 'onclick' ) && ! str_contains( $html, '<img' ), 'External markup cannot execute.' );
p9ps_assert( str_contains( $html, 'kJ / 0 kcal' ), 'Zero-valued nutrition table rendered.' );
p9ps_assert( str_contains( do_shortcode( '[p9ps_food id="' . $p->get_id() . '"]' ), 'MILCH' ), 'Shortcode renders approved food.' );

unset( $_POST['p9ps_approve'] );
Admin::save( $p ); $p->save();
p9ps_assert( ProductData::approved( ProductData::get( $p ) ), 'Unchanged save retains approval.' );
$_POST['p9ps']['ingredients'] .= ', Wasser';
Admin::save( $p ); $p->save();
p9ps_assert( ! ProductData::approved( ProductData::get( $p ) ), 'Changed declaration loses approval.' );
p9ps_assert( '' === Frontend::render( $p ), 'Unapproved data not visible.' );
$_POST['p9ps_approve'] = '1';
Admin::save( $p ); $p->save();
$approved_hash = ProductData::get( $p )['review_hash'];
$valid_nonce = $_POST['p9ps_nonce'];
$_POST['p9ps_nonce'] = 'invalid';
$_POST['p9ps']['ingredients'] = 'Invalid nonce attempt';
Admin::save( $p );
p9ps_assert( ProductData::get( $p )['review_hash'] === $approved_hash, 'Invalid nonce cannot change approved data.' );
unset( $_POST['p9ps_nonce'] );
Admin::save( $p );
p9ps_assert( ProductData::get( $p )['review_hash'] === $approved_hash, 'Missing nonce cannot change approved data.' );
$_POST['p9ps_nonce'] = $valid_nonce;
$_POST['p9ps']['ingredients'] = ProductData::get( $p )['ingredients'];
$_POST['p9ps']['nutrients']['fat'] = '-1';
Admin::save( $p ); $p->save();
p9ps_assert( ProductData::get( $p )['review_hash'] === $approved_hash, 'Invalid numeric save leaves existing data intact.' );

$manual_post = $_POST;
$recipe_product = new WC_Product_Simple();
$recipe_product->set_name( 'P9 Recipe Integration Test' ); $recipe_product->save();
$row_nutrients = array_fill_keys( Nutrition::KEYS, 0 );
$row_nutrients['carbs'] = 100; $row_nutrients['sugars'] = 100;
$_POST = array(
    'p9ps_present' => '1', 'p9ps_nonce' => wp_create_nonce( 'p9ps_save_food' ), 'p9ps_approve' => '1',
    'p9ps' => array( 'enabled' => '1', 'ingredients' => 'Zucker, Wasser', 'mode' => 'recipe', 'basis' => 'g', 'yield' => '200' ),
    'p9ps_recipe_json' => wp_slash( wp_json_encode( array( array( 'name' => 'Zucker', 'grams' => 100, 'nutrients' => $row_nutrients ) ) ) ),
    'p9ps_sources_json' => '[]',
);
Admin::save( $recipe_product ); $recipe_product->save();
$recipe_data = ProductData::get( $recipe_product );
p9ps_assert( 50.0 === $recipe_data['nutrients']['sugars'], 'Server calculates recipe on save from actual JSON form input.' );
p9ps_assert( ProductData::approved( $recipe_data ), 'Recipe is approved after valid explicit review.' );
$recipe_product->delete( true ); $_POST = $manual_post;

$subscriber = wp_create_user( 'p9ps-subscriber', wp_generate_password(), 'p9ps-subscriber@example.invalid' );
wp_set_current_user( $subscriber );
$subscriber_ajax = $ajax; $subscriber_ajax['nonce'] = wp_create_nonce( 'p9ps_admin' );
p9ps_assert( false === p9ps_ajax_request( $subscriber_ajax )['response']['success'], 'A valid nonce does not grant a subscriber AJAX access.' );
$subscriber_user = new WP_User( $subscriber );
$subscriber_user->add_cap( 'edit_products' );
p9ps_assert( false === p9ps_ajax_request( $subscriber_ajax )['response']['success'], 'General product editing capability does not bypass per-product authorization.' );
$subscriber_user->remove_cap( 'edit_products' );
$_POST['p9ps']['ingredients'] = 'Unauthorized';
Admin::save( $p );
p9ps_assert( ProductData::get( $p )['review_hash'] === $approved_hash, 'Subscriber cannot write food meta.' );
$p->set_status( 'draft' ); $p->save();
p9ps_assert( '' === Frontend::render( $p ), 'Shortcode cannot expose another user draft.' );
wp_set_current_user( $admin->ID );
$p->set_status( 'publish' ); $p->save();
wp_update_post( array( 'ID' => $p->get_id(), 'post_password' => 'test-secret' ) );
wp_set_current_user( 0 );
p9ps_assert( '' === Frontend::render( $p ), 'Password-protected product data cannot be exposed anonymously.' );
p9ps_assert( '' === do_shortcode( '[p9ps_food id="' . $p->get_id() . '"]' ), 'Shortcode respects product password protection.' );
wp_set_current_user( $admin->ID );
wp_update_post( array( 'ID' => $p->get_id(), 'post_password' => '' ) );

$item = new WC_Order_Item_Product();
$item->set_product( $p ); $item->set_quantity( 1 );
do_action( 'woocommerce_checkout_create_order_line_item', $item, 'test', array(), null );
$snapshot = $item->get_meta( '_p9ps_food_snapshot', true );
p9ps_assert( ! empty( $snapshot ) && ! array_key_exists( 'recipe', $snapshot ), 'Classic checkout snapshot excludes private recipe.' );
Frontend::snapshot( $item );
p9ps_assert( count( $item->get_meta_data() ) === 1, 'Snapshot is idempotent.' );
$order = wc_create_order();
$order->add_product( $p, 1 );
do_action( 'woocommerce_store_api_checkout_update_order_meta', $order );
$order_items = $order->get_items();
$store_item = reset( $order_items );
p9ps_assert( ! empty( $store_item->get_meta( '_p9ps_food_snapshot', true ) ), 'Store API checkout hook records declaration.' );
$order->delete( true );
$p->update_meta_data( ProductData::META, ProductData::defaults() ); $p->save();
p9ps_assert( $snapshot === $item->get_meta( '_p9ps_food_snapshot', true ), 'Existing order snapshot unchanged by catalog updates.' );

// Real HTTP boundary via WordPress pre_http_request, no traffic to OFF.
update_option( 'p9ps_off_enabled', 'yes', false );
delete_option( 'p9ps_off_lock' );
delete_transient( 'p9ps_off_12345678' );
$calls = 0;
$http = static function ( $pre, $args, $url ) use ( &$calls ) {
    if ( ! str_starts_with( $url, 'https://world.openfoodfacts.org/' ) ) { return $pre; }
    ++$calls;
    p9ps_assert( 0 === $args['redirection'], 'HTTP redirects disabled.' );
    p9ps_assert( str_starts_with( $args['user-agent'], 'P9ProductSettings/' ), 'Identifying User-Agent present.' );
    return array( 'headers' => array(), 'body' => wp_json_encode( array( 'product' => array( 'product_name_de' => 'Test', 'nutriments' => array( 'fat_100g' => 1 ) ) ) ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
};
add_filter( 'pre_http_request', $http, 10, 3 );
$a = Sources::off( '12345678' ); $b = Sources::off( '12345678' );
p9ps_assert( $calls === 1, 'Repeated barcode uses 24-hour cache.' );
p9ps_assert( $a === $b, 'Cached response preserves source snapshot.' );
try { Sources::off( '12345679' ); p9ps_assert( false, 'Rate limit expected.' ); } catch ( RuntimeException $e ) { p9ps_assert( $calls === 1, 'Rate limit blocks second network request.' ); }
remove_filter( 'pre_http_request', $http, 10 );
update_option( 'p9ps_off_enabled', 'no', false );
delete_option( 'p9ps_off_lock' ); delete_transient( 'p9ps_off_12345678' );

$p->delete( true );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $subscriber );
$_POST = array();
WP_CLI::success( 'P9 integration: ' . $GLOBALS['p9ps_checks'] . ' checks passed.' );

