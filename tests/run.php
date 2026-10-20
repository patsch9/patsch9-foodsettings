<?php
require __DIR__ . '/bootstrap.php';
use P9PS\Nutrition;
use P9PS\Nutriscore;
use P9PS\Recipe;
use P9PS\ProductData;
use P9PS\Sources;

$checks = 0;
function check( bool $condition, string $message ): void {
    global $checks;
    ++$checks;
    if ( ! $condition ) { throw new RuntimeException( $message ); }
}
function throws( callable $fn, string $message ): void {
    try { $fn(); } catch ( Throwable $e ) { check( true, $message ); return; }
    check( false, $message );
}
function near( $actual, $expected, string $message ): void { check( abs( $actual - $expected ) < 0.0000001, $message ); }

check( null === Nutrition::number( '' ), 'Blank must remain unknown.' );
check( 0.0 === Nutrition::number( '0' ), 'Explicit zero must be preserved.' );
near( Nutrition::number( '1,25' ), 1.25, 'German decimal separator' );
near( Nutrition::number( 0.00000001 ), 0.00000001, 'Small numeric values must survive.' );
foreach ( array( '-1', '1.000,50', '<0,5', '1e2', array(), true, INF, NAN ) as $bad ) {
    throws( static fn() => Nutrition::number( $bad ), 'Reject invalid numeric value.' );
}
throws( static fn() => Nutrition::clean( array( 'fat' => 2, 'saturates' => 3 ) ), 'Reject inconsistent fat.' );
throws( static fn() => Nutrition::clean( array( 'carbs' => 2, 'sugars' => 3 ) ), 'Reject inconsistent sugars.' );
near( Nutrition::declared( 'salt', .201 ), .20, 'Salt label precision' );

$fixtures = json_decode( file_get_contents( __DIR__ . '/nutriscore-reference.json' ), true );
foreach ( $fixtures['cases'] as $i => $case ) {
    $out = Nutriscore::calculate( $case['nutrients'], $case['category'], $case['fruit'], $case['sweeteners'] );
    check( $out['grade'] === $case['grade'], 'Official workbook grade case ' . $i );
    check( $out['score'] === $case['score'], 'Official workbook score case ' . $i );
}
$zero = array_fill_keys( Nutrition::KEYS, 0.0 );
check( 'a' === Nutriscore::calculate( $zero, 'general', 0 )['grade'], 'General score 0 = A.' );
check( 'b' === Nutriscore::calculate( $zero, 'beverage', 0, false )['grade'], 'Only water gets beverage A.' );
check( 'c' === Nutriscore::calculate( $zero, 'beverage', 0, true )['grade'], 'Sweetened zero-energy beverage = C.' );
check( 'a' === Nutriscore::calculate( array(), 'water', null )['grade'], 'Pure water special case.' );
check( null === Nutriscore::calculate( array( 'sugars' => 10 ), 'water', null )['grade'], 'Contradictory water classification blocked.' );
check( null === Nutriscore::calculate( $zero, 'general', null )['grade'], 'No inferred FVL percentage.' );
check( null === Nutriscore::calculate( array_diff_key( $zero, array( 'fibre' => 1 ) ), 'general', 0 )['grade'], 'No missing fibre default.' );
check( null === Nutriscore::calculate( $zero, 'beverage', 0 )['grade'], 'No missing sweetener default.' );
check( null === Nutriscore::calculate( $zero, 'excluded', 0 )['grade'], 'Excluded category.' );
check( null === Nutriscore::calculate( $zero, 'fats', 0 )['grade'], 'Zero fat cannot form ratio.' );

$tables = array(
    'energy_kj' => array( 335, 670, 1005, 1340, 1675, 2010, 2345, 2680, 3015, 3350 ),
    'sugars' => array( 3.4, 6.8, 10, 14, 17, 20, 24, 27, 31, 34, 37, 41, 44, 48, 51 ),
    'salt' => array( .2, .4, .6, .8, 1, 1.2, 1.4, 1.6, 1.8, 2, 2.2, 2.4, 2.6, 2.8, 3, 3.2, 3.4, 3.6, 3.8, 4 ),
    'saturates' => range( 1, 10 ),
);
foreach ( $tables as $key => $limits ) {
    foreach ( $limits as $points => $limit ) {
        $n = $zero; $n[$key] = $limit;
        check( Nutriscore::calculate( $n, 'general', 0 )['score'] === $points, "$key equality boundary $limit" );
        $n[$key] += .0001;
        check( Nutriscore::calculate( $n, 'general', 0 )['score'] === $points + 1, "$key strict boundary $limit" );
    }
}
$n = $zero; $n['energy_kj'] = 3500; $n['sugars'] = 10; $n['protein'] = 30;
check( ! Nutriscore::calculate( $n, 'general', 100 )['protein_used'], 'Updated algorithm has no old high-fruit protein exemption.' );
check( Nutriscore::calculate( $n, 'cheese', 0 )['protein_used'], 'Cheese protein exemption.' );
$n = $zero; $n['protein'] = 30;
check( Nutriscore::calculate( $n, 'red_meat', 0 )['score'] === -2, 'Red meat protein cap.' );

