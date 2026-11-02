<?php
namespace PATSCH9FS;

defined( 'ABSPATH' ) || exit;

/** Private, temporary editor recovery. Never a source for public product data. */
final class EditorDraft {
	private static function key( $product ): string {
		return 'patsch9fs_draft_' . get_current_user_id() . '_' . $product->get_id();
	}
	private static function allowed( $product ): bool {
		return get_current_user_id() > 0 && current_user_can( 'edit_post', $product->get_id() ) && 'product' === get_post_type( $product->get_id() );
	}
	/** Preserve invalid numeric text for correction; Admin escapes it on output. */
	private static function number_input( $value ) {
		if ( null === $value || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}
		return is_string( $value ) ? wp_check_invalid_utf8( $value ) : 'Ungültige Angabe';
	}
	private static function nutrients( $values ): array {
		$values = is_array( $values ) ? $values : array();
		$out = array();
		foreach ( Nutrition::KEYS as $key ) {
			$out[ $key ] = self::number_input( $values[ $key ] ?? null );
		}
		return $out;
	}
	private static function normalize( array $input ): array {
		$data = ProductData::defaults();
		foreach ( ProductData::TEXT_FIELDS as $key ) {
			$data[ $key ] = sanitize_textarea_field( $input[ $key ] ?? '' );
		}
		foreach ( array( 'enabled', 'show_score', 'same_variations' ) as $key ) {
			$data[ $key ] = ! empty( $input[ $key ] );
		}
		foreach ( array( 'portion', 'alcohol', 'yield', 'fruit' ) as $key ) {
			$data[ $key ] = self::number_input( $input[ $key ] ?? null );
		}
		$data['mode'] = 'recipe' === ( $input['mode'] ?? '' ) ? 'recipe' : 'manual';
		$data['basis'] = 'ml' === ( $input['basis'] ?? '' ) ? 'ml' : 'g';
		$data['category'] = in_array( $input['category'] ?? '', Nutriscore::CATEGORIES, true ) ? $input['category'] : 'excluded';
		$data['sweeteners'] = in_array( $input['sweeteners'] ?? '', array( 'yes', 'no' ), true ) ? $input['sweeteners'] : '';
		$data['ingredients'] = ProductData::html( $input['ingredients'] ?? '' );
		$data['allergens'] = array_values( array_filter( ProductData::ALLERGEN_IDS, static fn( $id ) => in_array( $id, (array) ( $input['allergens'] ?? array() ), true ) ) );
		$data['nutrients'] = self::nutrients( $input['nutrients'] ?? array() );
		foreach ( (array) ( $input['recipe'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$data['recipe'][] = array(
				'name' => sanitize_text_field( $row['name'] ?? '' ), 'declaration' => ProductData::html( $row['declaration'] ?? '' ),
				'grams' => self::number_input( $row['grams'] ?? null ), 'nutrients' => self::nutrients( $row['nutrients'] ?? array() ),
				'source' => is_array( $row['source'] ?? null ) ? ProductData::source( $row['source'] ) : array(),
			);
		}
		foreach ( (array) ( $input['sources'] ?? array() ) as $source ) {
			$source = is_array( $source ) ? ProductData::source( $source ) : array();
			if ( $source ) {
				$data['sources'][] = $source;
			}
		}
		return $data;
	}
	public static function remember( $product, array $input ): bool {
		if ( ! self::allowed( $product ) ) {
			return false;
		}
		$json = wp_json_encode( $input );
		if ( ! is_string( $json ) || strlen( $json ) > 300000 || count( (array) ( $input['recipe'] ?? array() ) ) > 100 || count( (array) ( $input['sources'] ?? array() ) ) > 110 ) {
			return false;
		}
		return set_transient( self::key( $product ), self::normalize( $input ), HOUR_IN_SECONDS );
	}
	public static function get( $product ): ?array {
		if ( ! self::allowed( $product ) ) {
			return null;
		}
		$data = get_transient( self::key( $product ) );
		return is_array( $data ) ? $data : null;
	}
	public static function forget( $product ): void {
		if ( self::allowed( $product ) ) {
			delete_transient( self::key( $product ) );
		}
	}
}
