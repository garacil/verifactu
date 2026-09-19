<?php

/**
 * Regression test for the RechazoPrevio semantics reported in PR #38.
 *
 * SuministroInformacion.xsd (RechazoPrevioType) defines 'S' as "AEAT rejected
 * the record before" and 'X' as "the record never reached AEAT", so the two
 * values must not be used interchangeably.
 */

require_once __DIR__ . '/../lib/newfenix/src/Invoice.php';

use OpenAEAT\Billing\Invoice;

function getPriorRejectionValue(Invoice $invoice)
{
	$property = new ReflectionProperty($invoice, 'payload');
	$property->setAccessible(true);
	$payload = $property->getValue($invoice);
	return $payload['RechazoPrevio'] ?? null;
}

$invoice = new Invoice();
$invoice->setAsPreviousRejection(true);
if (getPriorRejectionValue($invoice) !== 'S') {
	fwrite(STDERR, "Previously rejected records must use S\n");
	exit(1);
}

$invoice->setAsPreviousRejection(null);
if (getPriorRejectionValue($invoice) !== 'X') {
	fwrite(STDERR, "Records never submitted to AEAT must use X\n");
	exit(1);
}

$invoice->setAsPreviousRejection(false);
if (getPriorRejectionValue($invoice) !== null) {
	fwrite(STDERR, "False must remove RechazoPrevio\n");
	exit(1);
}

$subsanacion = Invoice::createSubsanacion('F-1', '01-01-2026', 'A00000000', 'Issuer', null);
if (getPriorRejectionValue($subsanacion) !== 'X') {
	fwrite(STDERR, "createSubsanacion(null) must mark the record as never submitted\n");
	exit(1);
}

$subsanacion = Invoice::createSubsanacion('F-1', '01-01-2026', 'A00000000', 'Issuer', true);
if (getPriorRejectionValue($subsanacion) !== 'S') {
	fwrite(STDERR, "createSubsanacion(true) must mark a previous AEAT rejection\n");
	exit(1);
}

echo "All prior rejection tests passed\n";
