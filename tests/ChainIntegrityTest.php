<?php

/**
 * Regression test for the chain integrity work discussed in issue #30 (point 4).
 *
 * Covers three defects:
 *   - The chain query used DATE_FORMAT(), which only exists in MySQL, so on
 *     PostgreSQL it failed and every invoice was chained as the first record.
 *   - A failed read returned "no previous record" instead of an error.
 *   - Reading the previous fingerprint and storing the new one was not
 *     serialized, so two concurrent validations chained onto the same record.
 */

if (!defined('MAIN_DB_PREFIX')) {
	define('MAIN_DB_PREFIX', 'llx_');
}
foreach (array('LOG_ERR' => 3, 'LOG_WARNING' => 4, 'LOG_INFO' => 6, 'LOG_DEBUG' => 7) as $name => $value) {
	if (!defined($name)) {
		define($name, $value);
	}
}
if (!function_exists('dol_syslog')) {
	function dol_syslog($message, $level = 0)
	{
	}
}
if (!function_exists('getEnvironment')) {
	function getEnvironment()
	{
		return 'produccion';
	}
}

class FakeChainTranslate
{
	public function trans($key)
	{
		return $key;
	}
}

/**
 * Minimal database double reproducing the Dolibarr API used by the chain.
 */
class FakeChainDb
{
	public $type;
	public $queries = array();
	private $rows;
	private $failQuery;
	private $position = 0;
	public $lockGranted = true;

	public function __construct($type = 'pgsql', array $rows = array(), $failQuery = false)
	{
		$this->type = $type;
		$this->rows = $rows;
		$this->failQuery = $failQuery;
	}

	public function query($sql)
	{
		$this->queries[] = $sql;
		$this->position = 0;
		if (strpos($sql, 'advisory_lock') !== false || strpos($sql, 'GET_LOCK') !== false) {
			$this->rows = array(array('obtained' => $this->lockGranted ? 't' : 'f'));
			return true;
		}
		if (strpos($sql, 'advisory_unlock') !== false || strpos($sql, 'RELEASE_LOCK') !== false) {
			$this->rows = array();
			return true;
		}
		return !$this->failQuery;
	}

	public function num_rows($resql)
	{
		return count($this->rows);
	}

	public function fetch_object($resql)
	{
		if (!isset($this->rows[$this->position])) {
			return false;
		}
		return (object) $this->rows[$this->position++];
	}

	public function free($resql)
	{
	}

	public function escape($value)
	{
		return str_replace("'", "''", $value);
	}

	public function plimit($limit = 0, $offset = 0)
	{
		return ' LIMIT ' . (int) $limit;
	}

	public function lasterror()
	{
		return 'simulated failure';
	}

	public function jdate($string, $gm = 'tzserver')
	{
		if (empty($string)) {
			return '';
		}
		$digits = preg_replace('/([^0-9])/', '', $string) . '000000';
		return mktime(
			(int) substr($digits, 8, 2),
			(int) substr($digits, 10, 2),
			(int) substr($digits, 12, 2),
			(int) substr($digits, 4, 2),
			(int) substr($digits, 6, 2),
			(int) substr($digits, 0, 4)
		);
	}
}

$conf = (object) array('entity' => 1);
$conf->global = new stdClass();
$langs = new FakeChainTranslate();
$db = new FakeChainDb();

require_once __DIR__ . '/../lib/functions/functions.hash.php';

$total = 0;
$passed = 0;
function check($condition, $label)
{
	global $total, $passed;
	$total++;
	if ($condition) {
		$passed++;
		echo "  PASS: " . $label . "\n";
		return true;
	}
	echo "  FAIL: " . $label . "\n";
	return false;
}

echo "Chain query portability\n";

$db = new FakeChainDb('pgsql', array(array('hash' => str_repeat('A', 64), 'invoice_number' => 'FA-1', 'invoice_date' => '2026-09-18')));
$previous = getLastInvoiceHash();
$chainQuery = $db->queries[0];

check(stripos($chainQuery, 'DATE_FORMAT') === false, 'the chain query carries no DATE_FORMAT, which PostgreSQL does not have');
check(stripos($chainQuery, 'MONTH(') === false && stripos($chainQuery, 'YEAR(') === false, 'nor any other MySQL-only date function');
check($previous !== null && $previous['hash'] === str_repeat('A', 64), 'the previous record is found');
check($previous['fecha'] === '18-09-2026', 'the date is formatted in PHP as dd-mm-yyyy, the format sent to AEAT');
check($previous['fecha'] === $previous['date'], 'both the Spanish and the English key carry the same value');

echo "\nA failed read never passes as 'no previous record'\n";

$db = new FakeChainDb('pgsql', array(), true);
$threw = false;
try {
	getLastInvoiceHash();
} catch (Exception $e) {
	$threw = true;
}
check($threw, 'a database error raises an exception instead of returning null');

$db = new FakeChainDb('pgsql', array());
$empty = null;
$threw = false;
try {
	$empty = getLastInvoiceHash();
} catch (Exception $e) {
	$threw = true;
}
check(!$threw && $empty === null, 'an empty chain still returns null, so the first invoice opens the chain');

