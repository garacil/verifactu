<?php

/**
 * Regression test for the AEAT flow control reported in PR #37.
 *
 * AEAT returns TiempoEsperaEnvio on every response (RespuestaSuministro.xsd).
 * The delay is recorded per entity and honoured only by automatic retries: on
 * validation an aborted submission would revert the invoice to draft.
 */

if (!defined('DOL_DOCUMENT_ROOT')) {
	define('DOL_DOCUMENT_ROOT', __DIR__);
}
if (!defined('LOG_WARNING')) {
	define('LOG_WARNING', 4);
}

$storedConstants = array();
$fakeNow = 1000000;

if (!function_exists('dol_now')) {
	function dol_now()
	{
		global $fakeNow;
		return $fakeNow;
	}
}
if (!function_exists('dolibarr_set_const')) {
	function dolibarr_set_const($db, $name, $value, $type = 'chaine', $visible = 0, $note = '', $entity = 1)
	{
		global $storedConstants;
		$storedConstants[$name] = $value;
		return 1;
	}
}
if (!function_exists('getDolGlobalInt')) {
	function getDolGlobalInt($name, $default = 0)
	{
		global $storedConstants;
		return isset($storedConstants[$name]) ? (int) $storedConstants[$name] : $default;
	}
}
if (!function_exists('dol_syslog')) {
	function dol_syslog($message, $level = 0)
	{
	}
}

require_once __DIR__ . '/../lib/functions/functions.response.php';

$conf = (object) array('entity' => 1);
$db = null;

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

check(getAEATWaitTimeRemaining() === 0, 'no delay is pending before any submission');
check(registerAEATWaitTime((object) array('TiempoEsperaEnvio' => 60)) === 60, 'the delay returned by AEAT is recorded');
check(getAEATWaitTimeRemaining() === 60, 'the full delay is pending right after the response');

$fakeNow += 45;
check(getAEATWaitTimeRemaining() === 15, 'the pending delay decreases with time');

$fakeNow += 20;
check(getAEATWaitTimeRemaining() === 0, 'the delay expires and never goes negative');

check(registerAEATWaitTime((object) array('TiempoEsperaEnvio' => 0)) === 0, 'a zero delay is ignored');
check(registerAEATWaitTime(null) === 0, 'a missing response is ignored');
check(registerAEATWaitTime((object) array()) === 0, 'a response without the field is ignored');

$source = file_get_contents(__DIR__ . '/../core/triggers/interface_999_modVerifactu_VerifactuTriggers.class.php');
check(
	strpos($source, "execVERIFACTUCall(\$object, 'Alta', false)") !== false,
	'validation does not enforce the wait time, so an invoice is never reverted for it'
);

$source = file_get_contents(__DIR__ . '/../class/verifactu.utils.php');
check(
	strpos($source, "execVERIFACTUCall(\$facture, 'Alta', true)") !== false,
	'automatic retries do enforce the wait time'
);

echo "\nTotal: " . $total . " | Passed: " . $passed . " | Failed: " . ($total - $passed) . "\n";
exit($total === $passed ? 0 : 1);
