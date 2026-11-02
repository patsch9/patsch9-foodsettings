<?php
namespace PATSCH9FS;

/** Updated 2023 algorithm. Reference: Santé publique France workbook, 2024-04-04. */
final class Nutriscore {
	public const CATEGORIES = array( 'general', 'cheese', 'red_meat', 'fats', 'beverage', 'water', 'excluded' );
	private static function points( float $value, array $thresholds, bool $inclusive = false ): int {
		return count( array_filter( $thresholds, static fn( $t ) => $inclusive ? $value >= $t : $value > $t ) );
	}

	public static function calculate( array $n, string $category, $fruit, ?bool $sweeteners = null ): array {
		if ( ! in_array( $category, self::CATEGORIES, true ) || 'excluded' === $category ) {
			return array( 'grade' => null, 'reason' => 'Keine geeignete Nutri-Score-Kategorie gewählt.' );
		}
		if ( 'water' === $category ) {
			foreach ( array( 'energy_kj', 'energy_kcal', 'fat', 'saturates', 'carbs', 'sugars', 'fibre', 'protein' ) as $key ) {
				if ( isset( $n[ $key ] ) && ( ! is_numeric( $n[ $key ] ) || (float) $n[ $key ] !== 0.0 ) ) {
					return array( 'grade' => null, 'reason' => 'Reines Wasser ist mit den hinterlegten Nährwerten nicht vereinbar.' );
				}
			}
			return array( 'grade' => 'a', 'score' => null, 'algorithm' => '2023', 'category' => $category );
		}
		$required = array( 'energy_kj', 'saturates', 'sugars', 'salt', 'fibre', 'protein' );
		if ( 'fats' === $category ) {
			$required[] = 'fat';
		}
		foreach ( $required as $key ) {
			if ( ! isset( $n[ $key ] ) || ! is_numeric( $n[ $key ] ) || ! is_finite( (float) $n[ $key ] ) || $n[ $key ] < 0 ) {
				return array( 'grade' => null, 'reason' => 'Für den Nutri-Score fehlen vollständige Nährwerte (einschließlich Ballaststoffen).' );
			}
		}
		if ( null === $fruit || ! is_numeric( $fruit ) || $fruit < 0 || $fruit > 100 || ( 'beverage' === $category && null === $sweeteners ) ) {
			return array( 'grade' => null, 'reason' => 'Obst-/Gemüse-/Hülsenfruchtanteil oder Süßstoffangabe fehlt.' );
		}
		$fruit = round( (float) $fruit, 1 );
		$sugar = array( 3.4, 6.8, 10, 14, 17, 20, 24, 27, 31, 34, 37, 41, 44, 48, 51 );
		$salt = array( .2, .4, .6, .8, 1, 1.2, 1.4, 1.6, 1.8, 2, 2.2, 2.4, 2.6, 2.8, 3, 3.2, 3.4, 3.6, 3.8, 4 );
		$energy = self::points( $n['energy_kj'], array( 335, 670, 1005, 1340, 1675, 2010, 2345, 2680, 3015, 3350 ) );
		$saturates = self::points( $n['saturates'], range( 1, 10 ) );
		$sugars = self::points( $n['sugars'], $sugar );
		$protein = self::points( $n['protein'], array( 2.4, 4.8, 7.2, 9.6, 12, 14, 17 ) );
		$fibre = self::points( $n['fibre'], array( 3, 4.1, 5.2, 6.3, 7.4 ) );
		$fvl = $fruit > 80 ? 5 : ( $fruit > 60 ? 2 : ( $fruit > 40 ? 1 : 0 ) );
		if ( 'red_meat' === $category ) {
			$protein = min( 2, $protein );
		}
		if ( 'fats' === $category ) {
			if ( $n['fat'] <= 0 || $n['saturates'] > $n['fat'] ) {
				return array( 'grade' => null, 'reason' => 'Für die Fett-Kategorie muss Gesamtfett größer null und mindestens so hoch wie gesättigtes Fett sein.' );
			}
			$energy = self::points( $n['saturates'] * 37, range( 120, 1200, 120 ) );
			$saturates = self::points( 100 * $n['saturates'] / $n['fat'], array( 10, 16, 22, 28, 34, 40, 46, 52, 58, 64 ), true );
		}
		if ( 'beverage' === $category ) {
			$energy = self::points( $n['energy_kj'], array( 30, 90, 150, 210, 240, 270, 300, 330, 360, 390 ) );
			$sugars = self::points( $n['sugars'], array( .5, 2, 3.5, 5, 6, 7, 8, 9, 10, 11 ) );
			$protein = self::points( $n['protein'], array( 1.2, 1.5, 1.8, 2.1, 2.4, 2.7, 3 ) );
			$fvl = $fruit > 80 ? 6 : ( $fruit > 60 ? 4 : ( $fruit > 40 ? 2 : 0 ) );
		}
		$negative = $energy + $sugars + $saturates + self::points( $n['salt'], $salt ) + ( 'beverage' === $category && $sweeteners ? 4 : 0 );
		$protein_used = 'beverage' === $category || 'cheese' === $category || $negative < ( 'fats' === $category ? 7 : 11 );
		$score = $negative - $fibre - $fvl - ( $protein_used ? $protein : 0 );
		if ( 'beverage' === $category ) {
			$grade = $score <= 2 ? 'b' : ( $score <= 6 ? 'c' : ( $score <= 9 ? 'd' : 'e' ) );
		} else {
			$grade = $score <= ( 'fats' === $category ? -6 : 0 ) ? 'a' : ( $score <= 2 ? 'b' : ( $score <= 10 ? 'c' : ( $score <= 18 ? 'd' : 'e' ) ) );
		}
		return array( 'grade' => $grade, 'score' => $score, 'negative' => $negative, 'positive' => $negative - $score, 'protein_used' => $protein_used, 'algorithm' => '2023', 'category' => $category );
	}
}