$rows = array(
    array( 'name' => 'Zucker', 'grams' => 100, 'nutrients' => array_replace( $zero, array( 'carbs' => 100, 'sugars' => 100, 'energy_kj' => 1700, 'energy_kcal' => 400 ) ) ),
    array( 'name' => 'Wasser', 'grams' => 100, 'nutrients' => $zero ),
);
$r = Recipe::calculate( $rows, 150 );
near( $r['nutrients']['sugars'], 100 / 150 * 100, 'Yield loss increases concentration.' );
near( $r['nutrients']['energy_kj'], 1700 / 150 * 100, 'Energy mass balance.' );
$rows[1]['nutrients']['fibre'] = null;
check( null === Recipe::calculate( $rows, 200 )['nutrients']['fibre'], 'One unknown constituent makes total unknown.' );
throws( static fn() => Recipe::calculate( $rows, 0 ), 'Reject zero yield.' );
throws( static fn() => Recipe::calculate( $rows, 20 ), 'Reject impossible mass concentration.' );
$rows[0]['grams'] = 0;
throws( static fn() => Recipe::calculate( $rows, 200 ), 'Reject zero ingredient mass.' );

$d = ProductData::sanitize( array( 'enabled' => true, 'ingredients' => 'Zucker', 'nutrients' => $zero ) );
check( ! ProductData::approved( $d ), 'No automatic publication.' );
$d['review_hash'] = ProductData::fingerprint( $d );
check( ProductData::approved( $d ), 'Explicit review publishes matching data.' );
$d['ingredients'] .= ', Wasser';
check( ! ProductData::approved( $d ), 'Change invalidates review hash.' );
check( count( ProductData::publication_errors( $d, true ) ) === 1, 'Variable product requires explicit shared composition.' );
$incomplete = $d; $incomplete['nutrients']['salt'] = null;
check( count( ProductData::publication_errors( $incomplete, false ) ) === 1, 'Incomplete table cannot be approved.' );
check( ProductData::sanitize( array( 'alcohol' => 3, 'category' => 'beverage' ) )['category'] === 'excluded', 'Alcohol >1.2 excludes score.' );
check( ProductData::source( array( 'provider' => 'off', 'id' => 'https://evil.test' ) ) === array(), 'Arbitrary source URLs rejected.' );
check( ProductData::source( array( 'provider' => 'bls', 'id' => 'P9B4000' ) )['id'] === 'P9B4000', 'Alphanumeric BLS codes supported.' );

$v3 = array( 'product_name_de' => 'Test', 'nutrition' => array( 'aggregated_set' => array( 'preparation' => 'as_sold', 'per' => '100ml', 'nutrients' => array(
    'fat' => array( 'unit' => 'g', 'value' => 2 ), 'salt' => array( 'unit' => 'mg', 'value' => 20 ),
    'sugars' => array( 'unit' => 'g', 'value' => 1, 'modifier' => '<' ),
) ) ) );
$off = Sources::normalize_off( $v3, '12345678' );
check( $off['basis'] === 'ml', 'V3 per-100ml preserved.' );
near( $off['nutrients']['salt'], .02, 'V3 milligrams normalized to grams.' );
check( null === $off['nutrients']['sugars'], 'Less-than values cannot become exact values.' );
$v3['nutrition']['aggregated_set']['preparation'] = 'prepared';
throws( static fn() => Sources::normalize_off( $v3, '12345678' ), 'Prepared source must not be silently used as sold.' );
$off = Sources::normalize_off( array( 'nutriments' => array( 'sodium_100g' => .1 ) ), '12345678' );
near( $off['nutrients']['salt'], .25, 'Legacy sodium conversion.' );
check( null === $off['nutrients']['energy_kj'], 'Missing energy never fabricated from macros.' );
check( count( Sources::search_bls( 'Öl' ) ) > 0, 'German uppercase accent search.' );
check( Sources::bls( 'P9B4000' )['basis'] === 'g', 'Alphanumeric BLS lookup.' );
throws( static fn() => Sources::bls( '../file' ), 'No path traversal.' );
throws( static fn() => Sources::off( 'https://127.0.0.1' ), 'No arbitrary remote endpoint.' );

$manifest = json_decode( file_get_contents( P9PS_PATH . 'data/bls/manifest.json' ), true );
$index = json_decode( file_get_contents( P9PS_PATH . 'data/bls/index.json' ), true );
check( count( $index ) === 7140 && $manifest['records'] === 7140, 'All BLS records bundled.' );
$nulls = 0;
foreach ( glob( P9PS_PATH . 'data/bls/?.json' ) as $file ) {
    foreach ( json_decode( file_get_contents( $file ), true ) as $id => $record ) {
        check( isset( $index[$id] ), 'BLS index/data consistency.' );
        check( count( $record['nutrients'] ) === 9, 'BLS nine-nutrient schema.' );
        $nulls += count( array_filter( $record['nutrients'], static fn( $v ) => null === $v ) );
    }
}
check( $nulls === $manifest['unknown_nutrient_values'], 'Unknown BLS values preserved.' );
echo "PASS: $checks checks; " . count( $fixtures['cases'] ) . " official Nutri-Score reference cases; 7140 BLS records.\n";
