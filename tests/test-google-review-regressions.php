<?php
/** Focused regressions for the 2026-11-03 external review findings. */
require __DIR__ . '/bootstrap.php';
require_once PATSCH9FS_PATH . 'includes/class-editor-draft.php';
require_once PATSCH9FS_PATH . 'includes/class-checkout.php';

use PATSCH9FS\Checkout;
use PATSCH9FS\EditorDraft;
use PATSCH9FS\ProductData;
use PATSCH9FS\Sources;

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}

$review_checks = 0;
$GLOBALS['patsch9fs_review_transients'] = array();
$GLOBALS['patsch9fs_review_index_updates'] = 0;

function review_check( bool $condition, string $message ): void {
    global $review_checks;
    ++$review_checks;
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
}
function review_throws( callable $callback, string $message ): void {
    try {
        $callback();
    } catch ( Throwable $e ) {
        review_check( true, $message );
        return;
    }
    review_check( false, $message );
}

if ( ! function_exists( '__' ) ) {
    function __( $text, $domain = null ) { return $text; }
}
if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
}
if ( ! function_exists( 'absint' ) ) {
    function absint( $value ) { return abs( (int) $value ); }
}
if ( ! function_exists( 'wp_check_invalid_utf8' ) ) {
    function wp_check_invalid_utf8( $text ) { return (string) $text; }
}
if ( ! function_exists( 'get_current_user_id' ) ) {
    function get_current_user_id() { return 7; }
}
if ( ! function_exists( 'current_user_can' ) ) {
    function current_user_can( $capability, ...$args ) { return true; }
}
if ( ! function_exists( 'get_post_type' ) ) {
    function get_post_type( $post_id ) { return 'product'; }
}
if ( ! function_exists( 'set_transient' ) ) {
    function set_transient( $key, $value, $expiration ) {
        $GLOBALS['patsch9fs_review_transients'][ $key ] = $value;
        return true;
    }
}
if ( ! function_exists( 'get_transient' ) ) {
    function get_transient( $key ) { return $GLOBALS['patsch9fs_review_transients'][ $key ] ?? false; }
}
if ( ! function_exists( 'delete_transient' ) ) {
    function delete_transient( $key ) { unset( $GLOBALS['patsch9fs_review_transients'][ $key ] ); return true; }
}
if ( ! function_exists( 'get_option' ) ) {
    function get_option( $name, $default = false ) {
        if ( 'patsch9fs_withdrawal_product_ids' === $name ) {
            return array( 10, 20 );
        }
        return $default;
    }
}
if ( ! function_exists( 'update_option' ) ) {
    function update_option( $name, $value, $autoload = null ) {
        if ( 'patsch9fs_withdrawal_product_ids' === $name ) {
            ++$GLOBALS['patsch9fs_review_index_updates'];
        }
        return true;
    }
}
if ( ! function_exists( 'get_posts' ) ) {
    function get_posts( $args ) {
        if ( 'product' === ( $args['post_type'] ?? '' ) ) {
            return array( 20 );
        }
        if ( 'product_variation' === ( $args['post_type'] ?? '' ) ) {
            return array( 21 );
        }
        return array();
    }
}
if ( ! function_exists( 'wc_get_product' ) ) {
    function wc_get_product( $id ) { return null; }
}

final class ReviewDraftProduct {
    public function get_id(): int { return 44; }
}

$draft_product = new ReviewDraftProduct();
review_check(
    EditorDraft::remember(
        $draft_product,
        array(
            'enabled' => '1',
            'withdrawal_ack' => '1',
            'ingredients' => 'Zucker',
            'recipe' => array(),
            'sources' => array(),
        )
    ),
    'Editor recovery accepts the submitted legal setting.'
);
$draft = EditorDraft::get( $draft_product );
review_check( true === $draft['enabled'], 'Editor recovery keeps the food-enabled toggle.' );
review_check( true === $draft['withdrawal_ack'], 'Editor recovery keeps the withdrawal acknowledgement toggle.' );

$method = new ReflectionMethod( Checkout::class, 'indexed_product_ids' );
$ids = $method->invoke( null );
review_check( array( 20, 21 ) === $ids, 'Only published indexed parents and published variations are exposed to the block condition.' );
review_check( 0 === $GLOBALS['patsch9fs_review_index_updates'], 'Reading the checkout index never deletes draft/future products from persistent storage.' );

