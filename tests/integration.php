<?php
/** Run with: wp eval-file tests/integration.php (throwaway WordPress + WooCommerce only). */
use PATSCH9FS\Admin;
use PATSCH9FS\EditorDraft;
use PATSCH9FS\Frontend;
use PATSCH9FS\Nutrition;
use PATSCH9FS\ProductData;
use PATSCH9FS\Sources;

if ( '1' !== getenv( 'PATSCH9FS_RUN_INTEGRATION_TESTS' ) || ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( 'WooCommerce' ) ) {
    throw new RuntimeException( 'A throwaway WordPress + WooCommerce installation and PATSCH9FS_RUN_INTEGRATION_TESTS=1 are required.' );
}
if ( ! class_exists( 'WC_Admin_Meta_Boxes' ) ) {
    require_once WC_ABSPATH . 'includes/admin/class-wc-admin-meta-boxes.php';
}
$GLOBALS['patsch9fs_checks'] = 0;
function patsch9fs_assert( $ok, $message ) {
    ++$GLOBALS['patsch9fs_checks'];
    if ( ! $ok ) { throw new RuntimeException( $message ); }
}
function patsch9fs_ajax_request( array $request ): array {
    if ( ! defined( 'DOING_AJAX' ) ) { define( 'DOING_AJAX', true ); }
    $old_post = $_POST; $old_request = $_REQUEST;
    $_POST = $request; $_REQUEST = $request;
    $handler = static function () {
        return static function ( $message ) { throw new RuntimeException( 'patsch9fs-ajax-stop:' . (string) $message ); };
    };
    add_filter( 'wp_die_ajax_handler', $handler );
    ob_start();
    try {
        Admin::ajax();
        throw new RuntimeException( 'AJAX failed to terminate.' );
    } catch ( RuntimeException $error ) {
        if ( ! str_starts_with( $error->getMessage(), 'patsch9fs-ajax-stop:' ) ) { throw $error; }
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
$ajax = array( 'nonce' => wp_create_nonce( 'patsch9fs_admin' ), 'product_id' => $p->get_id(), 'task' => 'search', 'query' => 'Weizen Mehl' );
$ajax_result = patsch9fs_ajax_request( $ajax );
patsch9fs_assert( true === $ajax_result['response']['success'] && count( $ajax_result['response']['data'] ) > 0, 'Authorized AJAX searches bundled BLS data.' );
$unicode_ajax = $ajax; $unicode_ajax['query'] = 'Ö';
patsch9fs_assert( false === patsch9fs_ajax_request( $unicode_ajax )['response']['success'], 'Single multibyte character does not pass the two-character minimum.' );
$unicode_ajax['query'] = 'Öl';
patsch9fs_assert( true === patsch9fs_ajax_request( $unicode_ajax )['response']['success'], 'Two-character umlaut query is accepted.' );
$unicode_ajax['query'] = str_repeat( 'ä', 80 );
patsch9fs_assert( true === patsch9fs_ajax_request( $unicode_ajax )['response']['success'], 'Eighty Unicode characters are accepted independently of UTF-8 byte length.' );
$unicode_ajax['query'] .= 'ä';
patsch9fs_assert( false === patsch9fs_ajax_request( $unicode_ajax )['response']['success'], 'Eighty-one Unicode characters exceed the search limit.' );
$invalid_ajax = $ajax; $invalid_ajax['nonce'] = 'invalid';
patsch9fs_assert( 'patsch9fs-ajax-stop:-1' === patsch9fs_ajax_request( $invalid_ajax )['stopped'], 'Invalid AJAX nonce is rejected before processing.' );
$invalid_ajax = $ajax; $invalid_ajax['product_id'] = 0;
patsch9fs_assert( false === patsch9fs_ajax_request( $invalid_ajax )['response']['success'], 'AJAX cannot operate without a real editable product.' );
$ingredient_notes = "E102, E110, E129: Kann Aktivität und Aufmerksamkeit bei Kindern beeinträchtigen.\nKakao: 35 % mindestens in der verwendeten Schokolade.\nKakao: 54,5 % mindestens in der verwendeten Schokoladenkuvertüre.\nKakao: 31,7 % mindestens in der verwendeten Milchschokoladenkuvertüre.";
$_POST = array(
    'patsch9fs_present' => '1', 'patsch9fs_nonce' => wp_create_nonce( 'patsch9fs_save_food' ), 'patsch9fs_approve' => '1',
    'patsch9fs' => array( 'enabled' => '1', 'ingredients' => '<strong onclick="alert(1)">MILCH</strong>, Zucker<img src=x onerror="alert(1)">', 'ingredient_notes' => $ingredient_notes . '<img src=x onerror="alert(1)">', 'operator' => 'Hersteller, Straße 1, Ort', 'nutrients' => array_fill_keys( Nutrition::KEYS, '0' ) ),
    'patsch9fs_recipe_json' => '[]', 'patsch9fs_sources_json' => '[]',
);
Admin::save( $p ); $p->save();
$d = ProductData::get( wc_get_product( $p->get_id() ) );
patsch9fs_assert( ProductData::approved( $d ), 'Valid saved declaration is approved.' );
$html = Frontend::render( $p );
patsch9fs_assert( str_contains( $html, '<strong>MILCH</strong>' ), 'Safe allergen emphasis retained.' );
patsch9fs_assert( ! str_contains( $html, 'onclick' ) && ! str_contains( $html, '<img' ), 'External markup cannot execute.' );
patsch9fs_assert( str_contains( $html, 'kJ / 0 kcal' ), 'Zero-valued nutrition table rendered.' );
patsch9fs_assert( str_contains( do_shortcode( '[patsch9fs_food id="' . $p->get_id() . '"]' ), 'MILCH' ), 'Shortcode renders approved food.' );
patsch9fs_assert( $ingredient_notes === $d['ingredient_notes'], 'Sanitized multiline ingredient notes survive a real product save.' );
patsch9fs_assert( str_contains( $html, 'patsch9fs-ingredient-notes' ) && str_contains( $html, '31,7 %' ) && str_contains( $html, '54,5 %' ), 'All cocoa notes appear in the public declaration.' );
patsch9fs_assert( str_contains( $html, "beeinträchtigen.<br />\nKakao:" ), 'Public notes retain line breaks.' );
patsch9fs_assert( strpos( $html, '<h4>Zutaten</h4>' ) < strpos( $html, '<h4>Zutatenhinweise</h4>' ), 'Notes are placed below the ingredient list.' );
$_POST['patsch9fs']['ingredient_notes'] = $ingredient_notes;

unset( $_POST['patsch9fs_approve'] );
Admin::save( $p ); $p->save();
patsch9fs_assert( ProductData::approved( ProductData::get( $p ) ), 'Unchanged save retains approval.' );
$_POST['patsch9fs']['ingredient_notes'] .= "\nWeiterer geprüfter Hinweis.";
Admin::save( $p ); $p->save();
patsch9fs_assert( ! ProductData::approved( ProductData::get( $p ) ) && '' === Frontend::render( $p ), 'Changing only ingredient notes revokes shop publication.' );
$_POST['patsch9fs']['ingredient_notes'] = $ingredient_notes;
$_POST['patsch9fs_approve'] = '1';
Admin::save( $p ); $p->save();
unset( $_POST['patsch9fs_approve'] );
$_POST['patsch9fs']['ingredients'] .= ', Wasser';
Admin::save( $p ); $p->save();
patsch9fs_assert( ! ProductData::approved( ProductData::get( $p ) ), 'Changed declaration loses approval.' );
patsch9fs_assert( '' === Frontend::render( $p ), 'Unapproved data not visible.' );
$_POST['patsch9fs_approve'] = '1';
Admin::save( $p ); $p->save();
$approved_hash = ProductData::get( $p )['review_hash'];
$valid_nonce = $_POST['patsch9fs_nonce'];
$_POST['patsch9fs_nonce'] = 'invalid';
$_POST['patsch9fs']['ingredients'] = 'Invalid nonce attempt';
Admin::save( $p );
patsch9fs_assert( ProductData::get( $p )['review_hash'] === $approved_hash, 'Invalid nonce cannot change approved data.' );
unset( $_POST['patsch9fs_nonce'] );
Admin::save( $p );
patsch9fs_assert( ProductData::get( $p )['review_hash'] === $approved_hash, 'Missing nonce cannot change approved data.' );
$_POST['patsch9fs_nonce'] = $valid_nonce;
$_POST['patsch9fs']['ingredients'] = ProductData::get( $p )['ingredients'];
$_POST['patsch9fs']['nutrients']['fat'] = '-1';
Admin::save( $p ); $p->save();
patsch9fs_assert( ProductData::get( $p )['review_hash'] === $approved_hash, 'Invalid numeric save leaves existing data intact.' );
$draft = EditorDraft::get( $p );
patsch9fs_assert( '-1' === $draft['nutrients']['fat'], 'Invalid numeric editor input survives failed save in a private draft.' );
patsch9fs_assert( $ingredient_notes === $draft['ingredient_notes'], 'Ingredient notes survive failed validation in private editor recovery.' );
patsch9fs_assert( ! ProductData::approved( $draft ), 'Recovery data never has a publication approval.' );
$second_editor = wp_insert_user( array( 'user_login' => 'patsch9fs-recovery-' . wp_generate_password( 8, false ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
patsch9fs_assert( ! is_wp_error( $second_editor ), 'Second authorized editor fixture exists.' );
wp_set_current_user( $second_editor );
patsch9fs_assert( current_user_can( 'edit_post', $p->get_id() ) && null === EditorDraft::get( $p ), 'Even another authorized editor cannot read the administrator recovery.' );
EditorDraft::forget( $p );
wp_set_current_user( $admin->ID );
patsch9fs_assert( '-1' === EditorDraft::get( $p )['nutrients']['fat'], 'An authorized editor can discard only their own recovery.' );
$other_product = new WC_Product_Simple(); $other_product->set_name( 'Other recovery scope' ); $other_product->save();
patsch9fs_assert( null === EditorDraft::get( $other_product ), 'Recovery is isolated per product.' );
$other_product->delete( true );
$_POST['patsch9fs']['ingredients'] = '<strong onclick="alert(1)">NEUE ZUTAT</strong>';
$_POST['patsch9fs']['nutrients']['fat'] = '"><img src=x onerror=alert(1)>';
Admin::save( $p ); $p->save();
$draft = EditorDraft::get( $p );
patsch9fs_assert( '<strong>NEUE ZUTAT</strong>' === $draft['ingredients'], 'Recoverable ingredient emphasis is sanitized without losing new text.' );
patsch9fs_assert( ! str_contains( Frontend::render( $p ), 'NEUE ZUTAT' ), 'Recovery input cannot replace the approved public declaration.' );
$GLOBALS['product_object'] = $p;
ob_start(); Admin::panel(); $recovery_html = ob_get_clean();
unset( $GLOBALS['product_object'] );
patsch9fs_assert( str_contains( $recovery_html, 'wiederhergestellt' ) && str_contains( $recovery_html, 'NEUE ZUTAT' ), 'Editor actually restores inputs after redirect.' );
patsch9fs_assert( ! str_contains( $recovery_html, '<img src=x' ) && ! str_contains( $recovery_html, 'onclick="alert' ), 'Recovered invalid numeric and HTML values cannot execute in the editor.' );
$invalid_input = $_POST;
$_POST['patsch9fs']['ingredients'] = 'Gespeicherte Korrektur'; $_POST['patsch9fs']['nutrients']['fat'] = '0';
unset( $_POST['patsch9fs_approve'] );
Admin::save( $p ); $p->save();
patsch9fs_assert( null === EditorDraft::get( $p ), 'Successful correction clears editor recovery.' );
patsch9fs_assert( ! ProductData::approved( ProductData::get( $p ) ), 'Corrected recovery input still requires explicit approval.' );
$corrected_post = $_POST;
$_POST['patsch9fs_recipe_json'] = '{not valid json';
Admin::save( $p );
patsch9fs_assert( null === EditorDraft::get( $p ), 'Malformed JSON is not misreported as a recoverable empty recipe.' );
$_POST = $corrected_post;
$_POST['patsch9fs']['ingredients'] = 'Nur Zutaten'; $_POST['patsch9fs']['operator'] = ''; $_POST['patsch9fs_approve'] = '1';
Admin::save( $p ); $p->save();
patsch9fs_assert( ! str_contains( Frontend::render( $p ), 'patsch9fs-details' ), 'No empty details definition list is emitted.' );
$_POST['patsch9fs']['operator'] = 'Hersteller';
Admin::save( $p ); $p->save();
patsch9fs_assert( str_contains( Frontend::render( $p ), '<dl class="patsch9fs-details">' ), 'Populated details retain their definition list.' );
$approved_hash = ProductData::get( $p )['review_hash'];
$_POST = $invalid_input;
Admin::save( $p ); $p->save();

$manual_post = $_POST;
$recipe_product = new WC_Product_Simple();
$recipe_product->set_name( 'P9 Recipe Integration Test' ); $recipe_product->save();
$row_nutrients = array_fill_keys( Nutrition::KEYS, 0 );
$row_nutrients['carbs'] = 100; $row_nutrients['sugars'] = 100;
$_POST = array(
    'patsch9fs_present' => '1', 'patsch9fs_nonce' => wp_create_nonce( 'patsch9fs_save_food' ), 'patsch9fs_approve' => '1',
    'patsch9fs' => array( 'enabled' => '1', 'ingredients' => 'Zucker, Wasser', 'mode' => 'recipe', 'basis' => 'g', 'yield' => '200' ),
    'patsch9fs_recipe_json' => wp_slash( wp_json_encode( array( array( 'name' => 'Zucker', 'grams' => 100, 'nutrients' => $row_nutrients ) ) ) ),
    'patsch9fs_sources_json' => '[]',
);
Admin::save( $recipe_product ); $recipe_product->save();
$recipe_data = ProductData::get( $recipe_product );
patsch9fs_assert( 50.0 === $recipe_data['nutrients']['sugars'], 'Server calculates recipe on save from actual JSON form input.' );
patsch9fs_assert( ProductData::approved( $recipe_data ), 'Recipe is approved after valid explicit review.' );
$recipe_hash = $recipe_data['review_hash'];
$_POST['patsch9fs']['basis'] = 'ml'; $_POST['patsch9fs']['ingredients'] = 'Neue Rezeptfassung';
Admin::save( $recipe_product ); $recipe_product->save();
$recipe_draft = EditorDraft::get( $recipe_product );
patsch9fs_assert( 'ml' === $recipe_draft['basis'] && 'Neue Rezeptfassung' === $recipe_draft['ingredients'], 'Invalid recipe unit retains both the choice and new ingredient text.' );
patsch9fs_assert( 100 === $recipe_draft['recipe'][0]['grams'] && 100 === $recipe_draft['recipe'][0]['nutrients']['sugars'], 'All recipe snapshot values survive validation failure.' );
patsch9fs_assert( $recipe_hash === ProductData::get( $recipe_product )['review_hash'], 'Invalid recipe draft does not replace the approved stored recipe.' );
EditorDraft::forget( $recipe_product );
patsch9fs_assert( null === EditorDraft::get( $recipe_product ), 'Discarding recovery removes only the private draft.' );
$_POST['patsch9fs']['basis'] = 'g';
Admin::save( $recipe_product ); $recipe_product->save();
patsch9fs_assert( null === EditorDraft::get( $recipe_product ) && 'Neue Rezeptfassung' === ProductData::get( $recipe_product )['ingredients'], 'Corrected recipe is saved through the normal validation path.' );
$recipe_product->delete( true ); $_POST = $manual_post;

$subscriber = wp_create_user( 'patsch9fs-subscriber', wp_generate_password(), 'patsch9fs-subscriber@example.invalid' );
wp_set_current_user( $subscriber );
patsch9fs_assert( null === EditorDraft::get( $p ), 'Another user cannot read the administrator recovery draft.' );
patsch9fs_assert( ! EditorDraft::remember( $p, array( 'ingredients' => 'Unauthorized draft' ) ), 'Product authorization is also required to write recovery data.' );
EditorDraft::forget( $p );
$subscriber_ajax = $ajax; $subscriber_ajax['nonce'] = wp_create_nonce( 'patsch9fs_admin' );
patsch9fs_assert( false === patsch9fs_ajax_request( $subscriber_ajax )['response']['success'], 'A valid nonce does not grant a subscriber AJAX access.' );
$subscriber_user = new WP_User( $subscriber );
$subscriber_user->add_cap( 'edit_products' );
wp_set_current_user( 0 ); wp_set_current_user( $subscriber );
patsch9fs_assert( current_user_can( 'edit_products' ) && ! current_user_can( 'edit_post', $p->get_id() ), 'Fixture grants general editing but not access to the admin product.' );
patsch9fs_assert( false === patsch9fs_ajax_request( $subscriber_ajax )['response']['success'], 'General product editing capability does not bypass per-product authorization.' );
$subscriber_user->remove_cap( 'edit_products' );
$_POST['patsch9fs']['ingredients'] = 'Unauthorized';
Admin::save( $p );
patsch9fs_assert( ProductData::get( $p )['review_hash'] === $approved_hash, 'Subscriber cannot write food meta.' );
$p->set_status( 'draft' ); $p->save();
patsch9fs_assert( '' === Frontend::render( $p ), 'Shortcode cannot expose another user draft.' );
wp_set_current_user( $admin->ID );
patsch9fs_assert( null !== EditorDraft::get( $p ), 'An unauthorized user cannot discard another user recovery.' );
patsch9fs_assert( ! EditorDraft::remember( $p, array( 'ingredients' => str_repeat( 'x', 300001 ) ) ), 'Oversized recovery payload is rejected.' );
EditorDraft::forget( $p );
$p->set_status( 'publish' ); $p->save();
wp_update_post( array( 'ID' => $p->get_id(), 'post_password' => 'test-secret' ) );
wp_set_current_user( 0 );
patsch9fs_assert( '' === Frontend::render( $p ), 'Password-protected product data cannot be exposed anonymously.' );
patsch9fs_assert( '' === do_shortcode( '[patsch9fs_food id="' . $p->get_id() . '"]' ), 'Shortcode respects product password protection.' );
wp_set_current_user( $admin->ID );
wp_update_post( array( 'ID' => $p->get_id(), 'post_password' => '' ) );

$item = new WC_Order_Item_Product();
$item->set_product( $p ); $item->set_quantity( 1 );
do_action( 'woocommerce_checkout_create_order_line_item', $item, 'test', array(), null );
$snapshot = $item->get_meta( '_patsch9fs_food_snapshot', true );
patsch9fs_assert( ! empty( $snapshot ) && ! array_key_exists( 'recipe', $snapshot ), 'Classic checkout snapshot excludes private recipe.' );
patsch9fs_assert( $ingredient_notes === $snapshot['ingredient_notes'], 'Classic checkout archives the ingredient notes.' );
Frontend::snapshot( $item );
patsch9fs_assert( count( $item->get_meta_data() ) === 1, 'Snapshot is idempotent.' );
$order = wc_create_order();
$order->add_product( $p, 1 );
do_action( 'woocommerce_store_api_checkout_update_order_meta', $order );
$order_items = $order->get_items();
$store_item = reset( $order_items );
patsch9fs_assert( ! empty( $store_item->get_meta( '_patsch9fs_food_snapshot', true ) ), 'Store API checkout hook records declaration.' );
patsch9fs_assert( $ingredient_notes === $store_item->get_meta( '_patsch9fs_food_snapshot', true )['ingredient_notes'], 'Store API checkout archives the ingredient notes.' );
$order->delete( true );
$p->update_meta_data( ProductData::META, ProductData::defaults() ); $p->save();
patsch9fs_assert( $snapshot === $item->get_meta( '_patsch9fs_food_snapshot', true ), 'Existing order snapshot unchanged by catalog updates.' );

// Real HTTP boundary via WordPress pre_http_request, no traffic to OFF.
update_option( 'patsch9fs_off_enabled', 'yes', false );
delete_option( 'patsch9fs_off_lock' );
delete_transient( 'patsch9fs_off_12345678' );
$calls = 0;
$http = static function ( $pre, $args, $url ) use ( &$calls ) {
    if ( ! str_starts_with( $url, 'https://world.openfoodfacts.org/' ) ) { return $pre; }
    ++$calls;
    patsch9fs_assert( 0 === $args['redirection'], 'HTTP redirects disabled.' );
    patsch9fs_assert( str_starts_with( $args['user-agent'], 'Patsch9FoodSettings/' ), 'Identifying User-Agent present.' );
    return array( 'headers' => array(), 'body' => wp_json_encode( array( 'product' => array( 'product_name_de' => 'Test', 'nutriments' => array( 'fat_100g' => 1 ) ) ) ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
};
add_filter( 'pre_http_request', $http, 10, 3 );
$a = Sources::off( '12345678' ); $b = Sources::off( '12345678' );
patsch9fs_assert( $calls === 1, 'Repeated barcode uses 24-hour cache.' );
patsch9fs_assert( $a === $b, 'Cached response preserves source snapshot.' );
try { Sources::off( '12345679' ); patsch9fs_assert( false, 'Rate limit expected.' ); } catch ( RuntimeException $e ) { patsch9fs_assert( $calls === 1, 'Rate limit blocks second network request.' ); }
remove_filter( 'pre_http_request', $http, 10 );
update_option( 'patsch9fs_off_enabled', 'no', false );
delete_option( 'patsch9fs_off_lock' ); delete_transient( 'patsch9fs_off_12345678' );

$p->delete( true );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $subscriber );
wp_delete_user( $second_editor );
$_POST = array();
WP_CLI::success( 'P9 integration: ' . $GLOBALS['patsch9fs_checks'] . ' checks passed.' );

