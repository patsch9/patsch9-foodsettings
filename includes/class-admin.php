<?php
namespace P9PS;

defined( 'ABSPATH' ) || exit;

final class Admin {
	public static function init(): void {
		add_filter( 'woocommerce_product_data_tabs', static function ( $tabs ) {
			$tabs['p9ps_food'] = array( 'label' => __( 'Lebensmittel (P9)', 'patsch9-productsettings' ), 'target' => 'p9ps-food', 'priority' => 75 );
			return $tabs;
		} );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_menu', static function () {
			add_submenu_page( 'woocommerce', 'Lebensmitteldaten', 'Lebensmitteldaten', 'manage_woocommerce', 'p9ps-food-settings', array( __CLASS__, 'settings' ) );
		} );
		add_action( 'admin_post_p9ps_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'wp_ajax_p9ps_food', array( __CLASS__, 'ajax' ) );
	}
	public static function assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || ( 'product' !== $screen->id && 'woocommerce_page_p9ps-food-settings' !== $screen->id ) ) {
			return;
		}
		wp_enqueue_style( 'p9ps-admin', P9PS_URL . 'assets/admin.css', array(), P9PS_VERSION );
		wp_enqueue_script( 'p9ps-admin', P9PS_URL . 'assets/admin.js', array( 'jquery' ), P9PS_VERSION, true );
		wp_localize_script( 'p9ps-admin', 'P9PS', array( 'url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'p9ps_admin' ), 'productId' => get_the_ID(), 'labels' => ProductData::labels(), 'offEnabled' => 'yes' === get_option( 'p9ps_off_enabled', 'no' ) ) );
		if ( 'woocommerce_page_p9ps-food-settings' === $screen->id ) {
			wp_enqueue_media();
		}
	}
	private static function field( array $d, string $key, string $label, string $type = 'text', array $options = array() ): void {
		$id = 'p9ps-' . $key;
		$name = 'p9ps[' . $key . ']';
		echo '<div class="p9ps-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		if ( 'checkbox' === $type ) {
			echo '<input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1" ' . checked( ! empty( $d[ $key ] ), true, false ) . '>';
		} elseif ( 'textarea' === $type ) {
			echo '<textarea rows="3" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( $d[ $key ] ?? '' ) . '</textarea>';
		} elseif ( 'select' === $type ) {
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
			foreach ( $options as $value => $text ) {
				echo '<option value="' . esc_attr( $value ) . '" ' . selected( $d[ $key ] ?? '', $value, false ) . '>' . esc_html( $text ) . '</option>';
			}
			echo '</select>';
		} else {
			echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $d[ $key ] ?? '' ) . '"' . ( 'number' === $type ? ' inputmode="decimal"' : '' ) . '>';
		}
		echo '</div>';
	}
	public static function panel(): void {
		global $product_object;
		if ( ! $product_object ) {
			return;
		}
		$d = ProductData::get( $product_object );
		echo '<div id="p9ps-food" class="panel woocommerce_options_panel hidden"><div class="p9ps-wrap">';
		wp_nonce_field( 'p9ps_save_food', 'p9ps_nonce' );
		echo '<input type="hidden" name="p9ps_present" value="1">';
		echo '<h3>' . esc_html__( 'Lebensmittelinformationen', 'patsch9-productsettings' ) . '</h3>';
		echo '<p>Für den klassischen WooCommerce-Produkteditor. Grundpreise und weitere Shop-Pflichten weiterhin separat konfigurieren.</p>';
		self::field( $d, 'enabled', 'Als Lebensmittel kennzeichnen', 'checkbox' );
		self::field( $d, 'same_variations', 'Alle Varianten haben identische Zutaten, Nährwerte und Füllmengen (nur dann gemeinsame Daten verwenden)', 'checkbox' );
		echo '<details open><summary>Datenquelle und Import</summary><p>BLS 4.0: Max Rubner-Institut (2025), CC BY 4.0. Durchschnittswerte je 100 g; Allergene selbst ergänzen.</p><div class="p9ps-search"><input id="p9ps-search" aria-label="BLS-Suchbegriff" placeholder="z. B. Weizen Mehl oder Butter"><button type="button" class="button" id="p9ps-search-button">BLS durchsuchen</button><select id="p9ps-results" aria-label="BLS-Ergebnisse"></select><button type="button" class="button" id="p9ps-select-bls">Auswählen</button></div>';
		echo '<div class="p9ps-search"><input id="p9ps-barcode" aria-label="Barcode" placeholder="EAN / GTIN" inputmode="numeric"><button type="button" class="button" id="p9ps-select-off">Open Food Facts abrufen</button></div><p>OFF muss unter WooCommerce → Lebensmitteldaten aktiviert sein. Nur bei Klick wird der Barcode an OFF gesendet. Übernommene Daten müssen mit der Packung abgeglichen werden.</p><div id="p9ps-message" role="status" aria-live="polite"></div><div id="p9ps-source-preview"></div></details>';
		echo '<details><summary>Eigene Rezeptur</summary><p>Zutaten als Momentaufnahme je 100 g hinterlegen. Mengen sind essbare Rohmengen in Gramm, Fertiggewicht nach dem Backen/Abkühlen wiegen. Bei ablaufendem Fett, verworfenem Kochwasser oder ähnlichen Verlusten ist diese einfache Massenbilanz ungeeignet.</p>';
		self::field( $d, 'mode', 'Nährwertquelle', 'select', array( 'manual' => 'Manuell / Produktimport', 'recipe' => 'Aus Rezept berechnen' ) );
		self::field( $d, 'yield', 'Fertiges, essbares Gesamtgewicht (g)', 'number' );
		echo '<div id="p9ps-recipe-rows"></div><button type="button" class="button" id="p9ps-add-manual">Manuelle Zutat hinzufügen</button> <button type="button" class="button button-primary" id="p9ps-calculate">Rezept berechnen</button><div id="p9ps-recipe-preview"></div><p>Die Zutatenliste ist ein Vorschlag. Unterzutaten, konkrete Getreide-/Nussarten, QUID-Prozente, Zusatzstoffe und Hervorhebungen selbst kontrollieren. Zutatenmengen werden nicht öffentlich ausgegeben.</p></details>';
		echo '<details open><summary>Zutaten, Allergene und Deklaration</summary>';
		self::field( $d, 'ingredients', 'Zutatenliste (Allergene mit <strong>…</strong> hervorheben)', 'textarea' );
		echo '<fieldset class="p9ps-allergens"><legend>Enthaltene Allergene (zusätzliche Übersicht)</legend>';
		foreach ( ProductData::allergens() as $id => $label ) {
			echo '<label><input type="checkbox" name="p9ps[allergens][]" value="' . esc_attr( $id ) . '" ' . checked( in_array( $id, $d['allergens'], true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</fieldset><p>Die Übersicht ersetzt die Hervorhebung in der Zutatenliste nicht. Konkrete Arten (z. B. Weizen, Haselnüsse) dort ausdrücklich benennen. Spuren sind kein enthaltenes Allergen.</p>';
		foreach ( array( 'designation' => 'Bezeichnung des Lebensmittels', 'net_quantity' => 'Nettofüllmenge (mit Einheit)', 'drained_weight' => 'Abtropfgewicht (mit Einheit)', 'operator' => 'Lebensmittelunternehmer: Name und vollständige Anschrift', 'origin' => 'Ursprungsland / Herkunft', 'storage' => 'Aufbewahrung / Kühlung', 'instructions' => 'Gebrauch / Zubereitung', 'shelf_life' => 'Haltbarkeitshinweis (keine chargenbezogene MHD-Verwaltung)', 'traces' => 'Kann Spuren enthalten von (geprüfter Wortlaut)' ) as $key => $label ) {
			self::field( $d, $key, $label, 'textarea' );
		}
		self::field( $d, 'alcohol', 'Alkoholgehalt (% vol)', 'number' );
		echo '</details><details open><summary>Nährwertdeklaration</summary>';
		self::field( $d, 'basis', 'Bezugsgröße: je 100', 'select', array( 'g' => 'Gramm', 'ml' => 'Milliliter' ) );
		self::field( $d, 'portion', 'Optionale Portionsgröße (g bzw. ml passend zur Bezugsgröße)', 'number' );
		echo '<div class="p9ps-nutrient-grid">';
		foreach ( ProductData::labels() as $key => $label ) {
			echo '<label>' . esc_html( $label . ( str_starts_with( $key, 'energy_' ) ? '' : ' (g)' ) ) . '<input inputmode="decimal" name="p9ps[nutrients][' . esc_attr( $key ) . ']" data-nutrient="' . esc_attr( $key ) . '" value="' . esc_attr( $d['nutrients'][ $key ] ?? '' ) . '"></label>';
		}
		echo '</div><p>Leer = unbekannt, 0 = tatsächlich null. Keine Einheiten oder &lt;-Werte eingeben. Eine angefangene Tabelle muss vor der Freigabe vollständig sein.</p></details><details><summary>Nutri-Score (aktualisierter Algorithmus 2023)</summary>';
		self::field( $d, 'category', 'Geprüfte Produktkategorie', 'select', array( 'excluded' => 'Nicht geeignet / noch nicht bestimmt', 'general' => 'Allgemeine Lebensmittel', 'cheese' => 'Käse', 'red_meat' => 'Rotes Fleisch / entsprechende Erzeugnisse', 'fats' => 'Fette, Öle, Nüsse und Samen', 'beverage' => 'Getränke (auch Milch und Pflanzendrinks)', 'water' => 'Reines Wasser ohne Zusätze' ) );
		self::field( $d, 'fruit', 'Anrechenbarer Obst-/Gemüse-/Hülsenfruchtanteil (%)', 'number' );
		self::field( $d, 'sweeteners', 'Nicht nutritive Süßstoffe bei Getränken', 'select', array( '' => 'Noch nicht geprüft', 'no' => 'Nein', 'yes' => 'Ja' ) );
		self::field( $d, 'show_score', 'Freigegebenes offizielles Nutri-Score-Logo anzeigen', 'checkbox' );
		$score = ProductData::score( $d );
		echo '<p><strong>Berechnung nach gespeichertem Stand: ' . esc_html( empty( $score['grade'] ) ? $score['reason'] : strtoupper( $score['grade'] ) . ' (Punkte: ' . ( $score['score'] ?? 'Wasser' ) . ')' ) . '</strong></p><p>Kein Rückschluss aus Produktnamen. Prozentanteil nach den offiziellen Regeln bestimmen; z. B. zählen Nüsse beim allgemeinen Algorithmus nicht als Obst. Reines Wasser und ausgeschlossene Produktgruppen bewusst einordnen. Die Anzeige setzt passende Nutzungsrechte und offizielle Grafiken in den Einstellungen voraus.</p></details>';
		echo '<input type="hidden" id="p9ps-recipe-json" name="p9ps_recipe_json" value="' . esc_attr( wp_json_encode( $d['recipe'] ) ) . '"><input type="hidden" id="p9ps-sources-json" name="p9ps_sources_json" value="' . esc_attr( wp_json_encode( $d['sources'] ) ) . '">';
		echo '<div class="p9ps-review"><p><strong>Status: ' . esc_html( ProductData::approved( $d ) ? 'Für die Shopanzeige freigegeben.' : 'Noch nicht für die Shopanzeige freigegeben.' ) . '</strong></p><label><input type="checkbox" id="p9ps-approve" name="p9ps_approve" value="1"> Ich habe die endgültigen Angaben, Zutaten, Allergene, Bezugsgröße und Quellen geprüft und gebe diese Version zur Anzeige frei.</label><p>Änderungen entziehen eine bestehende Freigabe. Bei unverändertem Speichern bleibt sie erhalten. Daten ohne Freigabe erscheinen nicht im Shop.</p></div>';
		echo '</div></div>';
	}
	private static function json_input( string $key ): array {
		// Decode structured input before field-by-field validation in ProductData::sanitize(). Nonce is checked by the caller.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw = isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '[]';
		if ( strlen( $raw ) > 300000 ) {
			throw new \InvalidArgumentException( 'Datenumfang zu groß.' );
		}
		$data = json_decode( $raw, true, 32, JSON_THROW_ON_ERROR );
		if ( ! is_array( $data ) || ! array_is_list( $data ) ) {
			throw new \InvalidArgumentException( 'Es wird eine Liste erwartet.' );
		}
		return $data;
	}
	public static function save( $product ): void {
		if ( empty( $_POST['p9ps_present'] ) || ! isset( $_POST['p9ps_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['p9ps_nonce'] ) ), 'p9ps_save_food' ) || ! current_user_can( 'edit_post', $product->get_id() ) ) {
			return;
		}
		try {
			// Structured fields are validated together below; text sanitization here would destroy the recipe/HTML schema.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$in = isset( $_POST['p9ps'] ) && is_array( $_POST['p9ps'] ) ? wp_unslash( $_POST['p9ps'] ) : array();
			$in['recipe'] = self::json_input( 'p9ps_recipe_json' );
			$in['sources'] = self::json_input( 'p9ps_sources_json' );
			$d = ProductData::sanitize( $in );
			$old = ProductData::get( $product );
			$errors = ProductData::publication_errors( $d, $product->is_type( 'variable' ) );
			if ( ! $errors && ( ! empty( $_POST['p9ps_approve'] ) || ( ProductData::approved( $old ) && ProductData::fingerprint( $old ) === ProductData::fingerprint( $d ) ) ) ) {
				$d['review_hash'] = ProductData::fingerprint( $d );
				$d['reviewed_at'] = gmdate( 'c' );
				$d['reviewed_by'] = get_current_user_id();
			} elseif ( $d['enabled'] ) {
				\WC_Admin_Meta_Boxes::add_error( 'P9 Lebensmitteldaten gespeichert, aber nicht freigegeben. ' . implode( ' ', $errors ) );
			}
			$product->update_meta_data( ProductData::META, $d );
		} catch ( \Throwable $e ) {
			\WC_Admin_Meta_Boxes::add_error( 'P9 Lebensmitteldaten nicht geändert: ' . esc_html( $e->getMessage() ) );
		}
	}
	public static function ajax(): void {
		check_ajax_referer( 'p9ps_admin', 'nonce' );
		$product_id = absint( $_POST['product_id'] ?? 0 );
		if ( ! current_user_can( 'edit_products' ) || ! current_user_can( 'edit_post', $product_id ) || 'product' !== get_post_type( $product_id ) ) {
			wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ), 403 );
		}
		try {
			$task = sanitize_key( $_POST['task'] ?? '' );
			if ( 'search' === $task ) {
				$query = sanitize_text_field( wp_unslash( $_POST['query'] ?? '' ) );
				if ( strlen( $query ) < 2 || strlen( $query ) > 80 ) {
					throw new \InvalidArgumentException( 'Suchbegriff muss 2–80 Zeichen lang sein.' );
				}
				$result = Sources::search_bls( $query );
			} elseif ( 'bls' === $task ) {
				$result = Sources::bls( sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) ) );
			} elseif ( 'off' === $task ) {
				$result = Sources::off( sanitize_text_field( wp_unslash( $_POST['barcode'] ?? '' ) ) );
			} elseif ( 'recipe' === $task ) {
				$in = array( 'mode' => 'recipe', 'basis' => 'g', 'yield' => sanitize_text_field( wp_unslash( $_POST['yield'] ?? '' ) ), 'recipe' => self::json_input( 'rows' ) );
				$d = ProductData::sanitize( $in );
				$result = Recipe::calculate( $d['recipe'], (float) $d['yield'] );
			} else {
				throw new \InvalidArgumentException( 'Unbekannte Aktion.' );
			}
		} catch ( \Throwable $e ) {
			wp_send_json_error( array( 'message' => $e->getMessage() ), 400 );
			return;
		}
		wp_send_json_success( $result );
	}
	public static function settings(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		echo '<div class="wrap p9ps-settings"><h1>P9 Lebensmitteldaten</h1><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'p9ps_settings' );
		echo '<input type="hidden" name="action" value="p9ps_settings"><h2>Öffentliche Daten</h2><p>Die lokale BLS-4.0-Suche ist ohne Netzwerkzugriff verfügbar. Quelle: Max Rubner-Institut (2025), <a href="https://doi.org/10.25826/Data20251217-134202-0">BLS 4.0</a>, <a href="https://creativecommons.org/licenses/by/4.0/">CC BY 4.0</a>. Auszug: neun Nährwerte; keine Allergendaten.</p>';
		echo '<label><input name="off_enabled" type="checkbox" value="yes" ' . checked( get_option( 'p9ps_off_enabled', 'no' ), 'yes', false ) . '> Open Food Facts für bewusste Barcodeabfragen aktivieren</label><p>Bei Abfrage werden Barcode, Server-IP und Plugin-Kennung an world.openfoodfacts.org gesendet. Kein Versand von Rezepten, Kunden- oder Bestelldaten. Ergebnisse werden 24 Stunden zwischengespeichert, übernommene Produktdaten dauerhaft lokal gespeichert. OFF-Daten: <a href="https://world.openfoodfacts.org/terms-of-use">ODbL / Nutzungsbedingungen</a>. Namensnennung und bei Datenbankweitergabe ggf. Share-Alike beachten. Die Freigabe prüft die Lizenzpflichten nicht automatisch. Keine Besucheranfragen oder Hintergrundsynchronisation.</p><h2>Nutri-Score</h2><p>Die Berechnung steht intern auch ohne Logo bereit. Für die öffentliche Kennzeichnung zuerst die <a href="https://www.santepubliquefrance.fr/en/nutrition-and-physical-activity/nutri-score">Registrierung und Nutzungsbedingungen</a> prüfen. Offizielle Grafiken aus der Registrierung in der Mediathek auswählen; es werden keine nachgebauten Logos geliefert.</p>';
		echo '<label><input name="nutri_rights" type="checkbox" value="yes" ' . checked( get_option( 'p9ps_nutri_rights', 'no' ), 'yes', false ) . '> Die erforderlichen Nutzungsrechte und offiziellen Grafiken für die angezeigten Marken/Produkte liegen vor.</label>';
		foreach ( range( 'a', 'e' ) as $grade ) {
			$id = absint( get_option( 'p9ps_logo_' . $grade, 0 ) );
			echo '<p><label>Offizielles Logo ' . esc_html( strtoupper( $grade ) ) . ' <input class="p9ps-logo-id" name="logo_' . esc_attr( $grade ) . '" type="number" min="0" value="' . esc_attr( $id ) . '"></label> <button class="button p9ps-pick-logo" type="button">Bild auswählen</button></p>';
		}
		submit_button( 'Einstellungen speichern' );
		echo '</form><h2>Verwendung</h2><p>Produkte → Produkt bearbeiten → Produktdaten → Lebensmittel (P9). Standardmäßig Ausgabe im Tab „Zutaten &amp; Nährwerte“. Für eigene Produktvorlagen und Block-Themes einen Shortcode-Block mit <code>[p9ps_food]</code> oder <code>[p9ps_food id="123"]</code> einsetzen. Angaben mit abweichenden Varianten als separate einfache Produkte pflegen.</p></div>';
	}
	public static function save_settings(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Keine Berechtigung.', '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'p9ps_settings' );
		foreach ( array( 'off_enabled', 'nutri_rights' ) as $key ) {
			update_option( 'p9ps_' . $key, ! empty( $_POST[ $key ] ) ? 'yes' : 'no', false );
		}
		foreach ( range( 'a', 'e' ) as $grade ) {
			$id = absint( $_POST[ 'logo_' . $grade ] ?? 0 );
			if ( ! in_array( get_post_mime_type( $id ), array( 'image/png', 'image/jpeg', 'image/webp' ), true ) ) {
				$id = 0;
			}
			update_option( 'p9ps_logo_' . $grade, $id, false );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=p9ps-food-settings&saved=1' ) );
		exit;
	}
}

