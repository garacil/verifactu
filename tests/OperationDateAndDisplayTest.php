<?php

/**
 * Regression test for the improvements requested in issue #51.
 *
 * FechaOperacion is the date the operation was carried out, which the VAT
 * period follows, as opposed to the issue date that numbers the invoice. The
 * schema declares it optional, of type sf:fecha, and right before
 * DescripcionOperacion.
 *
 * The rest covers the display complaints reported in the same issue: the fiscal
 * blocks expanded on the invoice card and every technical column shown in the
 * lists.
 */

require_once __DIR__ . '/../lib/newfenix/src/Invoice.php';

use OpenAEAT\Billing\Invoice;

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

function buildInvoice()
{
	$invoice = new Invoice('FA-1', '05-01-2026', 'B12345678', 'Emisor');
	$invoice->setType(Invoice::TYPE_STANDARD)->setDescription('Test')->setAsFirstInChain();
	$invoice->addDesgloseIVA(1000, 21);
	$invoice->addRecipient('A87654321', 'Cliente');
	return $invoice;
}

echo "FechaOperacion\n";

$invoice = buildInvoice();
check($invoice->getOperationDate() === '', 'an invoice carries no operation date by default');

$invoice->setOperationDate('28-12-2025');
check($invoice->getOperationDate() === '28-12-2025', 'the operation date is stored as given');

$data = $invoice->getData(false);
$keys = array_keys($data);
$position = array_search('FechaOperacion', $keys);
$descriptionPosition = array_search('DescripcionOperacion', $keys);
check($position !== false, 'it reaches the transmitted record');
check(
	$position === $descriptionPosition - 1,
	'and sits right before DescripcionOperacion, the place the schema sequence gives it'
);

// The fingerprint is built from IDEmisorFactura, NumSerieFactura,
// FechaExpedicionFactura, TipoFactura, CuotaTotal, ImporteTotal, the previous
// fingerprint and FechaHoraHusoGenRegistro. The operation date is not part of it.
$withDate = buildInvoice();
$withDate->setOperationDate('28-12-2025');
$withoutDate = buildInvoice();
$timestamp = '2026-01-05T10:00:00+01:00';
foreach (array($withDate, $withoutDate) as $one) {
	$property = new ReflectionProperty($one, 'payload');
	$property->setAccessible(true);
	$payload = $property->getValue($one);
	$payload['FechaHoraHusoGenRegistro'] = $timestamp;
	$property->setValue($one, $payload);
	$one->generateHash();
}
$hashWith = $withDate->getData(false)['Huella'];
$hashWithout = $withoutDate->getData(false)['Huella'];
check($hashWith === $hashWithout, 'informing it does not alter the chained fingerprint');

$invoice = buildInvoice();
$invoice->setOperationDate('28-12-2025')->setOperationDate(null);
check($invoice->getOperationDate() === '', 'passing null removes it');
check(!array_key_exists('FechaOperacion', $invoice->getData(false)), 'and it is no longer transmitted');

$rejected = false;
try {
	buildInvoice()->setOperationDate('2025-12-28');
} catch (InvalidArgumentException $e) {
	$rejected = true;
}
check($rejected, 'a date in another format is rejected, since sf:fecha is dd-mm-yyyy');

$compat = file_get_contents(__DIR__ . '/../lib/functions/functions.compatibility.php');
check(
	strpos($compat, 'date_pointoftax') !== false,
	'the module reads it from date_pointoftax, the Dolibarr field for it'
);
check(
	strpos($compat, "return (\$operationDate === \$issueDate ? '' : \$operationDate);") !== false,
	'and only transmits it when it differs from the issue date'
);

$submission = file_get_contents(__DIR__ . '/../lib/functions/functions.submission.php');
check(strpos($submission, 'setOperationDate($operationDate)') !== false, 'the submission informs it');

echo "\nInvoice card and lists\n";

$module = file_get_contents(__DIR__ . '/../core/modules/modVerifactu.class.php');
check(
	substr_count($module, "array('options' => array('2' => null))") >= 2
		&& strpos($module, "array('options' => array('1' => null))") === false,
	'the fiscal separators are declared collapsed, so they no longer bury the invoice lines'
);
check(
	strpos($module, '$displayValueList = -$displayValue;') !== false,
	'the fiscal columns keep their visibility but negative, available yet unchecked in lists'
);
check(
	substr_count($module, "\n\t\t\t-5,") >= 9,
	'and the technical columns of the VeriFactu block do the same'
);
check(
	strpos($module, 'private function alignExtraFieldDisplay()') !== false,
	'an installation that upgrades gets the same settings, since addExtraField leaves existing fields alone'
);

$actions = file_get_contents(__DIR__ . '/../class/actions_verifactu.class.php');
check(
	strpos($actions, 'VERIFACTU_STATUS_BADGE_ON_REF') !== false,
	'the status badge glued to every invoice number is now governed by a setting'
);
check(
	strpos($actions, "\$onCard = (\$script === 'card.php'") !== false,
	'which by default keeps it on the invoice card and drops it from the lists'
);

echo "\nTotal: " . $total . " | Passed: " . $passed . " | Failed: " . ($total - $passed) . "\n";
exit($total === $passed ? 0 : 1);
