#!/usr/bin/env php
<?php
/**
 * Test: Mass Validate Interception by VeriFactu module
 *
 * Verifies that the doActions hook correctly intercepts the standard
 * Dolibarr mass validation and replaces it with individual transactions,
 * including chronological date ordering and date adjustment.
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

function dol_mktime($hour, $min, $sec, $month, $day, $year) {
	return mktime($hour, $min, $sec, $month, $day, $year);
}

function dol_now() {
	return time();
}

// Mock: last VeriFactu invoice hash - controlled per test
$_mock_last_invoice_hash = null;

function getLastInvoiceHash() {
	global $_mock_last_invoice_hash;
	return $_mock_last_invoice_hash;
}

function getEnvironment() {
	return 'test';
}

// Mock Translate
class Translate {
	public $tab_translate = array();
	public function load($domain) {}
	public function trans($key) {
		$args = func_get_args();
		array_shift($args);
		if (empty($args)) return $key;
		return $key . '(' . implode(',', $args) . ')';
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

// Mock DB - tracks begin/commit/rollback calls and idate calls
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

	public function idate($timestamp) {
		return date('Y-m-d', $timestamp);
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
	public $date;
	public $error = '';
	public $errors = array();
	public $element = 'facture';
	public $statut = 0;
	public $db;

	// Controls validation behavior per invoice ID
	public static $validation_results = array();
	// Controls dates per invoice ID (timestamp)
	public static $invoice_dates = array();
	// Tracks date adjustments for assertions
	public static $date_adjustments = array();

	public function __construct($db = null) {
		$this->db = $db;
	}

	public function fetch($id) {
		$this->id = $id;
		$this->ref = 'FA-' . str_pad($id, 4, '0', STR_PAD_LEFT);

		// Set date from mock configuration
		if (isset(self::$invoice_dates[$id])) {
			$this->date = self::$invoice_dates[$id];
		} else {
			$this->date = mktime(0, 0, 0, 3, 1, 2026); // Default: March 1, 2026
		}

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

	public function setValueFrom($field, $value) {
		if ($field === 'datef') {
			self::$date_adjustments[$this->id] = $value;
		}
		return 1;
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

function assert_not_contains($needle, $haystack, $message) {
	global $passed, $failed, $total;
	$total++;
	$found = false;
	if (is_array($haystack)) {
		foreach ($haystack as $item) {
			if (is_array($item) && isset($item['msg']) && strpos($item['msg'], $needle) !== false) {
				$found = true;
				break;
			}
		}
	}
	if (!$found) {
		$passed++;
		echo "  PASS: $message\n";
	} else {
		$failed++;
		echo "  FAIL: $message\n";
		echo "    '$needle' was found but should not be\n";
	}
}

function reset_test_state() {
	global $_mock_post, $_test_messages, $_mock_last_invoice_hash;
	$_mock_post = array();
	$_test_messages = array();
	$_mock_last_invoice_hash = null;
	Facture::$validation_results = array();
	Facture::$invoice_dates = array();
	Facture::$date_adjustments = array();
}

// ============================================================================
// TESTS - PART 1: Basic interception (original tests)
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

$actions = new ActionsVerifactu($db);
$parameters = array('context' => 'invoicelistverifactu');
$result = $actions->doActions($parameters, $object, $action, $hookmanager);
assert_equals(0, $result, "doActions returns 0 without confirmation");

// ---- TEST 4: All invoices validate successfully ----
echo "\nTest 4: All invoices validate successfully (individual transactions)\n";
reset_test_state();
$db->resetCalls();
$_mock_post = array('massaction' => 'validate', 'confirmmassaction' => 'yes', 'toselect' => array(1, 2, 3));

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
Facture::$validation_results = array(2 => 'error');

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
Facture::$validation_results = array(1 => 'fetch_fail');

$actions = new ActionsVerifactu($db);
$parameters = array('context' => 'invoicelistverifactu');
$result = $actions->doActions($parameters, $object, $action, $hookmanager);

assert_equals(1, $result, "doActions returns 1 to block standard code");
assert_equals(
	array('begin', 'commit'),
	$db->calls,
	"Only invoice 2 gets a transaction (invoice 1 fetch failed before begin)"
);
assert_contains('VERIFACTU_MASS_VALIDATE_FETCH_ERROR', $_test_messages, "Fetch error message shown");

// ---- TEST 8: Works with invoicelist context ----
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
Facture::$validation_results = array(1 => 'already_valid');

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
// TESTS - PART 2: Date conflict detection and adjustment
// ============================================================================

echo "\n\n=== Date Conflict Detection Tests ===\n\n";

// ---- TEST 11: Date conflict detected → confirmation dialog triggered ----
echo "Test 11: Date conflict detected - shows confirmation dialog\n";
reset_test_state();
$db->resetCalls();
// Last validated invoice was March 2, 2026
$_mock_last_invoice_hash = array(
	'hash' => 'abc123',
	'numero' => 'FA-0099',
	'number' => 'FA-0099',
	'fecha' => '02-03-2026',
	'date' => '02-03-2026'
);
// Invoice has date March 1 (before last validated)
Facture::$invoice_dates = array(1 => mktime(0, 0, 0, 3, 1, 2026));
$_mock_post = array('massaction' => 'validate', 'confirmmassaction' => 'yes', 'toselect' => array(1));

$actions = new ActionsVerifactu($db);
$parameters = array('context' => 'invoicelistverifactu');
$action = '';
$hookmanager = new HookManager();
$result = $actions->doActions($parameters, $object, $action, $hookmanager);

assert_equals(1, $result, "doActions returns 1 to block standard code");
assert_equals('verifactu_confirm_date_adjust', $action, "Action set to trigger date confirmation dialog");
assert_true(!empty($actions->results['verifactu_date_conflict']), "Conflict flag set in results");
assert_equals(1, $actions->results['verifactu_date_conflict_count'], "1 invoice needs adjustment");
assert_equals(array(), $db->calls, "No transactions started (waiting for confirmation)");

// ---- TEST 12: Date conflict with confirmation → dates adjusted and processed ----
echo "\nTest 12: Date conflict confirmed - adjusts dates and processes\n";
reset_test_state();
$db->resetCalls();
$_mock_last_invoice_hash = array(
	'hash' => 'abc123',
	'numero' => 'FA-0099',
	'number' => 'FA-0099',
	'fecha' => '02-03-2026',
	'date' => '02-03-2026'
);
// Invoice 1: March 1 (needs adjustment), Invoice 2: March 3 (no adjustment)
Facture::$invoice_dates = array(
	1 => mktime(0, 0, 0, 3, 1, 2026),
	2 => mktime(0, 0, 0, 3, 3, 2026)
);
$_mock_post = array(
	'massaction' => 'validate',
	'confirmmassaction' => 'yes',
	'toselect' => array(1, 2),
	'verifactu_date_confirm' => 'yes'  // User confirmed date adjustment
);

$actions = new ActionsVerifactu($db);
$parameters = array('context' => 'invoicelistverifactu');
$action = '';
$hookmanager = new HookManager();
$result = $actions->doActions($parameters, $object, $action, $hookmanager);

assert_equals(1, $result, "doActions returns 1");
assert_equals(
	array('begin', 'commit', 'begin', 'commit'),
	$db->calls,
	"Both invoices processed with individual transactions"
);
// Invoice 1 (March 1) should be adjusted to March 2
assert_true(isset(Facture::$date_adjustments[1]), "Invoice 1 date was adjusted");
assert_equals('2026-03-02', Facture::$date_adjustments[1], "Invoice 1 adjusted to March 2 (last validated date)");
// Invoice 2 (March 3) should NOT be adjusted
assert_true(!isset(Facture::$date_adjustments[2]), "Invoice 2 date was NOT adjusted (already >= last validated)");
assert_contains('VERIFACTU_MASS_VALIDATE_DATES_ADJUSTED', $_test_messages, "Date adjustment message shown");

// ---- TEST 13: No date conflict → processes directly without asking ----
echo "\nTest 13: No date conflict - processes directly\n";
reset_test_state();
$db->resetCalls();
$_mock_last_invoice_hash = array(
	'hash' => 'abc123',
	'numero' => 'FA-0099',
	'number' => 'FA-0099',
	'fecha' => '01-03-2026',
	'date' => '01-03-2026'
);
// Both invoices have dates >= last validated
Facture::$invoice_dates = array(
	1 => mktime(0, 0, 0, 3, 2, 2026),
	2 => mktime(0, 0, 0, 3, 3, 2026)
);
$_mock_post = array('massaction' => 'validate', 'confirmmassaction' => 'yes', 'toselect' => array(1, 2));

$actions = new ActionsVerifactu($db);
$parameters = array('context' => 'invoicelistverifactu');
$action = '';
$hookmanager = new HookManager();
$result = $actions->doActions($parameters, $object, $action, $hookmanager);

assert_equals(1, $result, "doActions returns 1");
assert_equals(
	array('begin', 'commit', 'begin', 'commit'),
	$db->calls,
	"Both invoices processed directly"
);
assert_not_contains('verifactu_confirm_date_adjust', $action, "No confirmation dialog triggered");
assert_not_contains('VERIFACTU_MASS_VALIDATE_DATES_ADJUSTED', $_test_messages, "No date adjustment message");

// ---- TEST 14: Invoices sorted by date ASC before processing ----
echo "\nTest 14: Invoices sorted by date ASC (menor a mayor)\n";
reset_test_state();
$db->resetCalls();
// Invoices passed in reverse order but should be processed in date order
Facture::$invoice_dates = array(
	1 => mktime(0, 0, 0, 3, 5, 2026),  // March 5
	2 => mktime(0, 0, 0, 3, 1, 2026),  // March 1 (earliest)
	3 => mktime(0, 0, 0, 3, 3, 2026),  // March 3
);
// Make invoice 2 fail so we can verify processing order
Facture::$validation_results = array(2 => 'error');
$_mock_post = array('massaction' => 'validate', 'confirmmassaction' => 'yes', 'toselect' => array(1, 2, 3));

$actions = new ActionsVerifactu($db);
$parameters = array('context' => 'invoicelistverifactu');
$action = '';
$hookmanager = new HookManager();
$result = $actions->doActions($parameters, $object, $action, $hookmanager);

assert_equals(1, $result, "doActions returns 1");
// Sorted order: Invoice 2 (Mar 1) → Invoice 3 (Mar 3) → Invoice 1 (Mar 5)
// Invoice 2 fails (rollback), Invoice 3 succeeds, Invoice 1 succeeds
assert_equals(
	array('begin', 'rollback', 'begin', 'commit', 'begin', 'commit'),
	$db->calls,
	"Processed in date order: Inv2(Mar1 fail) → Inv3(Mar3 ok) → Inv1(Mar5 ok)"
);

// ---- TEST 15: Rolling minimum date - later invoices adjusted to previous ----
echo "\nTest 15: Rolling minimum date advances as invoices are processed\n";
reset_test_state();
$db->resetCalls();
$_mock_last_invoice_hash = array(
	'hash' => 'abc123',
	'numero' => 'FA-0099',
	'number' => 'FA-0099',
	'fecha' => '05-03-2026',
	'date' => '05-03-2026'
);
// All three invoices have dates before last validated (March 5)
Facture::$invoice_dates = array(
	1 => mktime(0, 0, 0, 3, 1, 2026),  // March 1
	2 => mktime(0, 0, 0, 3, 2, 2026),  // March 2
	3 => mktime(0, 0, 0, 3, 3, 2026),  // March 3
);
$_mock_post = array(
	'massaction' => 'validate',
	'confirmmassaction' => 'yes',
	'toselect' => array(1, 2, 3),
	'verifactu_date_confirm' => 'yes'
);

$actions = new ActionsVerifactu($db);
$parameters = array('context' => 'invoicelistverifactu');
$action = '';
$hookmanager = new HookManager();
$result = $actions->doActions($parameters, $object, $action, $hookmanager);

assert_equals(1, $result, "doActions returns 1");
// All 3 should be adjusted to March 5 (the last validated date)
assert_equals('2026-03-05', Facture::$date_adjustments[1], "Invoice 1 (Mar1) adjusted to Mar5");
assert_equals('2026-03-05', Facture::$date_adjustments[2], "Invoice 2 (Mar2) adjusted to Mar5");
assert_equals('2026-03-05', Facture::$date_adjustments[3], "Invoice 3 (Mar3) adjusted to Mar5");

// ---- TEST 16: No previous VeriFactu invoices → no conflict ----
echo "\nTest 16: No previous VeriFactu invoices - no conflict possible\n";
reset_test_state();
$db->resetCalls();
$_mock_last_invoice_hash = null; // No previous invoices
Facture::$invoice_dates = array(1 => mktime(0, 0, 0, 1, 1, 2025));
$_mock_post = array('massaction' => 'validate', 'confirmmassaction' => 'yes', 'toselect' => array(1));

$actions = new ActionsVerifactu($db);
$parameters = array('context' => 'invoicelistverifactu');
$action = '';
$hookmanager = new HookManager();
$result = $actions->doActions($parameters, $object, $action, $hookmanager);

assert_equals(1, $result, "doActions returns 1");
assert_equals(array('begin', 'commit'), $db->calls, "Invoice processed normally");
assert_true(empty(Facture::$date_adjustments), "No date adjustments made");

// ---- TEST 17: Multiple conflicts counted correctly ----
echo "\nTest 17: Multiple conflicts counted correctly for confirmation\n";
reset_test_state();
$db->resetCalls();
$_mock_last_invoice_hash = array(
	'hash' => 'abc123',
	'numero' => 'FA-0099',
	'number' => 'FA-0099',
	'fecha' => '10-03-2026',
	'date' => '10-03-2026'
);
// 2 out of 3 invoices need adjustment
Facture::$invoice_dates = array(
	1 => mktime(0, 0, 0, 3, 5, 2026),   // March 5 - needs adjustment
	2 => mktime(0, 0, 0, 3, 15, 2026),  // March 15 - OK
	3 => mktime(0, 0, 0, 3, 8, 2026),   // March 8 - needs adjustment
);
$_mock_post = array('massaction' => 'validate', 'confirmmassaction' => 'yes', 'toselect' => array(1, 2, 3));

$actions = new ActionsVerifactu($db);
$parameters = array('context' => 'invoicelistverifactu');
$action = '';
$hookmanager = new HookManager();
$result = $actions->doActions($parameters, $object, $action, $hookmanager);

assert_equals(1, $result, "doActions returns 1");
assert_equals('verifactu_confirm_date_adjust', $action, "Confirmation dialog triggered");
assert_equals(2, $actions->results['verifactu_date_conflict_count'], "2 invoices need adjustment");

// ---- TEST 18: verifactu_toselect fallback works ----
echo "\nTest 18: verifactu_toselect fallback from confirmation dialog\n";
reset_test_state();
$db->resetCalls();
$_mock_post = array(
	'massaction' => 'validate',
	'confirmmassaction' => 'yes',
	'toselect' => array(),  // Empty (checkboxes not in confirmation form)
	'verifactu_toselect' => '1,2',  // Comma-separated from hidden field
	'verifactu_date_confirm' => 'yes'
);

$actions = new ActionsVerifactu($db);
$parameters = array('context' => 'invoicelistverifactu');
$action = '';
$hookmanager = new HookManager();
$result = $actions->doActions($parameters, $object, $action, $hookmanager);

assert_equals(1, $result, "doActions returns 1");
assert_equals(
	array('begin', 'commit', 'begin', 'commit'),
	$db->calls,
	"Both invoices processed via verifactu_toselect fallback"
);

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
