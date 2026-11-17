<?php

/**
 * Regression test for looking up a record filed under another fiscal period.
 *
 * AEAT files a record under the month of its FechaOperacion, not of its issue
 * date. When the operation date falls in another month, or was changed after the
 * record was accepted, a query by issue date returns SinDatos: the duplicate
 * handler cannot reconcile error 3000 and the status check clears every
 * VeriFactu mark of an invoice that is in AEAT.
 */

require_once __DIR__ . '/../lib/functions/functions.compatibility.php';
require_once __DIR__ . '/../lib/functions/functions.query.php';

$total = 0;
$passed = 0;
function check($condition, $label)
{
	global $total, $passed;
	$total++;
	if ($condition) {
		$passed++;
		echo "  PASS: " . $label . "\n";
		return;
	}
	echo "  FAIL: " . $label . "\n";
}

$conf = new stdClass();
$conf->global = new stdClass();
$conf->global->VERIFACTU_USE_OPERATION_DATE = 1;

$facture = new stdClass();
$facture->date = mktime(12, 0, 0, 11, 17, 2026);
$facture->date_pointoftax = mktime(12, 0, 0, 10, 14, 2026);

echo "Alternative query dates\n";

check(
	getVerifactuAlternativeQueryDates($facture) === array('14-10-2026'),
	'by default only the operation date is added'
);

$dates = getVerifactuAlternativeQueryDates($facture, 12);
check(count($dates) === 13, 'with months back it adds one date per previous month');
check($dates[1] === '01-10-2026', 'starting the month before the issue month');
check($dates[12] === '01-11-2025', 'and reaching twelve months back, across the year');

$facture->date = mktime(12, 0, 0, 3, 31, 2026);
$dates = getVerifactuAlternativeQueryDates($facture, 1);
check($dates[1] === '01-02-2026', 'a month-end issue date does not skip February');

echo "Callers\n";

$query = file_get_contents(__DIR__ . '/../lib/functions/functions.query.php');
check(
	preg_match('/function preCheckInvoiceInAEAT(\s|.)*?consultarFacturaEnAEAT\([^;]*getVerifactuAlternativeQueryDates\(\$facture\)\)/', $query) === 1,
	'the pre-check also searches the operation date period'
);
check(
	preg_match('/function verificarEstadoFacturaAEAT(\s|.)*?consultarFacturaEnAEAT\([^;]*getVerifactuAlternativeQueryDates\(\$facture, 12\)\)/', $query) === 1,
	'the status check searches the previous months before clearing the marks'
);
check(
	preg_match('/function manejarFacturaDuplicada(\s|.)*?consultarFacturaEnAEAT\([^;]*getVerifactuAlternativeQueryDates\(\$facture, 12\)\)/', $query) === 1,
	'the duplicate handler searches the previous months'
);

echo "\n" . $passed . "/" . $total . " passed\n";
exit($passed === $total ? 0 : 1);
