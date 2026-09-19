<?php

/**
 * Regression tests for the module dashboard (verifactuindex.php)
 *
 * Covers:
 *  - 2.1.0 / 2.2.0: opening the VeriFactu menu entry ended with a fatal error,
 *               "Call to undefined function dol_get_first_day()". The monthly
 *               chart was rewritten to rely on dol_get_first_day() and
 *               dol_get_last_day() so it also works on PostgreSQL, but those
 *               helpers live in core/lib/date.lib.php, which Dolibarr does not
 *               load for every page. A module page that uses them has to
 *               require the library itself, before the first call.
 *
 * Run with: php tests/DashboardDependenciesTest.php
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

echo "=== Dashboard Dependencies Regression Tests ===\n\n";

// ---------------------------------------------------------------------------
// verifactuindex.php must load date.lib.php before using its helpers
// ---------------------------------------------------------------------------
echo "verifactuindex.php: core/lib/date.lib.php is loaded before it is needed\n";

$indexSource = file_get_contents($moduleRoot . '/verifactuindex.php');

$requirePosition = strpos($indexSource, "/core/lib/date.lib.php'");
$firstCallPosition = strpos($indexSource, 'dol_get_first_day(');

assert_test($firstCallPosition !== false, 'the monthly chart still relies on dol_get_first_day()', $passed, $failed, $total);
assert_test($requirePosition !== false, 'verifactuindex.php requires core/lib/date.lib.php', $passed, $failed, $total);
assert_test(
    $requirePosition !== false && $firstCallPosition !== false && $requirePosition < $firstCallPosition,
    'date.lib.php is required before the first call to dol_get_first_day()',
    $passed,
    $failed,
    $total
);

// ---------------------------------------------------------------------------
// Module-wide: whoever uses a date.lib.php helper must load the library
// ---------------------------------------------------------------------------
echo "\nModule-wide: every file using a date.lib.php helper loads the library\n";

// Helpers defined in core/lib/date.lib.php (Dolibarr 17 to 22). None of them is
// available on a page unless the page, or something it includes, loads the
// library: main.inc.php does not.
$dateLibHelpers = array(
    'dol_get_first_day',
    'dol_get_last_day',
    'dol_get_first_hour',
    'dol_get_last_hour',
    'dol_get_first_day_week',
    'dol_get_prev_day',
    'dol_get_next_day',
    'dol_get_prev_month',
    'dol_get_next_month',
    'dol_get_prev_week',
    'dol_get_next_week',
    'dol_time_plus_duree',
    'dol_stringtotime',
    'num_between_day',
    'num_open_day',
    'num_public_holiday',
    'convertSecondToTime',
    'convertTime2Seconds',
    'convertDurationtoHour',
    'monthArray',
    'getWeekNumber',
);
$helperPattern = '/\b(' . implode('|', array_map('preg_quote', $dateLibHelpers)) . ')\s*\(/';

$skippedDirectories = array('/.git/', '/tests/', '/.github/', '/lib/newfenix/vendor/');

$scannedFiles = 0;
$filesMissingLibrary = array();

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

    $scannedFiles++;
    $source = file_get_contents($file->getPathname());
    if (preg_match($helperPattern, $source) === 1 && strpos($source, 'date.lib.php') === false) {
        $filesMissingLibrary[] = ltrim($relativePath, '/');
    }
}

assert_test($scannedFiles > 0, 'module PHP files were scanned (' . $scannedFiles . ')', $passed, $failed, $total);
assert_test(
    empty($filesMissingLibrary),
    'no module file calls a date.lib.php helper without requiring the library'
        . (empty($filesMissingLibrary) ? '' : ': ' . implode(', ', $filesMissingLibrary)),
    $passed,
    $failed,
    $total
);

echo "\n=== Results ===\n";
echo "Total:  $total\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

exit($failed > 0 ? 1 : 0);
