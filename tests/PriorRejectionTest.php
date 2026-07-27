<?php

require_once __DIR__ . '/../lib/newfenix/src/Invoice.php';

use OpenAEAT\Billing\Invoice;

function getPriorRejectionValue(Invoice $invoice)
{
	$property = new ReflectionProperty($invoice, 'payload');
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
	fwrite(STDERR, "Records not previously submitted must use X\n");
	exit(1);
}

$invoice->setAsPreviousRejection(false);
if (getPriorRejectionValue($invoice) !== null) {
	fwrite(STDERR, "False must remove RechazoPrevio\n");
	exit(1);
}

echo "All prior rejection tests passed\n";
