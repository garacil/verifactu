<?php

/**
 * Regression test for what was applied from PR #34 and PR #39, which is not
 * what either of them proposed.
 *
 * PR #39 wanted a cancellation never to open the chain. The schema says
 * otherwise, so the module now marks it as the first record instead of leaving
 * the chaining unset, which used to fail validation.
 *
 * PR #34 wanted the taxpayer and certificate uniqueness enforced in the
 * submission path, reading the internals of MultiCompany. The taxpayer check is
 * kept, moved to the configuration screen and resolved with core tables only.
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
$multicompanyEnabled = true;
if (!function_exists('isModEnabled')) {
	function isModEnabled($module)
	{
		global $multicompanyEnabled;
		return $module === 'multicompany' ? $multicompanyEnabled : false;
	}
}

class FakeEntityDb
{
	public $type = 'pgsql';
	private $rows;
	private $position = 0;
	private $failQuery;
	public $lastQuery = '';

	public function __construct(array $rows = array(), $failQuery = false)
	{
		$this->rows = $rows;
		$this->failQuery = $failQuery;
	}

	public function query($sql)
	{
		$this->lastQuery = $sql;
		$this->position = 0;
		return !$this->failQuery;
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

	public function decrypt($value)
	{
		return $value;
	}

	public function escape($value)
	{
		return $value;
	}

	public function plimit($limit = 0, $offset = 0)
	{
		return ' LIMIT ' . (int) $limit;
	}

	public function lasterror()
	{
		return 'simulated failure';
	}
}

$conf = (object) array('entity' => 2);
$conf->global = new stdClass();
$db = new FakeEntityDb();

require_once __DIR__ . '/../lib/functions/functions.configuration.php';

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

echo "PR #34: one taxpayer cannot own two VeriFactu entities\n";

$db = new FakeEntityDb(array(array('entity' => 1, 'value' => ' B-12345678 ')));
check(getVerifactuEntityWithSameTaxId('b12345678', 2) === 1, 'the same NIF in another entity is reported, whatever its formatting');
check(getVerifactuEntityWithSameTaxId('A87654321', 2) === null, 'a different NIF is accepted');

$db = new FakeEntityDb(array(array('entity' => 1, 'value' => 'B12345678')));
check(getVerifactuEntityWithSameTaxId('', 2) === null, 'an empty NIF is not compared');
check(
	strpos($db->lastQuery, 'MAIN_MODULE_VERIFACTU') === false,
	'and no query is run for it'
);

$db = new FakeEntityDb(array(array('entity' => 1, 'value' => 'B12345678')));
getVerifactuEntityWithSameTaxId('B12345678', 2);
check(strpos($db->lastQuery, "MAIN_MODULE_VERIFACTU") !== false, 'only entities with VeriFactu enabled are considered');
check(strpos($db->lastQuery, 'llx_entity') === false, 'the check reads core tables only, not the MultiCompany ones');
check(strpos($db->lastQuery, 'taxpayer.entity <> 2') !== false, 'the entity being configured is excluded');

$db = new FakeEntityDb(array(), true);
check(getVerifactuEntityWithSameTaxId('B12345678', 2) === null, 'a query error does not block the configuration screen');

$multicompanyEnabled = false;
$db = new FakeEntityDb(array(array('entity' => 1, 'value' => 'B12345678')));
check(getVerifactuEntityWithSameTaxId('B12345678', 2) === null, 'without MultiCompany there is nothing to compare');
$multicompanyEnabled = true;

echo "\nPR #34: shared invoice numbering is reported\n";

$conf->global = new stdClass();
check(isVerifactuInvoiceNumberingShared() === false, 'nothing is reported when sharing is off');
$conf->global->MULTICOMPANY_SHARINGS_ENABLED = 1;
check(isVerifactuInvoiceNumberingShared() === false, 'nor when sharing is on but numbering is not shared');
$conf->global->MULTICOMPANY_INVOICENUMBER_SHARING_ENABLED = 1;
check(isVerifactuInvoiceNumberingShared() === true, 'shared invoice numbering is reported');

$setup = file_get_contents(__DIR__ . '/../admin/setup.php');
check(
	strpos($setup, 'getVerifactuEntityWithSameTaxId($postedTaxId') !== false,
	'the configuration screen refuses a NIF already used by another entity'
);
check(
	strpos($setup, 'isVerifactuInvoiceNumberingShared()') !== false,
	'and warns about shared numbering'
);

$submission = file_get_contents(__DIR__ . '/../lib/functions/functions.submission.php');
check(
	strpos($submission, 'getVerifactuEntityWithSameTaxId') === false,
	'none of this runs in the submission path, where a false reverts the invoice to draft'
);

echo "\nPR #39: a cancellation may open the chain\n";

$cancellation = file_get_contents(__DIR__ . '/../lib/functions/functions.cancellation.php');
check(
	strpos($cancellation, '$cancellation->setAsFirstInChain();') !== false,
	'with no previous record the cancellation is marked as the first one'
);
check(
	preg_match('/SuministroInformacion\.xsd declares/', $cancellation) === 1,
	'and the schema reference is recorded next to it'
);

require_once __DIR__ . '/../lib/newfenix/src/Cancellation.php';
$object = new OpenAEAT\Billing\Cancellation('F-1', '01-01-2026', 'A00000000', 'A00000000', 'Test');
$object->setAsFirstInChain();
$property = new ReflectionProperty($object, 'registrationPayload');
$property->setAccessible(true);
$payload = $property->getValue($object);
check(
	isset($payload['Encadenamiento']['PrimerRegistro']) && $payload['Encadenamiento']['PrimerRegistro'] === 'S',
	'the library still supports it, as SuministroInformacion.xsd does'
);

$validated = false;
try {
	$validated = $object->validate();
} catch (Exception $e) {
	$validated = false;
}
check($validated === true, 'and such a cancellation validates instead of being rejected');

echo "\nTotal: " . $total . " | Passed: " . $passed . " | Failed: " . ($total - $passed) . "\n";
exit($total === $passed ? 0 : 1);
