<?php
/** Focused field and review-compatibility regressions; no dataset/network needed. */
require __DIR__ . '/bootstrap.php';
use PATSCH9FS\ProductData;

$checks = 0;
function notes_check( bool $condition, string $message ): void {
    global $checks;
    ++$checks;
    if ( ! $condition ) { throw new RuntimeException( $message ); }
}
$notes = "E102, E110, E129: Kann Aktivität und Aufmerksamkeit bei Kindern beeinträchtigen.\nKakao: 35 % mindestens in der verwendeten Schokolade.\nKakao: 54,5 % mindestens in der verwendeten Schokoladenkuvertüre.\nKakao: 31,7 % mindestens in der verwendeten Milchschokoladenkuvertüre.";
$data = ProductData::sanitize( array( 'enabled' => true, 'ingredients' => 'Zucker' ) );
notes_check( '' === $data['ingredient_notes'], 'New field defaults to empty.' );
$legacy = $data;
unset( $legacy['ingredient_notes'], $legacy['review_hash'], $legacy['reviewed_at'], $legacy['reviewed_by'] );
$legacy_hash = hash( 'sha256', wp_json_encode( $legacy, JSON_PRESERVE_ZERO_FRACTION ) );
$data['review_hash'] = $legacy_hash;
notes_check( ProductData::approved( $data ), 'Existing approval survives an empty new field without recalculating its old hash.' );
$data['ingredient_notes'] = $notes;
notes_check( ! ProductData::approved( $data ), 'New notes revoke the previous approval.' );
$data['review_hash'] = ProductData::fingerprint( $data );
notes_check( ProductData::approved( $data ), 'Notes are included in explicit approval.' );
$data['ingredient_notes'] = str_replace( '35 %', '36 %', $notes );
notes_check( ! ProductData::approved( $data ), 'Changing only notes revokes approval.' );
$data['ingredient_notes'] = '';
notes_check( ! ProductData::approved( $data ), 'Clearing previously approved notes revokes approval too.' );
$clean = ProductData::sanitize( array( 'ingredient_notes' => $notes ) );
notes_check( $notes === $clean['ingredient_notes'], 'All example lines, decimal commas and percentages survive sanitization.' );
$clean = ProductData::sanitize( array( 'ingredient_notes' => '<b onclick="alert(1)">Kakao</b>: 35 %<img src=x onerror=alert(1)>' ) );
notes_check( 'Kakao: 35 %' === $clean['ingredient_notes'], 'Notes are plain text without executable HTML.' );
$clean = ProductData::sanitize( array( 'ingredient_notes' => array( 'invalid' ) ) );
notes_check( '' === $clean['ingredient_notes'], 'Non-scalar note input cannot become markup or text.' );
echo "PASS: $checks ingredient-note checks.\n";
