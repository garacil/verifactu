<?php

/**
 * Regression test for the MultiCompany isolation reported in PR #34.
 *
 * getEntity('invoice') expands to every entity an invoice is shared with, so
 * using it on VeriFactu queries lets records from another taxpayer into the
 * fiscal chain, the dashboard and the retry queue. Each query must be bound to
 * the active entity instead.
 */

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

$root = __DIR__ . '/..';

$verifactuQueryFiles = array(
	'lib/functions/functions.hash.php',
	'class/verifactu.utils.php',
	'core/triggers/interface_999_modVerifactu_VerifactuTriggers.class.php',
	'views/list.facture.php',
	'views/query.facture.php',
	'verifactuindex.php',
	'integrity.php',
);

foreach ($verifactuQueryFiles as $file) {
	$source = file_get_contents($root . '/' . $file);
	check(strpos($source, "getEntity('invoice')") === false, $file . ' does not widen the query with getEntity()');
	check(strpos($source, '((int) $conf->entity)') !== false, $file . ' filters by the active entity');
}

// The fiscal chain is the critical one: a previous record from another entity
// would be chained into this taxpayer's fingerprints.
$hash = file_get_contents($root . '/lib/functions/functions.hash.php');
check(
	preg_match('/WHERE f\.entity = " \. \(\(int\) \$conf->entity\)/', $hash) === 1,
	'getLastInvoiceHash() reads the previous record from the active entity only'
);

// The previous form also broke SQL outright once sharing was enabled, because
// getEntity() returns a comma separated list and the query used '='.
check(strpos($hash, 'f.entity = " . getEntity') === false, 'the chain query cannot receive an entity list where a single id is expected');

$integrity = file_get_contents($root . '/integrity.php');
check(strpos($integrity, 'WHERE 1=1') === false, 'the integrity page no longer counts invoices of every entity');

$module = file_get_contents($root . '/core/modules/modVerifactu.class.php');
check(
	strpos($module, 'SELECT rowid FROM " . MAIN_DB_PREFIX . "facture WHERE entity = " . ((int) $conf->entity)') !== false,
	'activation initializes the status extrafield only for the activated entity'
);
check(
	strpos($module, "'entity' => \$conf->entity, 'page' => 'societe/card.php'") !== false,
	'the mandatory country default value is registered for the activated entity'
);

echo "\nTotal: " . $total . " | Passed: " . $passed . " | Failed: " . ($total - $passed) . "\n";
exit($total === $passed ? 0 : 1);
