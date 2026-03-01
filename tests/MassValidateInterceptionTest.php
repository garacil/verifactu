#!/usr/bin/env php
<?php
/**
 * Test: Mass Validate Interception by VeriFactu module
 *
 * Verifies that the doActions hook correctly intercepts the standard
 * Dolibarr mass validation and replaces it with individual transactions.
 *
 * Run: php tests/MassValidateInterceptionTest.php
 */

// ============================================================================
// MOCK FRAMEWORK (simulates Dolibarr environment)
// ============================================================================

// Simulated POST data - controlled per test
$_mock_post = array();

function GETPOST($key, $type = '') {
	global $_mock_post;
	if ($key == 'toselect' && $type == 'array') {
		return isset($_mock_post[$key]) ? $_mock_post[$key] : array();
	}
	return isset($_mock_post[$key]) ? $_mock_post[$key] : '';
}

function getDolGlobalString($key) {
	return '';
}

function isModEnabled($module) {
	return false;
}

function setEventMessages($msg, $errors = null, $type = 'mesgs') {
	global $_test_messages;
	if (!isset($_test_messages)) $_test_messages = array();
	if ($msg !== null) {
		$_test_messages[] = array('msg' => $msg, 'type' => $type);
	}
	if (!empty($errors)) {
		foreach ($errors as $err) {
			$_test_messages[] = array('msg' => $err, 'type' => $type);
		}
	}
}

function dol_syslog($msg, $level = 0) {
	// no-op
}

function dol_include_once($path) {
	// no-op in test
}

// Mock Translate
class Translate {
	public $tab_translate = array();
	public function load($domain) {}
	public function trans($key) {
		$args = func_get_args();
		array_shift($args);
		if (empty($args)) return $key;
		return vsprintf($key . '(' . implode(',', array_fill(0, count($args), '%s')) . ')', $args);
	}
	public function transnoentitiesnoconv($key) { return $key; }
}

// Mock User
class User {
	public $admin = 1;
	public $rights;

	public function __construct() {
		$this->rights = new stdClass();
		$this->rights->facture = new stdClass();
		$this->rights->facture->creer = 1;
		$this->rights->facture->lire = 1;
	}

	public function hasRight($module, $right) {
		return isset($this->rights->$module->$right) ? $this->rights->$module->$right : 0;
	}
}

// Mock Conf
class Conf {
	public $global;
	public function __construct() {
		$this->global = new stdClass();
		$this->global->VERIFACTU_DIRECT_CALL_ON_VALIDATE = 0;
	}
}

// Mock DB - tracks begin/commit/rollback calls
class MockDoliDB {
	public $calls = array();
	public $transaction_depth = 0;

	public function begin() {
		$this->calls[] = 'begin';
		$this->transaction_depth++;
		return 1;
	}

	public function commit() {
		$this->calls[] = 'commit';
		$this->transaction_depth--;
		return 1;
	}

	public function rollback() {
		$this->calls[] = 'rollback';
		$this->transaction_depth--;
		return 1;
	}

	public function resetCalls() {
		$this->calls = array();
		$this->transaction_depth = 0;
	}
}

// Mock Facture - simulates invoice validation
class Facture {
	public $id;
	public $ref;
	public $error = '';
	public $errors = array();
	public $element = 'facture';
	public $statut = 0;
	public $db;

	// Controls validation behavior per invoice ID
	public static $validation_results = array();

	public function __construct($db = null) {
		$this->db = $db;
	}

	public function fetch($id) {
		$this->id = $id;
		$this->ref = 'FA-' . str_pad($id, 4, '0', STR_PAD_LEFT);

		if (isset(self::$validation_results[$id]) && self::$validation_results[$id] === 'fetch_fail') {
			return -1;
		}
		return 1;
	}

	public function validate($user) {
		if (isset(self::$validation_results[$this->id])) {
			$result = self::$validation_results[$this->id];
			if ($result === 'error') {
				$this->error = 'VeriFactu error for ' . $this->ref;
				return -1;
			}
			if ($result === 'already_valid') {
				return 0;
			}
		}
		return 1; // success
	}
}

// Mock HookManager
class HookManager {
	public $error = '';
	public $errors = array();
	public $resArray = array();
	public $resPrint = '';
}

// ============================================================================
// LOAD THE CLASS UNDER TEST
// ============================================================================

// We need to source the ActionsVerifactu class. Since it calls dol_include_once
// at class level, our mock handles that.
require_once __DIR__ . '/../class/actions_verifactu.class.php';

// ============================================================================
// TEST RUNNER
// ============================================================================

$passed = 0;
$failed = 0;
$total = 0;

