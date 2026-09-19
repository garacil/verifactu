<?php

/**
 * Regression tests for the translation keys of the module
 *
 * Covers:
 *  - 2.2.2: several messages were requested under keys that no language file
 *           defined (VERIFACTU_INVOICE_CAN_NOT_BE_*, VERIFACTU_STATUS_ANULADA,
 *           SOCIETE_HAVE_INVOICES_NOT_*, verifactu_FACTURA_Simplificada...),
 *           so Dolibarr printed the raw key instead of the text; and the mass
 *           validation texts existed only in Spanish and English. Every module
 *           key the code requests must now exist in the five languages.
 *
 * Only module keys are checked (VERIFACTU_*, verifactu_*, SOCIETE_HAVE_*,
 * ERROR_FETCHING_*, THIRD_PARTY_*): the rest belong to the Dolibarr core
 * language files.
 *
 * Run with: php tests/LanguageKeysTest.php
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
$languages = array('es_ES', 'en_US', 'ca_ES', 'eu_ES', 'gl_ES');

// Keys that are not real translations: a prefix completed at runtime and a
// setup item left inside a commented-out template block.
$ignored = array('VERIFACTU_STATUS_', 'VERIFACTU_MYPARAM8');

$skippedDirectories = array('/.git/', '/tests/', '/.github/', '/lib/newfenix/');

echo "=== Language Keys Regression Tests ===\n\n";

// ---------------------------------------------------------------------------
// Collect the module keys requested by the code
// ---------------------------------------------------------------------------
$used = array();
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($moduleRoot, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($moduleRoot)));
    foreach ($skippedDirectories as $skipped) {
        if (strpos($relativePath, $skipped) !== false) {
            continue 2;
        }
    }
    $source = file_get_contents($file->getPathname());

    // $langs->trans('KEY'), transnoentities(...), transnoentitiesnoconv(...)
    if (preg_match_all('/trans(?:noentities(?:noconv)?)?\(\s*[\'"]([A-Za-z0-9_]+)[\'"]/', $source, $m)) {
        foreach ($m[1] as $key) {
            $used[$key][ltrim($relativePath, '/')] = true;
        }
    }
}

// Extrafield labels and help texts declared in the module descriptor are
// translated by Dolibarr from the module language file.
$descriptor = file_get_contents($moduleRoot . '/core/modules/modVerifactu.class.php');
if (preg_match_all('/addExtraField\((.*?)\);/s', $descriptor, $calls)) {
    foreach ($calls[1] as $call) {
        if (preg_match_all('/[\'"](verifactu_[A-Za-z0-9_]+)[\'"]/', $call, $m)) {
            // The first quoted value is the attribute code, not a translation.
            array_shift($m[1]);
            foreach ($m[1] as $key) {
                $used[$key]['core/modules/modVerifactu.class.php (extrafield)'] = true;
            }
        }
    }
}

$moduleKeys = array();
foreach ($used as $key => $files) {
    if (in_array($key, $ignored, true)) {
        continue;
    }
    if (preg_match('/^(VERIFACTU_|verifactu_|SOCIETE_HAVE_|ERROR_FETCHING_|THIRD_PARTY_)/', $key)) {
        $moduleKeys[$key] = array_keys($files);
    }
}
ksort($moduleKeys);

assert_test(count($moduleKeys) > 100, 'module keys requested by the code were collected (' . count($moduleKeys) . ')', $passed, $failed, $total);

// ---------------------------------------------------------------------------
// Every one of them must exist in every language
// ---------------------------------------------------------------------------
foreach ($languages as $lang) {
    echo "\n$lang\n";
    $defined = array();
    foreach (file($moduleRoot . '/langs/' . $lang . '/verifactu.lang') as $line) {
        if (preg_match('/^([A-Za-z0-9_]+)\s*=\s*(.*)$/', rtrim($line, "\r\n"), $m)) {
            $defined[$m[1]] = $m[2];
        }
    }

    $missing = array();
    $empty = array();
    foreach ($moduleKeys as $key => $files) {
        if (!array_key_exists($key, $defined)) {
            $missing[] = $key . ' (' . $files[0] . ')';
        } elseif (trim($defined[$key]) === '') {
            $empty[] = $key;
        }
    }

    assert_test(
        empty($missing),
        'every module key requested by the code is defined'
            . (empty($missing) ? '' : ': ' . implode(', ', $missing)),
        $passed,
        $failed,
        $total
    );
    assert_test(
        empty($empty),
        'no requested key has an empty text' . (empty($empty) ? '' : ': ' . implode(', ', $empty)),
        $passed,
        $failed,
        $total
    );
}

// ---------------------------------------------------------------------------
// The keys fixed in 2.2.2, checked by name so they can never regress
// ---------------------------------------------------------------------------
echo "\nKeys fixed in 2.2.2\n";
$fixed = array(
    'VERIFACTU_INVOICE_CAN_NOT_BE_MODIFIED',
    'VERIFACTU_INVOICE_CAN_NOT_BE_VALID_CIF_REQUIRED',
    'VERIFACTU_INVOICE_CAN_NOT_BE_VALID_TVA_INTRA_REQUIRED',
    'VERIFACTU_INVOICE_CAN_NOT_BE_VALID_ADDRESS_REQUIRED',
    'VERIFACTU_STATUS_ANULADA',
    'VERIFACTU_ERROR_FACTURA_YA_ANULADA',
    'SOCIETE_HAVE_INVOICES_NOT_IDPROF1_MODIFIED',
    'SOCIETE_HAVE_INVOICES_NOT_TVAINTRA_MODIFIED',
    'VERIFACTU_DEFAULT_SOCIETE_IMPUESTO_SET',
    'verifactu_FACTURA_Simplificada',
    'verifactu_SIMPLIFIED_INVOICE_CUSTOMER',
    'VERIFACTU_TIPO_ANULACION',
    'ERROR_FETCHING_INVOICE',
    'VERIFACTU_MASS_VALIDATE_DATE_CONFIRM_MSG',
);
foreach ($fixed as $key) {
    $present = array();
    foreach ($languages as $lang) {
        if (preg_match('/^' . preg_quote($key, '/') . '\s*=\s*\S/m', file_get_contents($moduleRoot . '/langs/' . $lang . '/verifactu.lang'))) {
            $present[] = $lang;
        }
    }
    assert_test(count($present) === count($languages), "$key is translated in the five languages", $passed, $failed, $total);
}

// The cancelled badge text must stay the word the code searches for in Spanish,
// or cancelled invoices would no longer be recognised by the tab and the list.
$esCancelled = '';
if (preg_match('/^VERIFACTU_STATUS_ANULADA\s*=\s*(.*)$/m', file_get_contents($moduleRoot . '/langs/es_ES/verifactu.lang'), $m)) {
    $esCancelled = trim($m[1]);
}
assert_test($esCancelled === 'Anulada', 'the Spanish cancelled status reads "Anulada", the word the code looks for', $passed, $failed, $total);

echo "\n=== Results ===\n";
echo "Total:  $total\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

exit($failed > 0 ? 1 : 0);
