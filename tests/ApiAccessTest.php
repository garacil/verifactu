<?php

/**
 * Regression tests for the REST API access rules (class/api_verifactu.class.php)
 *
 * Covers:
 *  - 2.2.2: toProduction and toTest were annotated "@access public". Restler
 *           only authenticates methods whose access level is above public, so
 *           anyone reaching the API could switch the VeriFactu environment of an
 *           entity without an API key. Both methods now require a valid API key
 *           and refuse non-administrators with a 403 raised before the change.
 *           The integrity hash stays public, like integrity.php.
 *
 * Run with: php tests/ApiAccessTest.php
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

/**
 * Returns the docblock and the body of a public method of the API class.
 */
function extract_method(string $source, string $method): array
{
    $pattern = '/(\/\*\*(?:(?!\*\/).)*?\*\/)\s*public function ' . preg_quote($method, '/') . '\s*\(\)\s*\{(.*?)\n\t\}/s';
    if (!preg_match($pattern, $source, $m)) {
        return array('', '');
    }
    return array($m[1], $m[2]);
}

$moduleRoot = dirname(__DIR__);
$source = file_get_contents($moduleRoot . '/class/api_verifactu.class.php');

echo "=== REST API Access Regression Tests ===\n\n";

// ---------------------------------------------------------------------------
// Class-level default
// ---------------------------------------------------------------------------
echo "Class-level access\n";

$classDoc = '';
if (preg_match('/(\/\*\*(?:(?!\*\/).)*?\*\/)\s*class VerifactuApi\b/s', $source, $m)) {
    $classDoc = $m[1];
}
assert_test($classDoc !== '', 'the VerifactuApi class docblock is found', $passed, $failed, $total);
assert_test(
    strpos($classDoc, '@access public') === false,
    'the class does not default every method to public access',
    $passed,
    $failed,
    $total
);

// ---------------------------------------------------------------------------
// Methods that change the environment
// ---------------------------------------------------------------------------
foreach (array('toProduction', 'toTest') as $method) {
    echo "\n$method()\n";
    list($doc, $body) = extract_method($source, $method);

    assert_test($doc !== '' && $body !== '', "$method() is found with its docblock", $passed, $failed, $total);
    assert_test(
        strpos($doc, '@access public') === false,
        "$method() is not public",
        $passed,
        $failed,
        $total
    );
    assert_test(
        strpos($doc, '@access protected') !== false,
        "$method() requires a valid API key (@access protected)",
        $passed,
        $failed,
        $total
    );

    $adminCheck = strpos($body, 'DolibarrApiAccess::$user->admin');
    $refusal = strpos($body, 'RestException(403');
    $change = strpos($body, $method === 'toProduction' ? 'dolibarr_set_const' : 'dolibarr_del_const');
    $tryBlock = strpos($body, 'try {');

    assert_test($adminCheck !== false, "$method() checks that the API user is an administrator", $passed, $failed, $total);
    assert_test($refusal !== false, "$method() refuses other users with HTTP 403", $passed, $failed, $total);
    assert_test(
        $refusal !== false && $change !== false && $refusal < $change,
        "$method() refuses before touching the environment constant",
        $passed,
        $failed,
        $total
    );
    assert_test(
        $refusal !== false && $tryBlock !== false && $refusal < $tryBlock,
        "$method() raises the 403 outside the try block, so it is not rewrapped as a 500",
        $passed,
        $failed,
        $total
    );
}

// ---------------------------------------------------------------------------
// The integrity hash stays public, like integrity.php
// ---------------------------------------------------------------------------
echo "\ngetIntegrity()\n";
list($doc, $body) = extract_method($source, 'getIntegrity');
assert_test($doc !== '', 'getIntegrity() is found with its docblock', $passed, $failed, $total);
assert_test(
    strpos($doc, '@access public') !== false,
    'getIntegrity() stays public, as integrity.php',
    $passed,
    $failed,
    $total
);
assert_test(
    strpos($body, 'dolibarr_set_const') === false && strpos($body, 'dolibarr_del_const') === false,
    'getIntegrity() does not change any configuration',
    $passed,
    $failed,
    $total
);

// ---------------------------------------------------------------------------
// No other public method may write configuration
// ---------------------------------------------------------------------------
echo "\nModule-wide rule\n";
preg_match_all('/(\/\*\*(?:(?!\*\/).)*?\*\/)\s*public function (\w+)\s*\(\)\s*\{(.*?)\n\t\}/s', $source, $all, PREG_SET_ORDER);
$publicWriters = array();
foreach ($all as $entry) {
    $isPublic = strpos($entry[1], '@access public') !== false;
    $writes = preg_match('/dolibarr_(set|del)_const|->query\(\s*["\'](INSERT|UPDATE|DELETE)/i', $entry[3]) === 1;
    if ($isPublic && $writes) {
        $publicWriters[] = $entry[2];
    }
}
assert_test(
    empty($publicWriters),
    'no public API method writes configuration or data'
        . (empty($publicWriters) ? '' : ': ' . implode(', ', $publicWriters)),
    $passed,
    $failed,
    $total
);

echo "\n=== Results ===\n";
echo "Total:  $total\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

exit($failed > 0 ? 1 : 0);
