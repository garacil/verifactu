<?php

/**
 * Regression tests for the exception handler of execVERIFACTUCall()
 *
 * Covers:
 *  - 2.2.2: the catch block of execVERIFACTUCall() cleared verifactu_huella and
 *           verifactu_csv_factura whatever action had failed. A cancellation or
 *           correction ending with an exception (no connection, certificate,
 *           chain lock busy...) erased the fingerprint of a record AEAT had
 *           already accepted: getLastInvoiceHash() stopped seeing it, the next
 *           invoice was chained onto an older record, and the QR disappeared
 *           from an invoice still registered at AEAT. The fingerprint and the
 *           CSV are no longer touched on an exception, and an accepted record
 *           keeps its "sent" status; only the error fields are written.
 *
 * Run with: php tests/ExceptionKeepsRecordTest.php
 */

error_reporting(E_ALL);

$passed = 0;
$failed = 0;
$total = 0;

function assert_test(bool $condition, string $message, &$passed, &$failed, &$total): void
{
    $total++;
    if ($condition) {
        $passed++;
        echo "  PASS: $message\n";
    } else {
        $failed++;
        echo "  FAIL: $message\n";
    }
}

$moduleRoot = dirname(__DIR__);

// functions.response.php only needs these to be loadable outside Dolibarr.
if (!defined('LOG_ERR')) {
    define('LOG_ERR', 3);
}
if (!defined('LOG_WARNING')) {
    define('LOG_WARNING', 4);
}
if (!defined('LOG_DEBUG')) {
    define('LOG_DEBUG', 7);
}
if (!function_exists('dol_syslog')) {
    function dol_syslog($message, $level = LOG_DEBUG)
    {
    }
}
if (!class_exists('Facture')) {
    class Facture
    {
    }
}

require_once $moduleRoot . '/lib/functions/functions.response.php';

echo "=== Exception Handler Regression Tests ===\n\n";

$badge = '<div class="center"><span class="badge badge-status8 classfortooltip badge-status" attr-status="Error">Error</span></div>';
$now = 1789842504;

// ---------------------------------------------------------------------------
// buildVerifactuExceptionErrorData()
// ---------------------------------------------------------------------------
$helperExists = function_exists('buildVerifactuExceptionErrorData');
assert_test($helperExists, 'buildVerifactuExceptionErrorData() exists in functions.response.php', $passed, $failed, $total);
if (!$helperExists) {
    // Keep going with a stand-in that reproduces the old behaviour, so the
    // source checks below still report what is wrong instead of a fatal error.
    function buildVerifactuExceptionErrorData(array $currentOptions, $errorBadge, $errorMessage, $now)
    {
        return array('estado' => $errorBadge, 'error' => 'Exception: ' . $errorMessage, 'ultima_salida' => '', 'fecha_modificacion' => $now, 'csv_factura' => '', 'huella' => '');
    }
}

echo "\nbuildVerifactuExceptionErrorData(): invoice with a record accepted by AEAT\n";

$accepted = array(
    'options_verifactu_estado' => '<div class="center"><span class="badge badge-status4 classfortooltip badge-status" attr-status="Enviada">Enviada</span></div>',
    'options_verifactu_huella' => str_repeat('A', 64),
    'options_verifactu_csv_factura' => 'A-TESTCSV',
);
$data = buildVerifactuExceptionErrorData($accepted, $badge, 'AEAT communication failed: timeout', $now);

assert_test(!array_key_exists('huella', $data), 'the fingerprint is not part of the data written', $passed, $failed, $total);
assert_test(!array_key_exists('csv_factura', $data), 'the CSV is not part of the data written', $passed, $failed, $total);
assert_test(!array_key_exists('estado', $data), 'the "sent" status is kept (no error badge over an accepted record)', $passed, $failed, $total);
assert_test(
    ($data['error'] ?? '') === 'Exception: AEAT communication failed: timeout',
    'the error of the failed operation is still recorded',
    $passed,
    $failed,
    $total
);
assert_test(
    ($data['ultima_salida'] ?? '') === 'VeriFactu Exception: AEAT communication failed: timeout',
    'the last output describes the exception',
    $passed,
    $failed,
    $total
);
assert_test(($data['fecha_modificacion'] ?? null) === $now, 'the modification date is recorded', $passed, $failed, $total);

echo "\nbuildVerifactuExceptionErrorData(): invoice never accepted by AEAT\n";

$never = array(
    'options_verifactu_estado' => '<div class="center"><span class="badge badge-status8 classfortooltip badge-status" attr-status="No Enviada">No Enviada</span></div>',
    'options_verifactu_huella' => '',
    'options_verifactu_csv_factura' => '',
);
$data = buildVerifactuExceptionErrorData($never, $badge, 'Certificate error', $now);

assert_test(($data['estado'] ?? '') === $badge, 'the error status is shown', $passed, $failed, $total);
assert_test(!array_key_exists('huella', $data) && !array_key_exists('csv_factura', $data), 'fingerprint and CSV are still not written', $passed, $failed, $total);

$data = buildVerifactuExceptionErrorData(array(), $badge, 'x', $now);
assert_test(($data['estado'] ?? '') === $badge, 'an invoice without VeriFactu fields gets the error status', $passed, $failed, $total);

// ---------------------------------------------------------------------------
// execVERIFACTUCall(): the catch block uses the helper and clears nothing
// ---------------------------------------------------------------------------
echo "\nexecVERIFACTUCall(): catch block\n";

$source = file_get_contents($moduleRoot . '/lib/functions/functions.submission.php');
$start = strpos($source, 'function execVERIFACTUCall(');
$end = strpos($source, "\nfunction ", $start + 1);
$execBody = substr($source, $start, $end === false ? null : $end - $start);
$catchPos = strrpos($execBody, '} catch (Exception $e) {');
$catch = $catchPos === false ? '' : substr($execBody, $catchPos);

assert_test($catch !== '', 'the exception handler of execVERIFACTUCall() is found', $passed, $failed, $total);
assert_test(
    strpos($catch, 'buildVerifactuExceptionErrorData(') !== false,
    'the handler builds its data with buildVerifactuExceptionErrorData()',
    $passed,
    $failed,
    $total
);
assert_test(
    preg_match("/\\['options_verifactu_huella'\\]\\s*=/", $catch) !== 1,
    'the handler never assigns the fingerprint',
    $passed,
    $failed,
    $total
);
assert_test(
    preg_match("/\\['options_verifactu_csv_factura'\\]\\s*=/", $catch) !== 1,
    'the handler never assigns the CSV',
    $passed,
    $failed,
    $total
);
assert_test(
    preg_match("/'(huella|csv_factura)'\\s*=>/", $catch) !== 1,
    'the handler never passes fingerprint or CSV to saveVerifactuErrorData()',
    $passed,
    $failed,
    $total
);
assert_test(
    strpos($catch, 'fetch_optionals()') !== false
        && strpos($catch, 'fetch_optionals()') < strpos($catch, 'buildVerifactuExceptionErrorData('),
    'the handler reloads the stored fields before deciding',
    $passed,
    $failed,
    $total
);

echo "\n=== Results ===\n";
echo "Total:  $total\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

exit($failed > 0 ? 1 : 0);
