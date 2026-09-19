<?php

/**
 * Regression test for the record validation reported in PR #40.
 *
 * Every limit checked here is taken from SuministroInformacion.xsd, so an
 * invalid record is rejected locally instead of by the AEAT web service.
 */

require_once __DIR__ . '/../lib/newfenix/src/Invoice.php';

use OpenAEAT\Billing\Invoice;

function validInvoice($type = Invoice::TYPE_STANDARD)
{
	$invoice = new Invoice('F-1', '01-01-2026', 'A00000000', 'Issuer');
	$invoice->setType($type)->setDescription('Test')->setAsFirstInChain();
	$invoice->addDesgloseIVA(100, 21);
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
	fwrite(STDERR, "A well formed record must validate\n");
	exit(1);
}

$invoice = validInvoice(Invoice::TYPE_SIMPLIFIED);
$invoice->addRecipient('B00000000', 'Customer');
expectInvalid($invoice, 'Simplified invoice with recipient');

$invoice = validInvoice();
$invoice->setDescription(str_repeat('x', 501));
expectInvalid($invoice, 'Description above 500 characters');

$invoice = validInvoice();
$invoice->setType('XX');
expectInvalid($invoice, 'Unknown invoice type');

$invoice = new Invoice('F-1', '01-01-2026', 'A00000000', str_repeat('x', 121));
$invoice->setType(Invoice::TYPE_STANDARD)->setDescription('Test')->setAsFirstInChain();
$invoice->addDesgloseIVA(100, 21);
$invoice->addRecipient('B00000000', 'Customer');
expectInvalid($invoice, 'Issuer name above 120 characters');

$invoice = new Invoice('F-1', '01-01-2026', 'A00000000', 'Issuer');
$invoice->setType(Invoice::TYPE_STANDARD)->setDescription('Test');
$invoice->addDesgloseIVA(100, 21);
$invoice->addRecipient('B00000000', 'Customer');
expectInvalid($invoice, 'Invoice without chaining');

$invoice = validInvoice();
$invoice->setChainLink('A00000000', 'F-0', '31-12-2025', 'not-a-hash');
expectInvalid($invoice, 'Previous record with a malformed fingerprint');

$invoice = validInvoice();
$invoice->setChainLink('A00000000', 'F-0', '31-12-2025', str_repeat('A', 64));
if (!$invoice->validate()) {
	fwrite(STDERR, "A valid chain link must be accepted\n");
	exit(1);
}

echo "All invoice validation tests passed\n";
