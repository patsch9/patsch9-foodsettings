<?php
namespace PATSCH9FS;

/** Pure numeric routines; unknown values are null, never an assumed zero. */
final class Nutrition {
	public const KEYS = array( 'energy_kj', 'energy_kcal', 'fat', 'saturates', 'carbs', 'sugars', 'fibre', 'protein', 'salt' );
	public const REQUIRED = array( 'energy_kj', 'energy_kcal', 'fat', 'saturates', 'carbs', 'sugars', 'protein', 'salt' );

	public static function number( $value, float $maximum = 1000000.0 ): ?float {
		if ( null === $value || '' === $value ) {
			return null;
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			if ( ! is_finite( (float) $value ) || $value < 0 || $value > $maximum ) {
				throw new \InvalidArgumentException( 'Zahlenwert außerhalb des zulässigen Bereichs.' );
			}
			return (float) $value;
		}
		if ( ! is_scalar( $value ) || is_bool( $value ) ) {
			throw new \InvalidArgumentException( 'Ungültiger Zahlenwert.' );
		}
		$value = str_replace( ',', '.', trim( (string) $value ) );
		if ( ! preg_match( '/^\d+(?:\.\d+)?$/D', $value ) ) {
			throw new \InvalidArgumentException( 'Zahlen bitte ohne Einheiten, Tausendertrennzeichen oder Kleiner-als-Zeichen eingeben.' );
		}
		$number = (float) $value;
		if ( ! is_finite( $number ) || $number > $maximum ) {
			throw new \InvalidArgumentException( 'Zahlenwert außerhalb des zulässigen Bereichs.' );
		}
		return $number;
	}

	public static function clean( array $values, string $basis = 'g' ): array {
		$out = array();
		foreach ( self::KEYS as $key ) {
			$max = str_starts_with( $key, 'energy_' ) ? 10000.0 : ( 'ml' === $basis ? 250.0 : 100.0 );
			$out[ $key ] = self::number( $values[ $key ] ?? null, $max );
		}
		foreach ( array( 'saturates' => 'fat', 'sugars' => 'carbs' ) as $child => $parent ) {
			if ( null !== $out[ $child ] && null !== $out[ $parent ] && $out[ $child ] > $out[ $parent ] + 0.000001 ) {
				throw new \InvalidArgumentException( 'Gesättigte Fettsäuren dürfen Fett und Zucker darf Kohlenhydrate nicht überschreiten.' );
			}
		}
		return $out;
	}

	public static function missing( array $values ): array {
		return array_values( array_filter( self::REQUIRED, static fn( $key ) => ! isset( $values[ $key ] ) ) );
	}

	/** Numeric precision used for the declaration; do not round during recipe sums. */
	public static function declared( string $key, float $value ): float {
		if ( str_starts_with( $key, 'energy_' ) ) {
			return round( $value );
		}
		if ( 'salt' === $key ) {
			return round( $value, $value >= 1 ? 1 : 2 );
		}
		return round( $value, $value >= 10 ? 0 : 1 );
	}
}
