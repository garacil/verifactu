<?php

if (!defined('MAIN_DB_PREFIX')) {
	define('MAIN_DB_PREFIX', 'llx_');
}
if (!defined('LOG_ERR')) {
	define('LOG_ERR', 3);
}

if (!function_exists('isModEnabled')) {
	function isModEnabled($module)
	{
		return $module === 'multicompany';
	}
}
if (!function_exists('dol_syslog')) {
	function dol_syslog($message, $level = 0)
	{
	}
}
if (!function_exists('getDolGlobalInt')) {
	function getDolGlobalInt($name)
	{
		global $enabledSharingConstants;
		return !empty($enabledSharingConstants[$name]) ? 1 : 0;
	}
}

require_once __DIR__ . '/../lib/functions/functions.configuration.php';

class FakeEntitySharingDatabase
{
	private $rows;
	private $position = 0;

	public function __construct(array $rows)
	{
		$this->rows = $rows;
	}

	public function query($sql)
	{
		$this->position = 0;
		return true;
	}

	public function fetch_object($result)
	{
		if (!isset($this->rows[$this->position])) {
			return false;
		}
		return (object) $this->rows[$this->position++];
	}

	public function free($result)
	{
	}

	public function lasterror()
	{
		return '';
	}
}

function sharingOptions(array $sharings)
{
	return json_encode(array('sharings' => $sharings));
}

$conf = (object) array('entity' => 2);
$enabledSharingConstants = array(
	'MULTICOMPANY_INVOICE_SHARING_ENABLED' => 1,
	'MULTICOMPANY_INVOICENUMBER_SHARING_ENABLED' => 1,
	'MULTICOMPANY_BANKACCOUNT_SHARING_ENABLED' => 1,
);

$db = new FakeEntitySharingDatabase(array(
	array('rowid' => 1, 'options' => sharingOptions(array())),
	array('rowid' => 2, 'options' => sharingOptions(array())),
));
if (!isEntitySharingAllowed('invoice', 2)) {
	fwrite(STDERR, "An isolated entity must allow invoice use\n");
	exit(1);
}

$enabledSharingConstants['MULTICOMPANY_INVOICE_SHARING_ENABLED'] = 0;
$db = new FakeEntitySharingDatabase(array(
	array('rowid' => 2, 'options' => sharingOptions(array('invoice' => array('1')))),
));
if (!isEntitySharingAllowed('invoice', 2)) {
	fwrite(STDERR, "Stored sharing must be ignored when the feature is disabled\n");
	exit(1);
}
$enabledSharingConstants['MULTICOMPANY_INVOICE_SHARING_ENABLED'] = 1;

$db = new FakeEntitySharingDatabase(array(
	array('rowid' => 2, 'options' => sharingOptions(array('invoice' => array('1')))),
));
if (isEntitySharingAllowed('invoice', 2)) {
	fwrite(STDERR, "Outgoing invoice sharing must be rejected\n");
	exit(1);
}

$db = new FakeEntitySharingDatabase(array(
	array('rowid' => 1, 'options' => sharingOptions(array('bankaccount' => array('2')))),
	array('rowid' => 2, 'options' => sharingOptions(array())),
));
if (isEntitySharingAllowed('bankaccount', 2)) {
	fwrite(STDERR, "Incoming bank account sharing must be rejected\n");
	exit(1);
}

$db = new FakeEntitySharingDatabase(array(
	array('rowid' => 1, 'options' => sharingOptions(array('invoicenumber' => array(2)))),
	array('rowid' => 2, 'options' => sharingOptions(array())),
));
if (isEntitySharingAllowed('invoicenumber', 2)) {
	fwrite(STDERR, "Numeric incoming invoice-number sharing must be rejected\n");
	exit(1);
}

echo "Entity sharing isolation tests passed\n";
