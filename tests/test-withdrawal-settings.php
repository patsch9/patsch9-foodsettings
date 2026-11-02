<?php
/** Focused regressions for the per-product checkout acknowledgement setting. */
require __DIR__ . '/bootstrap.php';

use PATSCH9FS\ProductData;

$checks = 0;
function withdrawal_check( bool $condition, string $message ): void {
    global $checks;
    ++$checks;
    if ( ! $condition ) {
        throw new RuntimeException( $message );
    }
}

$base = ProductData::sanitize( array( 'enabled' => true, 'ingredients' => 'Zucker' ) );
withdrawal_check( false === $base['withdrawal_ack'], 'Checkout acknowledgement defaults to disabled.' );

$enabled = ProductData::sanitize( array( 'enabled' => true, 'ingredients' => 'Zucker', 'withdrawal_ack' => '1' ) );
withdrawal_check( true === $enabled['withdrawal_ack'], 'Explicit checkout acknowledgement setting is retained.' );

$zero = ProductData::sanitize( array( 'enabled' => true, 'ingredients' => 'Zucker', 'withdrawal_ack' => '0' ) );
withdrawal_check( false === $zero['withdrawal_ack'], 'Unchecked checkbox value is not treated as enabled.' );

$hash = ProductData::fingerprint( $base );
$base['review_hash'] = $hash;
withdrawal_check( ProductData::approved( $base ), 'Base food declaration can be approved.' );
$base['withdrawal_ack'] = true;
withdrawal_check( ProductData::approved( $base ), 'Legal checkout setting does not invalidate the reviewed food declaration.' );
$base['withdrawal_ack'] = false;
withdrawal_check( ProductData::approved( $base ), 'Disabling the checkout setting also leaves the reviewed food declaration unchanged.' );

echo "PASS: $checks withdrawal-setting checks.\n";
