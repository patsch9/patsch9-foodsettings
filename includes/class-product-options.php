<?php
namespace PATSCH9FS;

defined( 'ABSPATH' ) || exit;

final class ProductOptions {
	public static function init(): void {
		add_filter( 'product_type_options', array( __CLASS__, 'product_type_options' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'sync_request' ), 5 );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save_index_meta' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ), 20 );
	}

	public static function product_type_options( array $options ): array {
		$default = 'no';
		global $post;
		if ( $post instanceof \WP_Post && 'product' === $post->post_type ) {
			$product = wc_get_product( $post->ID );
			if ( $product ) {
				$draft = EditorDraft::get( $product );
				if ( null !== $draft ) {
					$default = ! empty( $draft['enabled'] ) ? 'yes' : 'no';
				} else {
					$indexed = $product->get_meta( ProductData::FOOD_ENABLED_META, true );
					$data = ProductData::get( $product );
					$default = 'yes' === $indexed || ( '' === $indexed && ! empty( $data['enabled'] ) ) ? 'yes' : 'no';
				}
			}
		}

		$options['patsch9fs_food_enabled'] = array(
			'id'            => ProductData::FOOD_ENABLED_META,
			'wrapper_class' => '',
			'label'         => __( 'Lebensmittel (P9)', 'patsch9-foodsettings' ),
			'description'   => __( 'Aktiviert die Lebensmitteldaten von Patsch9 Food Settings für dieses Produkt.', 'patsch9-foodsettings' ),
			'default'       => $default,
		);
		return $options;
	}

	private static function authorized( $product ): bool {
		$present = isset( $_POST['patsch9fs_present'] ) ? sanitize_text_field( wp_unslash( $_POST['patsch9fs_present'] ) ) : '';
		if ( '1' !== $present || ! isset( $_POST['patsch9fs_nonce'] ) ) {
			return false;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['patsch9fs_nonce'] ) );
		return wp_verify_nonce( $nonce, 'patsch9fs_save_food' ) && current_user_can( 'edit_post', $product->get_id() );
	}

	public static function sync_request( $product ): void {
		if ( ! self::authorized( $product ) ) {
			return;
		}
		// The header checkbox is the canonical switch. Synchronize it into the structured payload before Admin::save() validates it.
		// Nonce and capability are verified by self::authorized() immediately above.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! isset( $_POST['patsch9fs'] ) || ! is_array( $_POST['patsch9fs'] ) ) {
			$_POST['patsch9fs'] = array();
		}
		// Nonce and capability are verified by self::authorized() immediately above.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$food_enabled = isset( $_POST[ ProductData::FOOD_ENABLED_META ] ) && 'on' === sanitize_text_field( wp_unslash( $_POST[ ProductData::FOOD_ENABLED_META ] ) );
		if ( $food_enabled ) {
			$_POST['patsch9fs']['enabled'] = '1';
		} else {
			unset( $_POST['patsch9fs']['enabled'] );
		}

		// Nonce and capability are verified by self::authorized() immediately above.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$withdrawal_present = isset( $_POST['patsch9fs_withdrawal_present'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['patsch9fs_withdrawal_present'] ) );
		// If the enhancement script did not run, do not silently erase an existing legal checkout setting.
		if ( ! $withdrawal_present ) {
			$old = ProductData::get( $product );
			if ( ! empty( $old['withdrawal_ack'] ) ) {
				$_POST['patsch9fs']['withdrawal_ack'] = '1';
			}
		}
	}

	public static function save_index_meta( $product ): void {
		if ( ! self::authorized( $product ) ) {
			return;
		}
		// Admin::save() runs at priority 10. A recovery draft means validation failed,
		// so the persistent index/header metadata must remain untouched as well.
		if ( null !== EditorDraft::get( $product ) ) {
			return;
		}
		$data = ProductData::get( $product );
		$required = ! empty( $data['enabled'] ) && ! empty( $data['withdrawal_ack'] );
		$product->update_meta_data( ProductData::FOOD_ENABLED_META, ! empty( $data['enabled'] ) ? 'yes' : 'no' );
		if ( $required ) {
			$product->update_meta_data( ProductData::WITHDRAWAL_META, 'yes' );
		} else {
			$product->delete_meta_data( ProductData::WITHDRAWAL_META );
		}
		Checkout::set_product_requirement( $product->get_id(), $required );
	}

	public static function assets( string $hook ): void {
		unset( $hook );
		$screen = get_current_screen();
		if ( ! $screen || 'product' !== $screen->id ) {
			return;
		}
		$product = wc_get_product( get_the_ID() );
		if ( ! $product ) {
			return;
		}
		$data = EditorDraft::get( $product ) ?? ProductData::get( $product );
		wp_enqueue_script( 'patsch9fs-product-options', PATSCH9FS_URL . 'assets/product-options.js', array( 'jquery', 'patsch9fs-admin' ), PATSCH9FS_VERSION, true );
		wp_localize_script(
			'patsch9fs-product-options',
			'PATSCH9FS_PRODUCT_OPTIONS',
			array(
				'withdrawalAck'         => ! empty( $data['withdrawal_ack'] ),
				'withdrawalLabel'       => __( 'Checkout-Bestätigung für individuell angefertigte Lebensmittel verlangen', 'patsch9-foodsettings' ),
				'withdrawalDescription' => __( 'Nur aktivieren, wenn die Voraussetzungen der gesetzlichen Ausnahme tatsächlich erfüllt sind. Der Lebensmittelstatus allein schließt ein Widerrufsrecht nicht aus.', 'patsch9-foodsettings' ),
			)
		);
	}
}
