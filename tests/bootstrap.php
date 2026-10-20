<?php
// Minimal boundary stubs for fast, dependency-free domain tests.
// Real WordPress integration is tested separately; these are not WordPress emulation.
define( 'ABSPATH', __DIR__ . '/' );
define( 'P9PS_PATH', dirname( __DIR__ ) . '/' );
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function sanitize_text_field( $value ) { return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : ''; }
function sanitize_textarea_field( $value ) { return sanitize_text_field( $value ); }
function wp_strip_all_tags( $value ) { return strip_tags( $value ); }
function wp_kses( $value, $allowed ) { return strip_tags( $value, '<strong><b><em><br>' ); }
function remove_accents( $value ) { return strtr( $value, array( 'Ö' => 'O', 'ö' => 'o', 'ä' => 'a', 'ü' => 'u', 'Ä' => 'A', 'Ü' => 'U', 'ß' => 'ss' ) ); }
foreach ( array( 'nutrition', 'nutriscore', 'recipe', 'product-data', 'sources' ) as $module ) {
    require_once P9PS_PATH . 'includes/class-' . $module . '.php';
}