function assert_equals($expected, $actual, $message) {
	global $passed, $failed, $total;
	$total++;
	if ($expected === $actual) {
		$passed++;
		echo "  PASS: $message\n";
	} else {
		$failed++;
		echo "  FAIL: $message\n";
		echo "    Expected: " . var_export($expected, true) . "\n";
		echo "    Actual:   " . var_export($actual, true) . "\n";
	}
}

function assert_true($actual, $message) {
	assert_equals(true, $actual, $message);
}

function assert_contains($needle, $haystack, $message) {
	global $passed, $failed, $total;
	$total++;
	$found = false;
	if (is_array($haystack)) {
		foreach ($haystack as $item) {
			if (is_array($item) && isset($item['msg']) && strpos($item['msg'], $needle) !== false) {
				$found = true;
				break;
			}
			if (is_string($item) && strpos($item, $needle) !== false) {
				$found = true;
				break;
			}
		}
	}
	if ($found) {
		$passed++;
		echo "  PASS: $message\n";
	} else {
		$failed++;
		echo "  FAIL: $message\n";
		echo "    '$needle' not found in messages\n";
	}
}

function reset_test_state() {
	global $_mock_post, $_test_messages;
	$_mock_post = array();
	$_test_messages = array();
	Facture::$validation_results = array();
}

// ============================================================================
// TESTS
// ============================================================================

$db = new MockDoliDB();
$user = new User();
$langs = new Translate();
$conf = new Conf();

echo "\n=== MassValidateInterceptionTest ===\n\n";

// ---- TEST 1: Non-invoice contexts are not intercepted ----
echo "Test 1: Non-invoice contexts are not intercepted\n";
reset_test_state();
$_mock_post = array('massaction' => 'validate', 'confirmmassaction' => 'yes', 'toselect' => array(1, 2));

$actions = new ActionsVerifactu($db);
$parameters = array('context' => 'invoicecard');
$object = null;
$action = '';
$hookmanager = new HookManager();

$result = $actions->doActions($parameters, $object, $action, $hookmanager);
assert_equals(0, $result, "doActions returns 0 for non-list contexts");

// ---- TEST 2: Non-validate mass actions are not intercepted ----
echo "\nTest 2: Non-validate mass actions pass through\n";
reset_test_state();
$_mock_post = array('massaction' => 'delete', 'confirmmassaction' => 'yes', 'toselect' => array(1, 2));

$actions = new ActionsVerifactu($db);
$parameters = array('context' => 'invoicelistverifactu');
$result = $actions->doActions($parameters, $object, $action, $hookmanager);
assert_equals(0, $result, "doActions returns 0 for non-validate mass actions");

// ---- TEST 3: Unconfirmed validate is not intercepted ----
echo "\nTest 3: Unconfirmed validate is not intercepted\n";
reset_test_state();
$_mock_post = array('massaction' => 'validate', 'toselect' => array(1, 2));
// Note: confirmmassaction is NOT set

$actions = new ActionsVerifactu($db);
$parameters = array('context' => 'invoicelistverifactu');
$result = $actions->doActions($parameters, $object, $action, $hookmanager);
assert_equals(0, $result, "doActions returns 0 without confirmation");

// ---- TEST 4: All invoices validate successfully ----
echo "\nTest 4: All invoices validate successfully (individual transactions)\n";
reset_test_state();
$db->resetCalls();
$_mock_post = array('massaction' => 'validate', 'confirmmassaction' => 'yes', 'toselect' => array(1, 2, 3));
// All invoices succeed (default behavior)

$actions = new ActionsVerifactu($db);
$parameters = array('context' => 'invoicelistverifactu');
$result = $actions->doActions($parameters, $object, $action, $hookmanager);

assert_equals(1, $result, "doActions returns 1 to block standard code");
assert_equals(
	array('begin', 'commit', 'begin', 'commit', 'begin', 'commit'),
	$db->calls,
	"Three individual begin/commit pairs (one per invoice)"
);
assert_equals(0, $db->transaction_depth, "No open transactions remain");
assert_contains('RecordsModified', $_test_messages, "Success message shown with count");

// ---- TEST 5: Invoice #2 fails, others succeed ----
echo "\nTest 5: Invoice #2 fails, invoices #1 and #3 succeed independently\n";
reset_test_state();
$db->resetCalls();
$_mock_post = array('massaction' => 'validate', 'confirmmassaction' => 'yes', 'toselect' => array(1, 2, 3));
Facture::$validation_results = array(2 => 'error'); // Invoice 2 fails

$actions = new ActionsVerifactu($db);
$parameters = array('context' => 'invoicelistverifactu');
$result = $actions->doActions($parameters, $object, $action, $hookmanager);

