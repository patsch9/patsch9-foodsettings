<?php
namespace P9PS;

defined( 'ABSPATH' ) || exit;

final class Frontend {
	public static function init(): void {
		add_filter( 'woocommerce_product_tabs', static function ( $tabs ) {
			global $product;
			if ( $product && ProductData::approved( ProductData::get( $product ) ) ) {
				$tabs['p9ps_food'] = array( 'title' => __( 'Zutaten & Nährwerte', 'patsch9-foodsettings' ), 'priority' => 25, 'callback' => static function () {
					global $product;
					// All output is escaped inside render().
					echo self::render( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} );
			}
			return $tabs;
		} );
		add_shortcode( 'p9ps_food', static function ( $atts ) {
			$atts = shortcode_atts( array( 'id' => get_the_ID() ), $atts, 'p9ps_food' );
			return self::render( wc_get_product( absint( $atts['id'] ) ) );
		} );
		add_action( 'woocommerce_checkout_create_order_line_item', static function ( $item ) { self::snapshot( $item ); } );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', static function ( $order ) {
			foreach ( $order->get_items() as $item ) {
				self::snapshot( $item );
				$item->save();
			}
		} );
	}
	private static function number( float $value, string $key ): string {
		$value = Nutrition::declared( $key, $value );
		$decimals = str_starts_with( $key, 'energy_' ) ? 0 : ( 'salt' === $key && $value < 1 ? 2 : ( $value < 10 ? 1 : 0 ) );
		return number_format_i18n( $value, $decimals );
	}
	public static function render( $product ): string {
		if ( ! $product || ( 'publish' !== $product->get_status() && ! current_user_can( 'edit_post', $product->get_id() ) ) ) {
			return '';
		}
		if ( post_password_required( $product->get_id() ) && ! current_user_can( 'edit_post', $product->get_id() ) ) {
			return '';
		}
		$d = ProductData::get( $product );
		if ( ! ProductData::approved( $d ) || ProductData::publication_errors( $d, $product->is_type( 'variable' ) ) ) {
			return '';
		}
		$html = '<section class="p9ps-food" aria-label="Lebensmittelinformationen">';
		if ( $d['designation'] ) {
			$html .= '<h3>' . esc_html( $d['designation'] ) . '</h3>';
		}
		if ( $d['ingredients'] ) {
			$html .= '<h4>Zutaten</h4><p>' . ProductData::html( $d['ingredients'] ) . '</p>';
		}
		if ( $d['allergens'] ) {
			$allergens = ProductData::allergens();
			$html .= '<p><strong>Enthält: ' . esc_html( implode( ', ', array_map( static fn( $id ) => $allergens[ $id ], $d['allergens'] ) ) ) . '.</strong></p>';
		}
		if ( $d['traces'] ) {
			$html .= '<p>' . esc_html( $d['traces'] ) . '</p>';
		}
		$n = $d['nutrients'];
		if ( ! Nutrition::missing( $n ) ) {
			$html .= '<table class="shop_attributes p9ps-nutrition"><caption>Durchschnittliche Nährwerte</caption><thead><tr><th scope="col">Nährwert</th><th scope="col">Je 100 ' . esc_html( $d['basis'] ) . '</th>';
			if ( $d['portion'] ) {
				$html .= '<th scope="col">Je Portion (' . esc_html( number_format_i18n( $d['portion'], 1 ) . ' ' . $d['basis'] ) . ')</th>';
			}
			$html .= '</tr></thead><tbody>';
			foreach ( ProductData::labels() as $key => $label ) {
				if ( 'energy_kcal' === $key || null === $n[ $key ] ) {
					continue;
				}
				$html .= '<tr><th scope="row">' . esc_html( 'energy_kj' === $key ? 'Brennwert' : $label ) . '</th>';
				foreach ( $d['portion'] ? array( 1, $d['portion'] / 100 ) : array( 1 ) as $factor ) {
					$value = 'energy_kj' === $key ? self::number( $n['energy_kj'] * $factor, 'energy_kj' ) . ' kJ / ' . self::number( $n['energy_kcal'] * $factor, 'energy_kcal' ) . ' kcal' : self::number( $n[ $key ] * $factor, $key ) . ' g';
					$html .= '<td>' . esc_html( $value ) . '</td>';
				}
				$html .= '</tr>';
			}
			$html .= '</tbody></table>';
		}
		$details = '';
		foreach ( array( 'net_quantity' => 'Nettofüllmenge', 'drained_weight' => 'Abtropfgewicht', 'operator' => 'Lebensmittelunternehmer', 'origin' => 'Herkunft', 'storage' => 'Aufbewahrung', 'instructions' => 'Zubereitung / Verwendung', 'shelf_life' => 'Haltbarkeitshinweis' ) as $key => $label ) {
			if ( $d[ $key ] ) {
				$details .= '<dt><strong>' . esc_html( $label ) . '</strong></dt><dd>' . nl2br( esc_html( $d[ $key ] ) ) . '</dd>';
			}
		}
		if ( null !== $d['alcohol'] ) {
			$details .= '<dt><strong>Alkoholgehalt</strong></dt><dd>' . esc_html( number_format_i18n( $d['alcohol'], 1 ) . ' % vol' ) . '</dd>';
		}
		if ( '' !== $details ) {
			$html .= '<dl class="p9ps-details">' . $details . '</dl>';
		}
		$score = ProductData::score( $d );
		if ( $d['show_score'] && ! empty( $score['grade'] ) && 'yes' === get_option( 'p9ps_nutri_rights', 'no' ) ) {
			$id = absint( get_option( 'p9ps_logo_' . $score['grade'], 0 ) );
			if ( 'attachment' === get_post_type( $id ) && in_array( get_post_mime_type( $id ), array( 'image/png', 'image/jpeg', 'image/webp' ), true ) ) {
				$html .= wp_get_attachment_image( $id, 'medium', false, array( 'alt' => 'Nutri-Score ' . strtoupper( $score['grade'] ), 'class' => 'p9ps-nutriscore', 'style' => 'max-width:240px;height:auto' ) );
			}
		}
		if ( $d['sources'] ) {
			$html .= '<p class="p9ps-sources"><small>Datenquellen (ggf. für die Rezeptur angepasst): ';
			$links = array();
			foreach ( $d['sources'] as $s ) {
				if ( 'bls' === $s['provider'] ) {
					$links[] = '<a href="https://doi.org/10.25826/Data20251217-134202-0">Max Rubner-Institut (2025), BLS 4.0</a>, ' . esc_html( $s['id'] . ' ' . $s['name'] ) . ' (<a href="https://creativecommons.org/licenses/by/4.0/">CC BY 4.0</a>)';
				} elseif ( 'off' === $s['provider'] ) {
					$links[] = '<a href="' . esc_url( 'https://world.openfoodfacts.org/product/' . $s['id'] ) . '">Open Food Facts – ' . esc_html( $s['id'] . ' ' . $s['name'] ) . '</a> (<a href="https://opendatacommons.org/licenses/odbl/1-0/">ODbL</a>)';
				}
			}
			$html .= implode( '; ', $links ) . '.</small></p>';
		}
		return $html . '</section>';
	}
	public static function snapshot( $item ): void {
		if ( $item->get_meta( '_p9ps_food_snapshot', true ) ) {
			return;
		}
		$product = $item->get_product();
		if ( $product && $product->is_type( 'variation' ) ) {
			$product = wc_get_product( $product->get_parent_id() );
		}
		if ( ! $product ) {
			return;
		}
		$d = ProductData::get( $product );
		if ( ProductData::approved( $d ) && ! ProductData::publication_errors( $d, $product->is_type( 'variable' ) ) ) {
			// Store declaration, never private ingredient quantities or recipe snapshots.
			unset( $d['recipe'], $d['yield'], $d['reviewed_by'] );
			$d['recorded_at'] = gmdate( 'c' );
			$item->add_meta_data( '_p9ps_food_snapshot', $d, true );
		}
	}
}