echo "\nSerialization of the critical section\n";

$db = new FakeChainDb('pgsql');
verifactuChainLockHeld(false);
check(acquireVerifactuChainLock(1) === true, 'the lock is granted when free');
check(verifactuChainLockHeld() === true, 'ownership is recorded');
$queriesBefore = count($db->queries);
check(acquireVerifactuChainLock(1) === true, 'asking twice in the same request succeeds');
check(count($db->queries) === $queriesBefore, 'and does not take the lock twice, which would need two releases');
releaseVerifactuChainLock();
check(verifactuChainLockHeld() === false, 'releasing clears the ownership');
check(strpos(end($db->queries), 'pg_advisory_unlock') !== false, 'PostgreSQL releases through pg_advisory_unlock');

$db = new FakeChainDb('pgsql');
$db->lockGranted = false;
verifactuChainLockHeld(false);
check(acquireVerifactuChainLock(1) === false, 'a busy chain is reported instead of submitting anyway');

$db = new FakeChainDb('mysqli');
verifactuChainLockHeld(false);
$db->lockGranted = true;
acquireVerifactuChainLock(1);
check(strpos($db->queries[0], 'GET_LOCK') !== false, 'MySQL uses GET_LOCK');
releaseVerifactuChainLock();

$db = new FakeChainDb('sqlite3');
verifactuChainLockHeld(false);
check(acquireVerifactuChainLock(1) === true, 'a driver without application locks does not block the submission');

$lockNamesDiffer = false;
$conf->entity = 1;
$firstName = getVerifactuChainLockName();
$conf->entity = 2;
$lockNamesDiffer = ($firstName !== getVerifactuChainLockName());
$conf->entity = 1;
check($lockNamesDiffer, 'each entity has its own lock, so two taxpayers never wait for each other');
check(getVerifactuChainLockKey('verifactu_chain_1_produccion') <= 0x7FFFFFFF, 'the advisory key fits in the signed 32 bit integer PostgreSQL expects');

echo "\nThe record always exists after validating\n";

$trigger = file_get_contents(__DIR__ . '/../core/triggers/interface_999_modVerifactu_VerifactuTriggers.class.php');
check(
	strpos($trigger, "'PENDING_SUBMISSION'") !== false,
	'validating with direct submission disabled leaves the invoice marked as pending'
);
check(
	strpos($trigger, "return 1; // VeriFactu not executed, continue normally") === false,
	'validation no longer ends without any VeriFactu trace'
);

$utils = file_get_contents(__DIR__ . '/../class/verifactu.utils.php');
check(strpos($utils, "PENDING_SUBMISSION%'") !== false, 'the retry queue picks those invoices up');
check(strpos($utils, "'PENDING_SUBMISSION'") !== false, 'and clears the mark once they are sent');

$submission = file_get_contents(__DIR__ . '/../lib/functions/functions.submission.php');
check(
	strpos($submission, 'acquireVerifactuChainLock()') !== false && strpos($submission, 'releaseVerifactuChainLock();') !== false,
	'the submission takes and releases the chain lock'
);
check(
	preg_match('/finally\s*\{\s*releaseVerifactuChainLock\(\);/', $submission) === 1,
	'the lock is released even when the submission throws'
);

echo "\nThe declared identity is never cut in silence\n";

$configuration = file_get_contents(__DIR__ . '/../lib/functions/functions.configuration.php');
check(
	strpos($configuration, "mb_substr(\$identity[\$field], 0, \$limit)") !== false,
	'the schema limits are applied by characters, so a multibyte character is never split'
);
check(
	preg_match('/dol_syslog\((\s|.)*?no longer matches conf\/declaracion_responsable/', $configuration) === 1,
	'cutting a declared value is reported, because declared and transmitted must match'
);

echo "\nNo MySQL-only SQL left in the module\n";

$mysqlOnly = array('DATE_FORMAT(', 'MONTH(', 'YEAR(', 'IFNULL(', 'CURDATE(');
$offenders = array();
$directory = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/..'));
foreach ($directory as $file) {
	$path = $file->getPathname();
	if (substr($path, -4) !== '.php') {
		continue;
	}
	if (strpos($path, '/vendor/') !== false || strpos($path, '/tests/') !== false || strpos($path, '/newfenix/') !== false) {
		continue;
	}
	foreach (file($path) as $number => $line) {
		if (strpos($line, '$sql') === false && stripos($line, 'SELECT ') === false) {
			continue;
		}
		foreach ($mysqlOnly as $needle) {
			if (strpos($line, $needle) !== false) {
				$offenders[] = basename($path) . ':' . ($number + 1) . ' ' . $needle;
			}
		}
	}
}
check(empty($offenders), 'no query uses a MySQL-only function' . (empty($offenders) ? '' : ': ' . implode(', ', $offenders)));

echo "\nTotal: " . $total . " | Passed: " . $passed . " | Failed: " . ($total - $passed) . "\n";
exit($total === $passed ? 0 : 1);