assert_equals(1, $result, "doActions returns 1 to block standard code");
assert_equals(
	array('begin', 'commit', 'begin', 'rollback', 'begin', 'commit'),
	$db->calls,
	"Invoice 1: begin/commit, Invoice 2: begin/ROLLBACK, Invoice 3: begin/commit"
);
assert_equals(0, $db->transaction_depth, "No open transactions remain");
assert_contains('VERIFACTU_MASS_VALIDATE_PARTIAL', $_test_messages, "Partial success message shown");
assert_contains('VeriFactu error for FA-0002', $_test_messages, "Error detail for invoice 2 shown");

// ---- TEST 6: All invoices fail ----
echo "\nTest 6: All invoices fail\n";
reset_test_state();
$db->resetCalls();
$_mock_post = array('massaction' => 'validate', 'confirmmassaction' => 'yes', 'toselect' => array(1, 2, 3));
Facture::$validation_results = array(1 => 'error', 2 => 'error', 3 => 'error');

$actions = new ActionsVerifactu($db);
$parameters = array('context' => 'invoicelistverifactu');
$result = $actions->doActions($parameters, $object, $action, $hookmanager);

assert_equals(1, $result, "doActions returns 1 to block standard code");
assert_equals(
	array('begin', 'rollback', 'begin', 'rollback', 'begin', 'rollback'),
	$db->calls,
	"All three invoices: begin/rollback"
);

// ---- TEST 7: Fetch failure is handled gracefully ----
echo "\nTest 7: Fetch failure is handled gracefully\n";
reset_test_state();
$db->resetCalls();
$_mock_post = array('massaction' => 'validate', 'confirmmassaction' => 'yes', 'toselect' => array(1, 2));
Facture::$validation_results = array(1 => 'fetch_fail'); // Invoice 1 can't be loaded

$actions = new ActionsVerifactu($db);
$parameters = array('context' => 'invoicelistverifactu');
$result = $actions->doActions($parameters, $object, $action, $hookmanager);

assert_equals(1, $result, "doActions returns 1 to block standard code");
// Invoice 1: fetch fails, no transaction started; Invoice 2: begin/commit
assert_equals(
	array('begin', 'commit'),
	$db->calls,
	"Only invoice 2 gets a transaction (invoice 1 fetch failed before begin)"
);
assert_contains('VERIFACTU_MASS_VALIDATE_FETCH_ERROR', $_test_messages, "Fetch error message shown");

// ---- TEST 8: Works with invoicelist context (standard Dolibarr list) ----
echo "\nTest 8: Works with standard Dolibarr invoicelist context\n";
reset_test_state();
$db->resetCalls();
$_mock_post = array('massaction' => 'validate', 'confirmmassaction' => 'yes', 'toselect' => array(1));

$actions = new ActionsVerifactu($db);
$parameters = array('context' => 'invoicelist');
$result = $actions->doActions($parameters, $object, $action, $hookmanager);

assert_equals(1, $result, "doActions returns 1 for standard invoicelist context");
assert_equals(array('begin', 'commit'), $db->calls, "Individual transaction for the invoice");

// ---- TEST 9: Info message is always shown ----
echo "\nTest 9: VeriFactu info message is shown to user\n";
reset_test_state();
$db->resetCalls();
$_mock_post = array('massaction' => 'validate', 'confirmmassaction' => 'yes', 'toselect' => array(1));

$actions = new ActionsVerifactu($db);
$parameters = array('context' => 'invoicelistverifactu');
$result = $actions->doActions($parameters, $object, $action, $hookmanager);

assert_contains('VERIFACTU_MASS_VALIDATE_INFO', $_test_messages, "Info message about VeriFactu handling is shown");

// ---- TEST 10: Already-validated invoices are handled ----
echo "\nTest 10: Already-validated invoice handled without blocking others\n";
reset_test_state();
$db->resetCalls();
$_mock_post = array('massaction' => 'validate', 'confirmmassaction' => 'yes', 'toselect' => array(1, 2));
Facture::$validation_results = array(1 => 'already_valid'); // Invoice 1 is already validated

$actions = new ActionsVerifactu($db);
$parameters = array('context' => 'invoicelistverifactu');
$result = $actions->doActions($parameters, $object, $action, $hookmanager);

assert_equals(1, $result, "doActions returns 1");
assert_equals(
	array('begin', 'rollback', 'begin', 'commit'),
	$db->calls,
	"Invoice 1: begin/rollback (already valid), Invoice 2: begin/commit"
);
assert_contains('VERIFACTU_MASS_VALIDATE_ALREADY_VALIDATED', $_test_messages, "Already-validated message shown");

// ============================================================================
// RESULTS
// ============================================================================

echo "\n=== RESULTS ===\n";
echo "Total: $total | Passed: $passed | Failed: $failed\n";

if ($failed > 0) {
	echo "\nSOME TESTS FAILED!\n";
	exit(1);
} else {
	echo "\nALL TESTS PASSED\n";
	exit(0);
}
