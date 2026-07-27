<?php

require_once __DIR__ . '/../lib/newfenix/src/Invoice.php';

use OpenAEAT\Billing\Invoice;

function validInvoice($type = Invoice::TYPE_STANDARD)
{
	$invoice = new Invoice('F-1', '01-01-2026', 'A00000000', 'Issuer');
	$invoice->setType($type)->setDescription('Test')->setAsFirstInChain();
	$invoice->addDesglose(Invoice::QUAL_TAXABLE, 100, null, Invoice::TAX_VAT, Invoice::REGIME_GENERAL, 21, 21);
	if ($type !== Invoice::TYPE_SIMPLIFIED && $type !== Invoice::TYPE_CREDIT_NOTE_SIMPLIFIED) {
		$invoice->addRecipient('B00000000', 'Customer');
	}
	return $invoice;
}

function expectInvalid(Invoice $invoice, $label)
{
	try {
		$invoice->validate();
		fwrite(STDERR, $label . " was accepted\n");
		exit(1);
	} catch (InvalidArgumentException $e) {
		return;
	}
}

if (!validInvoice()->validate() || !validInvoice(Invoice::TYPE_SIMPLIFIED)->validate()) {
	exit(1);
}

$invoice = validInvoice(Invoice::TYPE_SIMPLIFIED);
$invoice->addRecipient('B00000000', 'Customer');
expectInvalid($invoice, 'Simplified invoice with recipient');

$invoice = validInvoice();
$invoice->setDescription(str_repeat('x', 501));
expectInvalid($invoice, 'Long description');

$invoice = validInvoice();
$invoice->setType('XX');
expectInvalid($invoice, 'Unknown invoice type');

$invoice = new Invoice('F-1', '01-01-2026', 'A00000000', 'Issuer');
$invoice->setDescription('Test')->addRecipient('B00000000', 'Customer');
$invoice->addDesglose(Invoice::QUAL_TAXABLE, 100, null, Invoice::TAX_VAT, Invoice::REGIME_GENERAL, 21, 21);
expectInvalid($invoice, 'Invoice without chain');

echo "All invoice validation tests passed\n";
