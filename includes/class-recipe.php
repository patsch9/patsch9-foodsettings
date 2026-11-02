<?php
namespace P9PS;

final class Recipe {
	/** Each ingredient snapshot is per 100 g; final yield is the edible finished mass. */
	public static function calculate( array $rows, float $yield ): array {
		if ( $yield <= 0 || ! is_finite( $yield ) || count( $rows ) < 1 || count( $rows ) > 100 ) {
			throw new \InvalidArgumentException( 'Rezept benötigt 1–100 Zutaten und ein positives Fertiggewicht.' );
		}
		$sums = array_fill_keys( Nutrition::KEYS, 0.0 );
		$unknown = array_fill_keys( Nutrition::KEYS, false );
		$total = 0;
		foreach ( $rows as $row ) {
			$amount = Nutrition::number( $row['grams'] ?? null );
			if ( null === $amount || $amount <= 0 ) {
				throw new \InvalidArgumentException( 'Jede Zutat benötigt eine positive Menge in Gramm.' );
			}
			$total += $amount;
			$nutrients = Nutrition::clean( $row['nutrients'] ?? array() );
			foreach ( $sums as $key => $sum ) {
				if ( null === $nutrients[ $key ] ) {
					$unknown[ $key ] = true;
				} else {
					$sums[ $key ] += $amount / 100 * $nutrients[ $key ];
				}
			}
		}
		foreach ( $sums as $key => $sum ) {
			$sums[ $key ] = $unknown[ $key ] ? null : $sum / $yield * 100;
		}
		// Catch impossible concentrations rather than silently publishing them.
		$sums = Nutrition::clean( $sums );
		usort( $rows, static fn( $a, $b ) => (float) $b['grams'] <=> (float) $a['grams'] );
		$ingredients = array_map( static function ( $row ) {
			$name = $row['name'];
			$declaration = $row['declaration'] ?? '';
			return '' !== $declaration ? $name . ' (' . $declaration . ')' : $name;
		}, $rows );
		return array( 'nutrients' => $sums, 'ingredients' => implode( ', ', $ingredients ), 'input_grams' => $total, 'yield_grams' => $yield );
	}
}
