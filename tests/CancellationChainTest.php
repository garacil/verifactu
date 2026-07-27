<?php

require_once __DIR__ . '/../lib/newfenix/src/Cancellation.php';

use OpenAEAT\Billing\Cancellation;

$cancellation = new Cancellation('F-1', '01-01-2026', 'A00000000', 'A00000000', 'Test');

try {
	$cancellation->validate();
	fwrite(STDERR, "Cancellation without a previous record was accepted\n");
	exit(1);
} catch (InvalidArgumentException $e) {
	// Expected.
}

try {
	$cancellation->setAsFirstInChain();
	fwrite(STDERR, "Cancellation was allowed as first record\n");
	exit(1);
} catch (LogicException $e) {
	// Expected.
}

$cancellation->setChainLink('A00000000', 'F-0', '31-12-2025', str_repeat('A', 64));
if (!$cancellation->validate()) {
	fwrite(STDERR, "Cancellation with a previous record was rejected\n");
	exit(1);
}

echo "All cancellation chain tests passed\n";