final class ReviewCheckoutProduct {
    private int $id;
    private bool $required;
    public function __construct( int $id, bool $required ) { $this->id = $id; $this->required = $required; }
    public function is_type( string $type ): bool { return false; }
    public function get_parent_id(): int { return 0; }
    public function get_id(): int { return $this->id; }
    public function get_meta( string $key, bool $single = true ) { return $this->required ? 'yes' : 'no'; }
}
final class ReviewCheckoutItem {
    private ReviewCheckoutProduct $product;
    public function __construct( ReviewCheckoutProduct $product ) { $this->product = $product; }
    public function get_product(): ReviewCheckoutProduct { return $this->product; }
}
final class ReviewCheckoutOrder {
    private array $items;
    private bool $checked;
    public array $meta = array();
    public array $deleted = array();
    public function __construct( array $items, bool $checked ) { $this->items = $items; $this->checked = $checked; }
    public function get_items(): array { return $this->items; }
    public function get_meta( string $key, bool $single = true ) {
        if ( '_wc_other/patsch9-foodsettings/withdrawal-ack' === $key ) {
            return $this->checked ? '1' : '';
        }
        return $this->meta[ $key ] ?? '';
    }
    public function update_meta_data( string $key, $value ): void { $this->meta[ $key ] = $value; }
    public function delete_meta_data( string $key ): void { $this->deleted[] = $key; unset( $this->meta[ $key ] ); }
}
final class ReviewCheckoutRequest {
    private string $method;
    public function __construct( string $method = 'POST' ) { $this->method = $method; }
    public function get_method(): string { return $this->method; }
}

$required_item = new ReviewCheckoutItem( new ReviewCheckoutProduct( 55, true ) );
$unchecked_order = new ReviewCheckoutOrder( array( $required_item ), false );
review_throws(
    static fn() => Checkout::handle_block_acknowledgement( $unchecked_order, new ReviewCheckoutRequest() ),
    'A Store API POST cannot complete without the required acknowledgement.'
);

$checked_order = new ReviewCheckoutOrder( array( $required_item ), true );
Checkout::handle_block_acknowledgement( $checked_order, new ReviewCheckoutRequest() );
review_check( 'yes' === ( $checked_order->meta['_patsch9fs_withdrawal_acknowledged'] ?? '' ), 'A valid block acknowledgement is recorded.' );
review_check( array( 55 ) === ( $checked_order->meta['_patsch9fs_withdrawal_acknowledgement_products'] ?? array() ), 'Recorded acknowledgement contains the relevant product IDs.' );

$irrelevant_order = new ReviewCheckoutOrder( array( new ReviewCheckoutItem( new ReviewCheckoutProduct( 66, false ) ) ), true );
Checkout::handle_block_acknowledgement( $irrelevant_order, new ReviewCheckoutRequest() );
review_check( ! isset( $irrelevant_order->meta['_patsch9fs_withdrawal_acknowledged'] ), 'A forged/stale field cannot create acknowledgement metadata for irrelevant orders.' );
review_check( count( $irrelevant_order->deleted ) >= 1, 'Stale acknowledgement metadata is cleared for irrelevant orders.' );

$off = Sources::normalize_off(
    array(
        'product_name_de' => '',
        'product_name' => 'Fallback Name',
        'ingredients_text_with_allergens_de' => '',
        'ingredients_text_de' => '',
        'ingredients_text' => 'Zucker, Kakao',
        'nutriments' => array(),
    ),
    '12345678'
);
review_check( 'Fallback Name' === $off['name'], 'Open Food Facts falls back from an empty localized product name.' );
review_check( 'Zucker, Kakao' === $off['declaration'], 'Open Food Facts falls back from empty localized ingredient fields.' );
review_check( 'Fallback Name' === $off['source']['name'], 'The source snapshot uses the same resolved fallback name.' );

$plugin = file_get_contents( PATSCH9FS_PATH . 'patsch9-foodsettings.php' );
review_check( str_contains( $plugin, 'WC requires at least: 9.9' ), 'Plugin metadata declares the WooCommerce version required by conditional checkout fields.' );
$cleanup = file_get_contents( PATSCH9FS_PATH . '.github/scripts/cleanup_release_artifacts.py' );
review_check( str_contains( $cleanup, 'ARTIFACT_PREFIX = "patsch9-foodsettings-"' ), 'Artifact cleanup uses the Food Settings prefix.' );
review_check( ! str_contains( $cleanup, 'p9-rezeptwerk-' ), 'Artifact cleanup no longer contains the copied Rezeptwerk prefix.' );

echo "PASS: $review_checks Google-review regression checks.\n";
