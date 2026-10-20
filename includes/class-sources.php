<?php
namespace P9PS;

defined( 'ABSPATH' ) || exit;

final class Sources {
	public static function search_bls( string $query ): array {
		$index = json_decode( file_get_contents( P9PS_PATH . 'data/bls/index.json' ), true );
		$query = strtolower( remove_accents( $query ) );
		$words = preg_split( '/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY );
		$out = array();
		foreach ( $index as $id => $name ) {
			$haystack = strtolower( remove_accents( $id . ' ' . $name ) );
			if ( ! array_filter( $words, static fn( $word ) => false === strpos( $haystack, $word ) ) ) {
				$out[] = array( 'id' => $id, 'name' => $name );
				if ( count( $out ) >= 30 ) {
					break;
				}
			}
		}
		return $out;
	}

	public static function bls( string $id ): array {
		if ( ! preg_match( '/^[A-Z][A-Z0-9]{6}$/D', $id ) ) {
			throw new \InvalidArgumentException( 'Ungültiger BLS-Code.' );
		}
		$path = P9PS_PATH . 'data/bls/' . $id[0] . '.json';
		$data = is_file( $path ) ? json_decode( file_get_contents( $path ), true ) : array();
		if ( ! isset( $data[ $id ] ) ) {
			throw new \InvalidArgumentException( 'BLS-Eintrag nicht gefunden.' );
		}
		$record = $data[ $id ];
		$warning = 'BLS-Durchschnittswerte. Zustand (roh/gegart) und konkrete Zutat abgleichen. BLS liefert hier keine verlässliche Allergen- oder Zutatenliste; diese selbst ergänzen.';
		foreach ( array( 'saturates' => 'fat', 'sugars' => 'carbs' ) as $child => $parent ) {
			if ( isset( $record['nutrients'][ $child ], $record['nutrients'][ $parent ] ) && $record['nutrients'][ $child ] > $record['nutrients'][ $parent ] + .000001 ) {
				$warning .= ' Dieser Quelldatensatz enthält widersprüchliche Teil-/Gesamtwerte. Vor Verwendung anhand belastbarer Herstellerwerte korrigieren oder andere Quelle wählen; unverändert ist keine Speicherung möglich.';
			}
		}
		return array(
			'name' => $record['name'], 'basis' => 'g', 'nutrients' => $record['nutrients'],
			'declaration' => '', 'allergens' => array(), 'traces' => '',
			'source' => array( 'provider' => 'bls', 'id' => $id, 'version' => '4.0', 'name' => $record['name'], 'license' => 'CC BY 4.0', 'retrieved' => gmdate( 'c' ) ),
			'warning' => $warning,
		);
	}

	public static function off( string $barcode ): array {
		if ( ! preg_match( '/^\d{8,14}$/D', $barcode ) ) {
			throw new \InvalidArgumentException( 'Barcode muss aus 8 bis 14 Ziffern bestehen.' );
		}
		if ( 'yes' !== get_option( 'p9ps_off_enabled', 'no' ) ) {
			throw new \RuntimeException( 'Open Food Facts zuerst unter WooCommerce > Lebensmitteldaten aktivieren.' );
		}
		$cached = get_transient( 'p9ps_off_' . $barcode );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		// Atomic per-site lock spaces requests by 5 seconds (<=12/minute).
		if ( ! add_option( 'p9ps_off_lock', time(), '', false ) ) {
			global $wpdb;
			$acquired = $wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND CAST(option_value AS UNSIGNED) <= %d",
				(string) time(), 'p9ps_off_lock', time() - 5
			) );
			if ( 1 !== $acquired ) {
				throw new \RuntimeException( 'Bitte fünf Sekunden bis zur nächsten Barcodeabfrage warten.' );
			}
			wp_cache_delete( 'p9ps_off_lock', 'options' );
		}
		$fields = 'code,product_name,product_name_de,brands,ingredients_text_de,ingredients_text,ingredients_text_with_allergens_de,allergens_tags,traces_tags,nutrition,nutriments,nutrition_data_per,nutriscore,nutriscore_grade,nutriscore_version,last_modified_t,product_quantity_unit';
		$url = 'https://world.openfoodfacts.org/api/v3.6/product/' . $barcode . '.json?lc=de&fields=' . rawurlencode( $fields );
		$response = wp_safe_remote_get( $url, array(
			'timeout' => 12, 'redirection' => 0, 'limit_response_size' => 512000,
			'user-agent' => 'P9ProductSettings/' . P9PS_VERSION . ' (https://github.com/patsch9/patsch9-productsettings)',
		) );
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Open Food Facts ist momentan nicht erreichbar.' );
		}
		$status = wp_remote_retrieve_response_code( $response );
		if ( 429 === $status || $status >= 500 ) {
			update_option( 'p9ps_off_lock', time() + 60, false );
			throw new \RuntimeException( 'Open Food Facts hat die Abfrage begrenzt oder ist ausgelastet. Bitte später erneut versuchen.' );
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $status || empty( $body['product'] ) || ! is_array( $body['product'] ) ) {
			throw new \RuntimeException( 'Kein passendes Lebensmittel bei Open Food Facts gefunden.' );
		}
		$record = self::normalize_off( $body['product'], $barcode );
		set_transient( 'p9ps_off_' . $barcode, $record, DAY_IN_SECONDS );
		return $record;
	}

	/** Current v3 nutrition schema, with explicit support for the legacy per-100 fields. */
	public static function normalize_off( array $p, string $barcode ): array {
		$map = array( 'energy_kj' => 'energy-kj', 'energy_kcal' => 'energy-kcal', 'fat' => 'fat', 'saturates' => 'saturated-fat', 'carbs' => 'carbohydrates', 'sugars' => 'sugars', 'fibre' => 'fiber', 'protein' => 'proteins', 'salt' => 'salt' );
		$n = array_fill_keys( Nutrition::KEYS, null );
		$set = $p['nutrition']['aggregated_set'] ?? null;
		$basis = 'g';
		if ( is_array( $set ) ) {
			if ( 'as_sold' !== ( $set['preparation'] ?? '' ) || ! in_array( $set['per'] ?? '', array( '100g', '100ml' ), true ) ) {
				throw new \RuntimeException( 'OFF-Daten liegen nicht als verkauftes Produkt je 100 g/ml vor. Bitte Herstellerwerte manuell übernehmen.' );
			}
			$basis = '100ml' === $set['per'] ? 'ml' : 'g';
			foreach ( $map as $key => $off_key ) {
				$entry = $set['nutrients'][ $off_key ] ?? array();
				if ( empty( $entry ) || ! empty( $entry['modifier'] ) ) {
					continue;
				}
				$unit = strtolower( $entry['unit'] ?? '' );
				$expected = 'energy_kj' === $key ? 'kj' : ( 'energy_kcal' === $key ? 'kcal' : 'g' );
				$factor = 'mg' === $unit && 'g' === $expected ? .001 : 1;
				if ( $unit !== $expected && .001 !== $factor ) {
					continue;
				}
				$value = Nutrition::number( $entry['value'] ?? null, 100000 );
				$n[ $key ] = null === $value ? null : $value * $factor;
			}
		} else {
			$legacy = $p['nutriments'] ?? array();
			$basis = '100ml' === ( $p['nutrition_data_per'] ?? '' ) ? 'ml' : 'g';
			foreach ( $map as $key => $off_key ) {
				if ( empty( $legacy[ $off_key . '_modifier' ] ) ) {
					$n[ $key ] = Nutrition::number( $legacy[ $off_key . '_100g' ] ?? null, 10000 );
				}
			}
			if ( null === $n['salt'] && isset( $legacy['sodium_100g'] ) && empty( $legacy['sodium_modifier'] ) ) {
				$n['salt'] = Nutrition::number( $legacy['sodium_100g'] ) * 2.5;
			}
		}
		$n = Nutrition::clean( $n, $basis );
		// Use only safe emphasis markup; external HTML/classes/URLs never enter the editor.
		$declaration = $p['ingredients_text_with_allergens_de'] ?? $p['ingredients_text_de'] ?? $p['ingredients_text'] ?? '';
		$declaration = preg_replace( '/<span\b[^>]*class=["\x27]allergen["\x27][^>]*>(.*?)<\/span>/is', '<strong>$1</strong>', $declaration );
		$declaration = wp_kses( $declaration, array( 'strong' => array(), 'b' => array(), 'em' => array() ) );
		$allergen_names = ProductData::allergens();
		$traces = array();
		foreach ( (array) ( $p['traces_tags'] ?? array() ) as $tag ) {
			if ( is_string( $tag ) ) {
				$traces[] = $allergen_names[ $tag ] ?? sanitize_text_field( $tag );
			}
		}
		return array(
			'name' => sanitize_text_field( $p['product_name_de'] ?? $p['product_name'] ?? $barcode ),
			'brands' => sanitize_text_field( $p['brands'] ?? '' ), 'basis' => $basis, 'nutrients' => $n,
			'declaration' => $declaration, 'allergens' => array_values( array_intersect( ProductData::ALLERGEN_IDS, $p['allergens_tags'] ?? array() ) ),
			'traces' => $traces ? 'Kann Spuren von ' . implode( ', ', $traces ) . ' enthalten.' : '',
			'source' => array( 'provider' => 'off', 'id' => $barcode, 'version' => (string) ( $p['last_modified_t'] ?? '' ), 'name' => sanitize_text_field( $p['product_name_de'] ?? $p['product_name'] ?? $barcode ), 'license' => 'ODbL-1.0', 'retrieved' => gmdate( 'c' ) ),
			'external_grade' => sanitize_text_field( $p['nutriscore']['2023']['grade'] ?? '' ),
			'warning' => 'Community-Daten: Packung, deutsche Zutatenliste, Allergene, Spuren und Bezugsgröße kontrollieren. Fehlende oder Kleiner-als-Angaben werden nicht als null interpretiert. Ein fremder Nutri-Score wird nicht automatisch veröffentlicht.',
		);
	}
}
