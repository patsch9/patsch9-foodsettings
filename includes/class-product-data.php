<?php
namespace PATSCH9FS;

defined( 'ABSPATH' ) || exit;

final class ProductData {
	public const META = '_patsch9fs_food';
	public const ALLERGEN_IDS = array( 'en:gluten', 'en:crustaceans', 'en:eggs', 'en:fish', 'en:peanuts', 'en:soybeans', 'en:milk', 'en:nuts', 'en:celery', 'en:mustard', 'en:sesame-seeds', 'en:sulphur-dioxide-and-sulphites', 'en:lupin', 'en:molluscs' );
	public const TEXT_FIELDS = array( 'designation', 'net_quantity', 'drained_weight', 'operator', 'origin', 'storage', 'instructions', 'shelf_life', 'traces', 'ingredient_notes' );

	public static function allergens(): array {
		return array_combine( self::ALLERGEN_IDS, array( 'Glutenhaltiges Getreide', 'Krebstiere', 'Eier', 'Fisch', 'Erdnüsse', 'Soja', 'Milch (einschl. Laktose)', 'Schalenfrüchte', 'Sellerie', 'Senf', 'Sesam', 'Schwefeldioxid/Sulfite', 'Lupinen', 'Weichtiere' ) );
	}
	public static function labels(): array {
		return array( 'energy_kj' => 'Brennwert (kJ)', 'energy_kcal' => 'Brennwert (kcal)', 'fat' => 'Fett', 'saturates' => 'davon gesättigte Fettsäuren', 'carbs' => 'Kohlenhydrate', 'sugars' => 'davon Zucker', 'fibre' => 'Ballaststoffe', 'protein' => 'Eiweiß', 'salt' => 'Salz' );
	}
	public static function defaults(): array {
		return array_merge( array_fill_keys( self::TEXT_FIELDS, '' ), array(
			'enabled' => false, 'basis' => 'g', 'mode' => 'manual', 'nutrients' => array_fill_keys( Nutrition::KEYS, null ),
			'ingredients' => '', 'allergens' => array(), 'portion' => null, 'alcohol' => null,
			'category' => 'excluded', 'fruit' => null, 'sweeteners' => '', 'show_score' => false,
			'recipe' => array(), 'yield' => null, 'sources' => array(), 'same_variations' => false,
			'review_hash' => '', 'reviewed_at' => '', 'reviewed_by' => 0,
		) );
	}
	public static function get( $product ): array {
		$raw = $product->get_meta( self::META, true );
		return array_replace( self::defaults(), is_array( $raw ) ? $raw : array() );
	}
	public static function html( $value ): string {
		return wp_kses( is_scalar( $value ) ? (string) $value : '', array( 'strong' => array(), 'b' => array(), 'em' => array(), 'br' => array() ) );
	}
	public static function source( array $s ): array {
		$provider = $s['provider'] ?? '';
		$id = is_scalar( $s['id'] ?? null ) ? (string) $s['id'] : '';
		if ( ( 'bls' === $provider && preg_match( '/^[A-Z][A-Z0-9]{6}$/D', $id ) ) || ( 'off' === $provider && preg_match( '/^\d{8,14}$/D', $id ) ) ) {
			return array( 'provider' => $provider, 'id' => $id, 'version' => sanitize_text_field( $s['version'] ?? '' ), 'name' => sanitize_text_field( $s['name'] ?? '' ), 'retrieved' => sanitize_text_field( $s['retrieved'] ?? '' ), 'license' => 'bls' === $provider ? 'CC BY 4.0' : 'ODbL-1.0' );
		}
		return array();
	}
	public static function sanitize( array $in ): array {
		$d = self::defaults();
		foreach ( self::TEXT_FIELDS as $key ) {
			$d[ $key ] = sanitize_textarea_field( $in[ $key ] ?? '' );
		}
		foreach ( array( 'enabled', 'show_score', 'same_variations' ) as $key ) {
			$d[ $key ] = ! empty( $in[ $key ] );
		}
		$d['basis'] = 'ml' === ( $in['basis'] ?? '' ) ? 'ml' : 'g';
		$d['mode'] = 'recipe' === ( $in['mode'] ?? '' ) ? 'recipe' : 'manual';
		$d['ingredients'] = self::html( $in['ingredients'] ?? '' );
		$d['allergens'] = array_values( array_intersect( self::ALLERGEN_IDS, (array) ( $in['allergens'] ?? array() ) ) );
		$d['portion'] = Nutrition::number( $in['portion'] ?? null );
		if ( null !== $d['portion'] && $d['portion'] <= 0 ) {
			throw new \InvalidArgumentException( 'Portionsgröße muss größer null sein.' );
		}
		$d['alcohol'] = Nutrition::number( $in['alcohol'] ?? null, 100 );
		$d['yield'] = Nutrition::number( $in['yield'] ?? null );
		$d['fruit'] = Nutrition::number( $in['fruit'] ?? null, 100 );
		$d['category'] = in_array( $in['category'] ?? '', Nutriscore::CATEGORIES, true ) ? $in['category'] : 'excluded';
		$d['sweeteners'] = in_array( $in['sweeteners'] ?? '', array( 'yes', 'no' ), true ) ? $in['sweeteners'] : '';
		if ( count( (array) ( $in['sources'] ?? array() ) ) > 110 || count( (array) ( $in['recipe'] ?? array() ) ) > 100 ) {
			throw new \InvalidArgumentException( 'Zu viele Zutaten oder Quellen.' );
		}
		foreach ( (array) ( $in['sources'] ?? array() ) as $source ) {
			$source = is_array( $source ) ? self::source( $source ) : array();
			if ( $source ) {
				$d['sources'][] = $source;
			}
		}
		foreach ( (array) ( $in['recipe'] ?? array() ) as $row ) {
			if ( ! is_array( $row ) ) {
				throw new \InvalidArgumentException( 'Ungültige Rezeptzeile.' );
			}
			$d['recipe'][] = array(
				'name' => sanitize_text_field( $row['name'] ?? '' ), 'declaration' => self::html( $row['declaration'] ?? '' ),
				'grams' => Nutrition::number( $row['grams'] ?? null ), 'nutrients' => Nutrition::clean( (array) ( $row['nutrients'] ?? array() ) ),
				'source' => is_array( $row['source'] ?? null ) ? self::source( $row['source'] ) : array(),
			);
		}
		if ( 'recipe' === $d['mode'] ) {
			if ( 'g' !== $d['basis'] ) {
				throw new \InvalidArgumentException( 'Rezeptberechnung erfolgt je 100 g. Für eine Deklaration je 100 ml bitte manuell hinterlegen.' );
			}
			$result = Recipe::calculate( $d['recipe'], (float) $d['yield'] );
			$d['nutrients'] = $result['nutrients'];
			// Ingredient proposal is never silently substituted for the verified declaration.
			$d['sources'] = array();
			foreach ( $d['recipe'] as $row ) {
				if ( $row['source'] ) {
					$d['sources'][] = $row['source'];
				}
			}
		} else {
			$d['nutrients'] = Nutrition::clean( (array) ( $in['nutrients'] ?? array() ), $d['basis'] );
		}
		$unique = array();
		foreach ( $d['sources'] as $source ) {
			$unique[ $source['provider'] . ':' . $source['id'] ] = $source;
		}
		$d['sources'] = array_values( $unique );
		return $d;
	}
	public static function fingerprint( array $d ): string {
		unset( $d['review_hash'], $d['reviewed_at'], $d['reviewed_by'] );
		// Empty optional additions must preserve hashes issued before the field existed.
		if ( '' === ( $d['ingredient_notes'] ?? '' ) ) {
			unset( $d['ingredient_notes'] );
		}
		return hash( 'sha256', wp_json_encode( $d, JSON_PRESERVE_ZERO_FRACTION ) );
	}
	public static function approved( array $d ): bool {
		return $d['enabled'] && ! empty( $d['review_hash'] ) && hash_equals( $d['review_hash'], self::fingerprint( $d ) );
	}
	public static function score( array $d ): array {
		if ( isset( $d['alcohol'] ) && $d['alcohol'] > 1.2 ) {
			return array( 'grade' => null, 'reason' => 'Keine Nutri-Score-Berechnung bei mehr als 1,2 % vol Alkohol.' );
		}
		$n = $d['nutrients'];
		foreach ( $n as $key => $value ) {
			if ( null !== $value ) {
				$n[ $key ] = Nutrition::declared( $key, $value );
			}
		}
		return Nutriscore::calculate( $n, $d['category'], $d['fruit'], '' === $d['sweeteners'] ? null : 'yes' === $d['sweeteners'] );
	}
	public static function publication_errors( array $d, bool $variable ): array {
		$errors = array();
		if ( '' === trim( wp_strip_all_tags( $d['ingredients'] ) ) && empty( $d['allergens'] ) ) {
			$errors[] = 'Zutatenliste oder Allergeninformation fehlt.';
		}
		$any = count( array_filter( $d['nutrients'], static fn( $v ) => null !== $v ) ) > 0;
		if ( $any && Nutrition::missing( $d['nutrients'] ) ) {
			$errors[] = 'Nährwerttabelle unvollständig: Alle Big-7-Werte einschließlich kJ und kcal ergänzen oder die Tabelle vollständig leeren.';
		}
		if ( $variable && ! $d['same_variations'] ) {
			$errors[] = 'Bei variablen Produkten muss bestätigt sein, dass alle Varianten identische Lebensmitteldaten und Füllmengen haben.';
		}
		return $errors;
	}
}
