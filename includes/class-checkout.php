<?php
namespace PATSCH9FS;

defined( 'ABSPATH' ) || exit;

final class Checkout {
	private const BLOCK_FIELD_ID = 'patsch9-foodsettings/withdrawal-ack';
	private const CLASSIC_FIELD_ID = 'patsch9fs_withdrawal_ack';
	private const INDEX_OPTION = 'patsch9fs_withdrawal_product_ids';

	public static function init(): void {
		add_action( 'woocommerce_init', array( __CLASS__, 'register_block_field' ), 20 );
		add_action( 'woocommerce_review_order_before_submit', array( __CLASS__, 'render_classic_field' ) );
		add_action( 'woocommerce_checkout_process', array( __CLASS__, 'validate_classic_field' ) );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'save_classic_acknowledgement' ), 20, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'handle_block_acknowledgement' ), 50, 2 );
	}

	public static function set_product_requirement( int $product_id, bool $required ): void {
		$ids = get_option( self::INDEX_OPTION, array() );
		$ids = is_array( $ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ) : array();
		if ( $required ) {
			$ids[] = absint( $product_id );
		} else {
			$ids = array_values( array_diff( $ids, array( absint( $product_id ) ) ) );
		}
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		sort( $ids, SORT_NUMERIC );
		update_option( self::INDEX_OPTION, $ids, false );
	}

	private static function acknowledgement_label(): string {
		return __( 'Ich bestätige, dass die entsprechend gekennzeichneten Lebensmittel nach meiner individuellen Auswahl oder Bestimmung angefertigt werden, und habe zur Kenntnis genommen, dass für diese Waren nach § 312g Abs. 2 Nr. 1 BGB kein Widerrufsrecht besteht.', 'patsch9-foodsettings' );
	}

	private static function acknowledgement_error(): string {
		return __( 'Bitte bestätige den Hinweis zum Widerrufsrecht für die individuell angefertigten Lebensmittel.', 'patsch9-foodsettings' );
	}

	private static function product_requires_acknowledgement( $product ): bool {
		if ( ! $product ) {
			return false;
		}
		if ( $product->is_type( 'variation' ) ) {
			$product = wc_get_product( $product->get_parent_id() );
		}
		if ( ! $product ) {
			return false;
		}
		$indexed = $product->get_meta( ProductData::WITHDRAWAL_META, true );
		if ( 'yes' === $indexed ) {
			return true;
		}
		if ( '' !== $indexed ) {
			return false;
		}
		$data = ProductData::get( $product );
		return ! empty( $data['enabled'] ) && ! empty( $data['withdrawal_ack'] );
	}

	private static function cart_requires_acknowledgement(): bool {
		if ( ! WC()->cart ) {
			return false;
		}
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'] ?? null;
			if ( self::product_requires_acknowledgement( $product ) ) {
				return true;
			}
		}
		return false;
	}

	private static function cart_acknowledgement_product_ids(): array {
		$ids = array();
		if ( ! WC()->cart ) {
			return $ids;
		}
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'] ?? null;
			if ( ! self::product_requires_acknowledgement( $product ) || ! $product ) {
				continue;
			}
			$ids[] = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		}
		return array_values( array_unique( array_map( 'absint', $ids ) ) );
	}

	private static function order_acknowledgement_product_ids( $order ): array {
		$ids = array();
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! self::product_requires_acknowledgement( $product ) || ! $product ) {
				continue;
			}
			$ids[] = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		}
		return array_values( array_unique( array_map( 'absint', $ids ) ) );
	}

	private static function indexed_product_ids(): array {
		$stored = get_option( self::INDEX_OPTION, array() );
		$parents = is_array( $stored ) ? array_values( array_unique( array_filter( array_map( 'absint', $stored ) ) ) ) : array();
		if ( ! $parents ) {
			return array();
		}

		// Keep drafts/future products in the persistent index. Publication state is
		// evaluated at read time so Quick Edit, REST and scheduled publishing work.
		$published_parents = get_posts(
			array(
				'post_type'              => 'product',
				'post_status'            => 'publish',
				'post__in'               => $parents,
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'orderby'                => 'post__in',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$published_parents = array_values( array_unique( array_filter( array_map( 'absint', $published_parents ) ) ) );
		if ( ! $published_parents ) {
			return array();
		}

		$variations = get_posts(
			array(
				'post_type'              => 'product_variation',
				'post_status'            => 'publish',
				'post_parent__in'        => $published_parents,
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return array_values( array_unique( array_filter( array_map( 'absint', array_merge( $published_parents, $variations ) ) ) ) );
	}

	public static function register_block_field(): void {
		// Conditional required/hidden schemas were added in WooCommerce 9.9.
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) || ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, '9.9', '<' ) ) {
			return;
		}
		$ids = self::indexed_product_ids();
		if ( ! $ids ) {
			return;
		}

		$contains = array(
			'type'     => 'array',
			'contains' => array( 'enum' => $ids ),
		);
		$required = array(
			'type'       => 'object',
			'properties' => array(
				'cart' => array(
					'type'       => 'object',
					'properties' => array( 'items' => $contains ),
				),
			),
		);
		$hidden = array(
			'type'       => 'object',
			'properties' => array(
				'cart' => array(
					'type'       => 'object',
					'properties' => array(
						'items' => array(
							'type' => 'array',
							'not'  => array( 'contains' => array( 'enum' => $ids ) ),
						),
					),
				),
			),
		);

		woocommerce_register_additional_checkout_field(
			array(
				'id'            => self::BLOCK_FIELD_ID,
				'label'         => self::acknowledgement_label(),
				'optionalLabel' => self::acknowledgement_label(),
				'location'      => 'order',
				'type'          => 'checkbox',
				'required'      => $required,
				'hidden'        => $hidden,
				'error_message' => self::acknowledgement_error(),
			)
		);
	}

	public static function render_classic_field(): void {
		if ( ! self::cart_requires_acknowledgement() ) {
			return;
		}
		// WooCommerce validates the checkout request nonce before processing checkout fields.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$value = isset( $_POST[ self::CLASSIC_FIELD_ID ] ) ? '1' : '';
		woocommerce_form_field(
			self::CLASSIC_FIELD_ID,
			array(
				'type'     => 'checkbox',
				'class'    => array( 'form-row-wide', 'patsch9fs-withdrawal-ack' ),
				'required' => true,
				'label'    => self::acknowledgement_label(),
			),
			$value
		);
	}

	public static function validate_classic_field(): void {
		// WooCommerce validates the checkout request nonce before this validation hook runs.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( self::cart_requires_acknowledgement() && empty( $_POST[ self::CLASSIC_FIELD_ID ] ) ) {
			wc_add_notice( self::acknowledgement_error(), 'error' );
		}
	}

	private static function record_acknowledgement( $order, string $source, array $product_ids ): void {
		$order->update_meta_data( '_patsch9fs_withdrawal_acknowledged', 'yes' );
		$order->update_meta_data( '_patsch9fs_withdrawal_acknowledged_at', gmdate( 'c' ) );
		$order->update_meta_data( '_patsch9fs_withdrawal_acknowledgement_text', self::acknowledgement_label() );
		$order->update_meta_data( '_patsch9fs_withdrawal_acknowledgement_source', sanitize_key( $source ) );
		$order->update_meta_data( '_patsch9fs_withdrawal_acknowledgement_products', array_values( array_map( 'absint', $product_ids ) ) );
	}

	private static function clear_acknowledgement( $order ): void {
		foreach ( array( '_patsch9fs_withdrawal_acknowledged', '_patsch9fs_withdrawal_acknowledged_at', '_patsch9fs_withdrawal_acknowledgement_text', '_patsch9fs_withdrawal_acknowledgement_source', '_patsch9fs_withdrawal_acknowledgement_products' ) as $key ) {
			$order->delete_meta_data( $key );
		}
	}

	public static function save_classic_acknowledgement( $order, array $data ): void {
		unset( $data );
		// WooCommerce validates the checkout request nonce before creating the order.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( self::cart_requires_acknowledgement() && ! empty( $_POST[ self::CLASSIC_FIELD_ID ] ) ) {
			self::record_acknowledgement( $order, 'classic', self::cart_acknowledgement_product_ids() );
		}
	}

	private static function block_field_checked( $order ): bool {
		if ( class_exists( '\Automattic\WooCommerce\Blocks\Package' ) && class_exists( '\Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields' ) ) {
			try {
				$fields = \Automattic\WooCommerce\Blocks\Package::container()->get( \Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class );
				return true === $fields->get_field_from_object( self::BLOCK_FIELD_ID, $order, 'other' );
			} catch ( \Throwable $e ) {
				// Fall through to the documented persisted meta format for compatibility with unexpected container failures.
			}
		}
		return '1' === (string) $order->get_meta( '_wc_other/' . self::BLOCK_FIELD_ID, true );
	}

	public static function handle_block_acknowledgement( $order, $request ): void {
		if ( ! is_object( $request ) || ! method_exists( $request, 'get_method' ) || 'POST' !== strtoupper( (string) $request->get_method() ) ) {
			return;
		}

		$product_ids = self::order_acknowledgement_product_ids( $order );
		if ( ! $product_ids ) {
			// A stale/malicious checkbox value must never create an acknowledgement
			// record when the order contains no product requiring it.
			self::clear_acknowledgement( $order );
			return;
		}
		if ( self::block_field_checked( $order ) ) {
			self::record_acknowledgement( $order, 'block', $product_ids );
			return;
		}

		$message = self::acknowledgement_error();
		if ( class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'patsch9fs_withdrawal_ack_required', esc_html( $message ), 400 );
		}
		throw new \RuntimeException( esc_html( $message ) );
	}
}
